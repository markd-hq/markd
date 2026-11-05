<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\Guards;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    use Guards;

    /** The numbers you show investors: users, activity, volume, retention signals, AI use. */
    public function stats(Request $r)
    {
        $this->mustBe($r, 'admin');
        $d7 = now()->subDays(7);
        $d30 = now()->subDays(30);
        $h24 = now()->subDay();
        $today = today()->toDateString();

        $count = fn (string $table, ?callable $where = null) => ($where ? $where(DB::table($table)) : DB::table($table))->count();
        $by = fn (string $table, string $col) => DB::table($table)->select($col, DB::raw('count(*) as n'))->groupBy($col)->pluck('n', $col);

        return [
            'users' => [
                'total' => $count('users'),
                'new_7d' => $count('users', fn ($q) => $q->where('created_at', '>=', $d7)),
                'active_24h' => $count('users', fn ($q) => $q->where('last_seen_at', '>=', $h24)),
                'by_role' => $by('users', 'role'),
                'wallets_linked' => $count('users', fn ($q) => $q->whereNotNull('wallet_verified_at')),
            ],
            'collectors' => [
                'total' => $count('collectors'),
                'verified' => $count('collectors', fn ($q) => $q->where('status', 'verified')),
            ],
            'savers' => [
                'total' => $count('savers'),
                'active' => $count('savers', fn ($q) => $q->where('status', 'active')),
                'marked_last_7d' => DB::table('marks')->where('mark_date', '>=', $d7->toDateString())->distinct()->count('saver_id'),
            ],
            'marks' => [
                'today' => $count('marks', fn ($q) => $q->whereDate('mark_date', $today)),
                'last_7d' => $count('marks', fn ($q) => $q->where('mark_date', '>=', $d7->toDateString())),
                'volume_kobo_today' => (int) DB::table('marks')->whereDate('mark_date', $today)->sum('amount_kobo'),
                'volume_kobo_7d' => (int) DB::table('marks')->where('mark_date', '>=', $d7->toDateString())->sum('amount_kobo'),
                'volume_kobo_30d' => (int) DB::table('marks')->where('mark_date', '>=', $d30->toDateString())->sum('amount_kobo'),
            ],
            'payouts' => DB::table('payouts')->select('status', DB::raw('count(*) as n'), DB::raw('coalesce(sum(net_kobo),0) as net_kobo'), DB::raw('coalesce(sum(platform_fee_kobo),0) as platform_fee_kobo'))->groupBy('status')->get(),
            'referrals' => $by('referrals', 'status'),
            'ai' => [
                'questions' => $count('ai_queries'),
                'last_24h' => $count('ai_queries', fn ($q) => $q->where('created_at', '>=', $h24)),
                'failed' => $count('ai_queries', fn ($q) => $q->where('ok', false)),
            ],
            'events_7d' => DB::table('events')->where('created_at', '>=', $d7)->select('type', DB::raw('count(*) as n'))->groupBy('type')->pluck('n', 'type'),
        ];
    }
}
