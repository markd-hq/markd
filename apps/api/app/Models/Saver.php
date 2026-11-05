<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Saver extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['started_on' => 'date', 'claimed_at' => 'datetime'];

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function collector()
    {
        return $this->belongsTo(Collector::class);
    }

    public function marks()
    {
        return $this->hasMany(Mark::class);
    }

    public function cycleStart(): Carbon
    {
        return $this->started_on->copy()->startOfDay();
    }

    public function cycleEnd(): Carbon
    {
        return $this->started_on->copy()->addDays($this->cycle_days - 1)->startOfDay();
    }

    public static function makeClaimCode(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (static::where('claim_code', $code)->exists());

        return $code;
    }
}
