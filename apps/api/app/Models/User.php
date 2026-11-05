<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    protected $guarded = ['id'];
    protected $hidden = ['password'];
    protected $casts = [
        'password' => 'hashed',
        'email_verified_at' => 'datetime',
        'wallet_verified_at' => 'datetime',
        'locked_until' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function collector()
    {
        return $this->hasOne(Collector::class);
    }

    public static function makeCode(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'; // no look-alike characters
        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (static::where('referral_code', $code)->exists());

        return $code;
    }
}
