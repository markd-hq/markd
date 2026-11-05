<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\Guards;
use App\Models\Saver;
use App\Support\Pay;
use App\Support\Track;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PayoutController extends Controller
{
    use Guards;

    public function preview(Request $r, Saver $saver)
    {
        $u = $this->mustBe($r, 'collector');
        abort_unless($saver->collector->user_id === $u->id, 403, 'Not your saver.');

        return $this->compute($saver);
    }

    public function store(Request $r)
    {
        $u = $this->mustBe($r, 'collector');
        $data = $r->validate([
            'saver_id' => 'required|uuid',
            'bank_code' => 'required|string|max:10',
            'account_number' => 'required|digits:10',
            'account_name' => 'required|string|max:120',
        ]);
        $s = Saver::where('public_id', $data['saver_id'])
            ->whereHas('collector', fn ($q) => $q->where('user_id', $u->id))->firstOrFail();

        // A payout is requested once the cycle is over. On a local machine you can test it early.
        abort_if(today()->lte($s->cycleEnd()) && ! app()->environment('local'), 422, 'This cycle is not finished yet.');

        $calc = $this->compute($s);
        abort_if(DB::table('payouts')->where('saver_id', $s->id)->where('cycle_start', $calc['cycle_start'])->exists(), 422, 'A payout was already requested for this cycle.');

        DB::table('payouts')->insert([
            'public_id' => (string) Str::uuid(),
            'saver_id' => $s->id,
            'collector_id' => $s->collector_id,
            'cycle_start' => $calc['cycle_start'],
            'cycle_end' => $calc['cycle_end'],
            'days_marked' => $calc['days_marked'],
            'gross_kobo' => $calc['gross_kobo'],
            'commission_kobo' => $calc['commission_kobo'],
            'platform_fee_kobo' => $calc['platform_fee_kobo'],
            'net_kobo' => $calc['net_kobo'],
            'status' => 'requested',
            'bank_code' => $data['bank_code'],
            'account_number' => Crypt::encryptString($data['account_number']),
            'account_name' => $data['account_name'],
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Track::event('payout_requested', $u->id, ['net_kobo' => $calc['net_kobo']], $r);

        return response()->json($calc, 201);
    }

    private function compute(Saver $s): array
    {
        $window = $s->marks()->whereBetween('mark_date', [$s->cycleStart()->toDateString(), $s->cycleEnd()->toDateString()]);
        $gross = (int) (clone $window)->sum('amount_kobo');
        $split = Pay::split($gross, $s->daily_kobo, $s->collector->commission_days, (int) config('markd.platform_share_bps'));

        return $split + [
            'saver' => $s->name,
            'cycle_start' => $s->cycleStart()->toDateString(),
            'cycle_end' => $s->cycleEnd()->toDateString(),
            'days_marked' => (clone $window)->count(),
            'net_naira' => Pay::naira($split['net_kobo']),
        ];
    }
}
