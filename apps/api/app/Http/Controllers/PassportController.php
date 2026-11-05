<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\Guards;
use App\Models\User;
use App\Services\PassportService;
use App\Support\Track;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PassportController extends Controller
{
    use Guards;

    public function mine(Request $r, PassportService $svc)
    {
        $u = $this->mustBe($r, 'saver', 'collector', 'admin');

        return $svc->forUser($u) + ['shared' => DB::table('passport_shares')->where('user_id', $u->id)->whereNull('revoked_at')->where('expires_at', '>', now())->count() > 0];
    }

    /** The saver chooses to share their statement. The link works for 30 days and can be switched off. */
    public function share(Request $r)
    {
        $u = $this->mustBe($r, 'saver', 'collector', 'admin');
        $token = bin2hex(random_bytes(24));
        DB::table('passport_shares')->insert([
            'user_id' => $u->id, 'token' => $token, 'expires_at' => now()->addDays(30),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Track::event('passport_shared', $u->id, [], $r);

        return ['token' => $token, 'expires_at' => now()->addDays(30)->toIso8601String()];
    }

    public function revoke(Request $r)
    {
        $u = $this->mustBe($r, 'saver', 'collector', 'admin');
        DB::table('passport_shares')->where('user_id', $u->id)->whereNull('revoked_at')->update(['revoked_at' => now(), 'updated_at' => now()]);
        Track::event('passport_revoked', $u->id, [], $r);

        return ['ok' => true];
    }

    /** Public: whoever holds the link (a lender, for example) can read the statement the saver chose to share. */
    public function show(Request $r, string $token, PassportService $svc)
    {
        $share = DB::table('passport_shares')->where('token', $token)->whereNull('revoked_at')->where('expires_at', '>', now())->first();
        abort_unless($share, 404, 'This link has expired or was switched off.');

        DB::table('passport_shares')->where('id', $share->id)->increment('views');
        Track::event('passport_viewed', null, [], $r);

        return $svc->forUser(User::findOrFail($share->user_id));
    }
}
