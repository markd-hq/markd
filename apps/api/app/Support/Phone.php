<?php

namespace App\Support;

class Phone
{
    /** Returns +234XXXXXXXXXX for any common Nigerian format, or null when invalid. */
    public static function normalize(string $raw): ?string
    {
        $d = preg_replace('/\D+/', '', $raw) ?? '';
        if ($d === '') {
            return null;
        }
        if (strlen($d) === 11 && $d[0] === '0') {
            $d = '234' . substr($d, 1);
        } elseif (strlen($d) === 10) {
            $d = '234' . $d;
        }

        return preg_match('/^234[789]\d{9}$/', $d) ? '+' . $d : null;
    }
}
