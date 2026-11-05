<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ReferralService
{
    /**
     * Flat rewards only, one level deep, paid after real activity.
     * Never a percentage of anyone's savings. Returns how many referrals qualified in this run.
     */
    public function evaluate(): int
    {
        $cfg = config('markd.referral');
        $qualified = 0;

        $pending = DB::table('referrals')->join('users', 'users.id', '=', 'referrals.referred_id')
            ->where('referrals.status', 'pending')->select('referrals.id', 'referrals.referred_id', 'users.role')->get();

        foreach ($pending as $r) {
            if ($r->role === 'collector') {
                $active = DB::table('savers')->join('collectors', 'collectors.id', '=', 'savers.collector_id')
                    ->where('collectors.user_id', $r->referred_id)
                    ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('marks')->whereColumn('marks.saver_id', 'savers.id')
                        ->where('marks.mark_date', '>=', now()->subDays(14)->toDateString()))
                    ->count();
                $ok = $active >= $cfg['collector_min_active'];
                $reward = $cfg['collector_reward_kobo'];
            } else {
                $days = DB::table('marks')->join('savers', 'savers.id', '=', 'marks.saver_id')
                    ->where('savers.user_id', $r->referred_id)->distinct()->count('marks.mark_date');
                $ok = $days >= $cfg['saver_min_days'];
                $reward = $cfg['saver_reward_kobo'];
            }
            if ($ok) {
                DB::table('referrals')->where('id', $r->id)->update(['status' => 'qualified', 'qualified_at' => now(), 'reward_kobo' => $reward, 'updated_at' => now()]);
                $qualified++;
            }
        }

        return $qualified;
    }
}
