<?php

namespace App\Services;

use App\Models\Collector;
use App\Models\Mark;
use App\Models\Saver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LedgerService
{
    /** Each mark commits to the one before it, so any later edit breaks the chain. */
    public static function hash(?string $prev, int $collectorId, int $saverId, string $date, int $amountKobo, string $stamp): string
    {
        return hash('sha256', implode('|', [$prev ?? 'GENESIS', $collectorId, $saverId, $date, $amountKobo, $stamp]));
    }

    public static function stamp(Carbon $t): string
    {
        return $t->format('Y-m-d H:i:s'); // stored as a plain datetime, so no timezone maths can change it
    }

    public function record(Saver $s, Carbon $date, int $amountKobo, string $channel, int $recordedBy, ?string $idempotencyKey = null): Mark
    {
        return DB::transaction(function () use ($s, $date, $amountKobo, $channel, $recordedBy, $idempotencyKey) {
            Collector::whereKey($s->collector_id)->lockForUpdate()->first(); // one writer per chain (MySQL)

            if ($idempotencyKey && ($existing = Mark::where('idempotency_key', $idempotencyKey)->first())) {
                return $existing;
            }
            if (Mark::where('saver_id', $s->id)->whereDate('mark_date', $date->toDateString())->exists()) {
                throw new \DomainException('This day is already marked.');
            }

            $prev = Mark::where('collector_id', $s->collector_id)->orderByDesc('id')->value('hash');
            $now = now()->startOfSecond();
            $hash = self::hash($prev, $s->collector_id, $s->id, $date->toDateString(), $amountKobo, self::stamp($now));

            return Mark::create([
                'saver_id' => $s->id,
                'collector_id' => $s->collector_id,
                'mark_date' => $date->toDateString(),
                'amount_kobo' => $amountKobo,
                'channel' => $channel,
                'idempotency_key' => $idempotencyKey,
                'recorded_by' => $recordedBy,
                'prev_hash' => $prev,
                'hash' => $hash,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }
}
