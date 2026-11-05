<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\Guards;
use App\Models\Saver;
use App\Services\LedgerService;
use App\Support\Pay;
use App\Support\Track;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class MarkController extends Controller
{
    use Guards;

    public function store(Request $r, LedgerService $ledger)
    {
        $u = $this->mustBe($r, 'collector');
        $data = $r->validate([
            'saver_id' => 'required|uuid',
            'mark_date' => 'nullable|date_format:Y-m-d',
            'amount_naira' => 'nullable|numeric|min:1|max:1000000',
            'channel' => 'nullable|in:cash,transfer',
            'idempotency_key' => 'nullable|string|max:64',
        ]);

        $s = Saver::where('public_id', $data['saver_id'])
            ->whereHas('collector', fn ($q) => $q->where('user_id', $u->id))->firstOrFail();

        $date = Carbon::parse($data['mark_date'] ?? today()->toDateString())->startOfDay();
        abort_if($date->gt(today()), 422, 'You cannot mark a future day.');
        abort_if($date->lt($s->cycleStart()), 422, 'That day is before this saver started.');
        abort_if($date->lt(today()->subDays(7)), 422, 'Marks older than 7 days need an admin.');

        $amount = isset($data['amount_naira']) ? Pay::toKobo($data['amount_naira']) : $s->daily_kobo;

        try {
            $mark = $ledger->record($s, $date, $amount, $data['channel'] ?? 'cash', $u->id, $data['idempotency_key'] ?? null);
        } catch (\DomainException $e) {
            abort(422, $e->getMessage());
        }
        Track::event('mark_recorded', $u->id, ['amount_kobo' => $amount, 'channel' => $mark->channel], $r);

        $saved = (int) $s->marks()->whereBetween('mark_date', [$s->cycleStart()->toDateString(), $s->cycleEnd()->toDateString()])->sum('amount_kobo');

        return response()->json([
            'mark' => ['date' => $date->toDateString(), 'amount_kobo' => $amount, 'ref' => substr($mark->hash, 0, 12)],
            'receipt' => sprintf('Markd: %s paid N%s for %s. Saved this cycle: N%s. Ref %s',
                $s->name, Pay::naira($amount), $date->format('d M'), Pay::naira($saved), strtoupper(substr($mark->hash, 0, 8))),
        ], 201);
    }
}
