<?php

namespace App\Support;

class Pay
{
    public static function toKobo(int|float|string $naira): int
    {
        return (int) round(((float) $naira) * 100);
    }

    public static function naira(int $kobo): string
    {
        return number_format($kobo / 100, 2, '.', ',');
    }

    /**
     * Traditional ajo: the collector keeps `commissionDays` days of contributions per cycle.
     * Markd takes `platformBps` of that commission. The saver gets the rest back.
     */
    public static function split(int $grossKobo, int $dailyKobo, int $commissionDays, int $platformBps): array
    {
        $commission = min($grossKobo, $commissionDays * $dailyKobo);
        $platform = intdiv($commission * $platformBps, 10000);

        return [
            'gross_kobo' => $grossKobo,
            'commission_kobo' => $commission,
            'platform_fee_kobo' => $platform,
            'collector_earns_kobo' => $commission - $platform,
            'net_kobo' => $grossKobo - $commission,
        ];
    }
}
