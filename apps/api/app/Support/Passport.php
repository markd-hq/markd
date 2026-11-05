<?php

namespace App\Support;

class Passport
{
    /**
     * An indicative reading of savings consistency. It is NOT a credit decision and never a loan amount.
     * Tune the thresholds after talking to lenders and collectors.
     */
    public static function readiness(int $score, int $historyDays, int $marks): array
    {
        if ($historyDays < 14 || $marks < 10) {
            return ['band' => 'Building', 'note' => 'Keep saving. A record of at least 14 days is needed before it means much to a lender.'];
        }
        if ($score >= 85 && $historyDays >= 30) {
            return ['band' => 'Strong', 'note' => 'A consistent, verified saving record of 30 days or more.'];
        }
        if ($score >= 70) {
            return ['band' => 'Ready', 'note' => 'A steady, verified record. A lender could use this as supporting evidence.'];
        }

        return ['band' => 'Building', 'note' => 'The record has gaps. Regular saving will improve it.'];
    }
}
