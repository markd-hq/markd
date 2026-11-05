<?php

namespace App\Console\Commands;

use App\Services\ReferralService;
use Illuminate\Console\Command;

class EvaluateReferrals extends Command
{
    protected $signature = 'markd:referrals';
    protected $description = 'Qualify pending referrals that have met the activity rules';

    public function handle(ReferralService $referrals): int
    {
        $this->info($referrals->evaluate() . ' referral(s) qualified');

        return self::SUCCESS;
    }
}
