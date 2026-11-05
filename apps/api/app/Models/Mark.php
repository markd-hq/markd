<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mark extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['mark_date' => 'date', 'created_at' => 'datetime'];

    // The ledger is append-only. Corrections are new, reversing entries.
    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Marks are append-only.'));
        static::deleting(fn () => throw new \LogicException('Marks are append-only.'));
    }

    public function saver()
    {
        return $this->belongsTo(Saver::class);
    }
}
