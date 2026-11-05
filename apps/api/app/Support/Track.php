<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Track
{
    /** Product analytics. Never lets a tracking problem break the user's action. */
    public static function event(string $type, ?int $userId = null, array $meta = [], ?Request $request = null): void
    {
        try {
            DB::table('events')->insert([
                'user_id' => $userId,
                'type' => $type,
                'meta' => json_encode($meta),
                'ip_hash' => $request ? hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')) : null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
