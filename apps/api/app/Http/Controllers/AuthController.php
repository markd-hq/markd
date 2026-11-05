<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\Guards;
use App\Models\User;
use App\Support\Phone;
use App\Support\Track;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    use Guards;

    public function register(Request $r)
    {
        $r->merge(['email' => strtolower(trim((string) $r->input('email')))]);
        $data = $r->validate([
            'name' => 'required|string|max:80',
            'email' => 'required|email:rfc|max:190|unique:users,email',
            'password' => 'required|string|min:8|max:72',
            'role' => 'nullable|in:collector,saver',
            'language' => 'nullable|in:en,pcm,yo,ig,ha',
            'phone' => 'nullable|string|max:20',
            'referral_code' => 'nullable|string|max:12',
        ]);
        $phone = ! empty($data['phone']) ? Phone::normalize($data['phone']) : null;
        abort_if(! empty($data['phone']) && ! $phone, 422, 'That phone number is not valid.');

        $referrer = ! empty($data['referral_code'])
            ? User::where('referral_code', strtoupper($data['referral_code']))->first() : null;

        $user = User::create([
            'public_id' => (string) Str::uuid(),
            'email' => $data['email'],
            'password' => $data['password'],                // hashed automatically by the model cast
            'name' => $data['name'],
            'phone' => $phone,
            'role' => $data['role'] ?? 'saver',
            'language' => $data['language'] ?? 'en',
            'referral_code' => User::makeCode(),
            'referred_by' => $referrer?->id,
        ]);

        if ($referrer) {
            DB::table('referrals')->insert([
                'referrer_id' => $referrer->id, 'referred_id' => $user->id,
                'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        Track::event('signup', $user->id, ['role' => $user->role, 'referred' => (bool) $referrer], $r);

        return response()->json($this->session($user), 201);
    }

    public function login(Request $r)
    {
        $data = $r->validate(['email' => 'required|email', 'password' => 'required|string|max:72']);
        $user = User::where('email', strtolower(trim($data['email'])))->first();

        abort_if($user && $user->locked_until && $user->locked_until->isFuture(), 429, 'Too many attempts. Try again in a few minutes.');

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            if ($user) {
                $fails = $user->failed_logins + 1;
                $user->forceFill(['failed_logins' => $fails, 'locked_until' => $fails >= 5 ? now()->addMinutes(15) : null])->save();
            }
            abort(422, 'Invalid email or password.'); // same message either way, so emails cannot be probed
        }

        $user->forceFill(['failed_logins' => 0, 'locked_until' => null])->save();
        Track::event('signin', $user->id, [], $r);

        return $this->session($user);
    }

    public function me(Request $r)
    {
        $u = $this->mustBe($r, 'saver', 'collector', 'admin');
        $refs = DB::table('referrals')->where('referrer_id', $u->id)
            ->selectRaw('status, count(*) as n, coalesce(sum(reward_kobo), 0) as reward_kobo')->groupBy('status')->get();

        return ['user' => $this->userJson($u), 'referrals' => $refs];
    }

    public function logout(Request $r)
    {
        $r->user()?->currentAccessToken()?->delete();

        return ['ok' => true];
    }

    private function session(User $user): array
    {
        return ['token' => $user->createToken('markd')->plainTextToken, 'user' => $this->userJson($user)];
    }
}
