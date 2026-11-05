<?php

namespace App\Support;

use DateTimeImmutable;

class SaverHealth
{
    /**
     * How is this saver doing? Looks at every day from the start of the cycle up to yesterday.
     * Pure function on purpose: easy to test, easy to tune after talking to real collectors.
     *
     * status: on_track | late | at_risk | defaulted
     */
    public static function assess(string $startedOn, int $cycleDays, array $markedDates, string $today, int $defaultAfter = 7): array
    {
        $start = new DateTimeImmutable($startedOn);
        $cycleEnd = $start->modify('+' . ($cycleDays - 1) . ' days');
        $yesterday = (new DateTimeImmutable($today))->modify('-1 day');
        $last = $yesterday < $cycleEnd ? $yesterday : $cycleEnd;

        $marked = array_flip(array_unique($markedDates));

        if ($last < $start) { // the cycle has only just begun
            return ['status' => 'on_track', 'expected_days' => 0, 'marked_days' => 0, 'missed_days' => 0,
                'consecutive_missed' => 0, 'completion_pct' => 100, 'score' => 100, 'label' => 'New'];
        }

        $expected = (int) $start->diff($last)->days + 1;
        $count = 0;
        for ($d = $start; $d <= $last; $d = $d->modify('+1 day')) {
            if (isset($marked[$d->format('Y-m-d')])) {
                $count++;
            }
        }

        $streak = 0;
        for ($d = $last; $d >= $start; $d = $d->modify('-1 day')) {
            if (isset($marked[$d->format('Y-m-d')])) {
                break;
            }
            $streak++;
        }

        $completion = $count / $expected;
        $status = match (true) {
            $streak >= $defaultAfter => 'defaulted',
            $streak >= 3 || $completion < 0.5 => 'at_risk',
            $streak >= 1 || $completion < 0.7 => 'late',
            default => 'on_track',
        };
        $score = max(0, min(100, (int) round($completion * 100 - 5 * min($streak, 10))));

        return [
            'status' => $status,
            'expected_days' => $expected,
            'marked_days' => $count,
            'missed_days' => $expected - $count,
            'consecutive_missed' => $streak,
            'completion_pct' => (int) round($completion * 100),
            'score' => $score,
            'label' => $score >= 85 ? 'Reliable' : ($score >= 60 ? 'Fair' : 'Risky'),
        ];
    }
}
