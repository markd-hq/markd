<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\Guards;
use App\Models\User;
use App\Support\SuiWallet;
use App\Support\Track;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WalletController extends Controller
{
    use Guards;

    /** Step 1: the server hands out a one-time message. The wallet must sign exactly this text. */
    public function challenge(Request $r)
    {
        $u = $this->mustBe($r, 'saver', 'collector', 'admin');
        $nonce = bin2hex(random_bytes(16));
        $expires = now()->addMinutes(10);
        $message = "Markd wants to link this wallet to {$u->email}.\nNonce: {$nonce}\nExpires: " . $expires->utc()->format('Y-m-d\TH:i:s\Z');

        DB::table('auth_challenges')->insert([
            'user_id' => $u->id, 'nonce' => $nonce, 'message' => $message,
            'expires_at' => $expires, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return ['nonce' => $nonce, 'message' => $message, 'expires_at' => $expires->toIso8601String()];
    }

    /** Step 2: the wallet signed it. We check the signature and save the address. */
    public function link(Request $r)
    {
        $u = $this->mustBe($r, 'saver', 'collector', 'admin');
        $data = $r->validate([
            'nonce' => 'required|string|max:40',
            'address' => 'required|string|max:80',
            'signature' => 'required|string|max:400',
        ]);

        $c = DB::table('auth_challenges')->where('user_id', $u->id)->where('nonce', $data['nonce'])
            ->whereNull('used_at')->where('expires_at', '>', now())->first();
        abort_unless($c, 422, 'That request expired. Start again.');

        abort_unless(SuiWallet::verifyPersonalMessage($c->message, $data['signature'], $data['address']), 422, 'The wallet signature did not check out.');

        $address = strtolower($data['address']);
        abort_if(User::where('wallet_address', $address)->where('id', '!=', $u->id)->exists(), 422, 'That wallet is already linked to another account.');

        DB::table('auth_challenges')->where('id', $c->id)->update(['used_at' => now()]);
        $u->forceFill(['wallet_address' => $address, 'wallet_verified_at' => now()])->save();
        Track::event('wallet_linked', $u->id, [], $r);

        return ['wallet_address' => $address, 'verified' => true];
    }
}
