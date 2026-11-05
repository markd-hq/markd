<?php

namespace App\Services;

use App\Models\Saver;
use App\Models\User;
use App\Support\Pay;
use App\Support\Passport;
use App\Support\SaverHealth;

class PassportService
{
    /** A saver's verified savings statement, built from the tamper-proof ledger. */
    public function forUser(User $u): array
    {
        $savers = Saver::where('user_id', $u->id)->with('collector:id,business_name,market_name')->get();
        if ($savers->isEmpty()) {
            return ['empty' => true, 'name' => $u->name];
        }

        $total = 0;
        $marksCount = 0;
        $first = null;
        $weighted = 0;
        $weights = 0;
        $rank = ['on_track' => 0, 'late' => 1, 'at_risk' => 2, 'defaulted' => 3];
        $worst = 'on_track';
        $refs = [];

        foreach ($savers as $s) {
            $marks = $s->marks()->orderBy('mark_date')->get(['mark_date', 'amount_kobo', 'hash']);
            foreach ($marks as $m) {
                $total += $m->amount_kobo;
                $d = $m->mark_date->toDateString();
                $first = $first === null || $d < $first ? $d : $first;
                $refs[] = ['date' => $d, 'amount_naira' => Pay::naira($m->amount_kobo), 'ref' => substr($m->hash, 0, 12)];
            }
            $marksCount += $marks->count();

            $h = SaverHealth::assess($s->started_on->toDateString(), $s->cycle_days, $marks->map(fn ($m) => $m->mark_date->toDateString())->all(), today()->toDateString(), (int) config('markd.default_after_days'));
            $w = max($h['expected_days'], 1);
            $weighted += $h['score'] * $w;
            $weights += $w;
            if ($rank[$h['status']] > $rank[$worst]) {
                $worst = $h['status'];
            }
        }

        $score = (int) round($weighted / max($weights, 1));
        $historyDays = $first ? (int) (new \DateTimeImmutable($first))->diff(new \DateTimeImmutable(today()->toDateString()))->days : 0;
        usort($refs, fn ($a, $b) => strcmp($b['date'], $a['date']));

        return [
            'empty' => false,
            'name' => $u->name,
            'summary' => [
                'total_saved_naira' => Pay::naira($total),
                'days_marked' => $marksCount,
                'saving_since' => $first,
                'history_days' => $historyDays,
                'collectors' => $savers->map(fn ($s) => $s->collector->business_name)->unique()->values(),
            ],
            'reliability' => ['score' => $score, 'status' => $worst, 'label' => $score >= 85 ? 'Reliable' : ($score >= 60 ? 'Fair' : 'Risky')],
            'readiness' => Passport::readiness($score, $historyDays, $marksCount),
            'recent_records' => array_slice($refs, 0, 6),
            'disclaimer' => 'Indicative only. This is a record of saving behaviour, not a credit decision.',
        ];
    }
}
