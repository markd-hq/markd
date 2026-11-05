<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Collector extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['verified_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function savers()
    {
        return $this->hasMany(Saver::class);
    }
}
