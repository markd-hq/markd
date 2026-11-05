<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use Illuminate\Http\Request;

trait Guards
{
    /** Allow only the listed roles, and keep "last seen" fresh for activity tracking. */
    protected function mustBe(Request $r, string ...$roles): User
    {
        $u = $r->user();
        abort_unless($u && in_array($u->role, $roles, true), 403, 'You are not allowed to do that.');
        if (! $u->last_seen_at || $u->last_seen_at->lt(now()->subMinute())) {
            $u->forceFill(['last_seen_at' => now()])->saveQuietly();
        }

        return $u;
    }

    protected function userJson(User $u): array
    {
        return [
            'id' => $u->public_id,
            'email' => $u->email,
            'name' => $u->name,
            'role' => $u->role,
            'language' => $u->language,
            'referral_code' => $u->referral_code,
            'wallet_address' => $u->wallet_address,
            'wallet_verified' => $u->wallet_verified_at !== null,
        ];
    }
}
