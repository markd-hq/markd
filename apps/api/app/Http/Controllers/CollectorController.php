<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\Guards;
use App\Models\Collector;
use App\Models\Mark;
use App\Support\SaverHealth;
use App\Support\Track;
use Illuminate\Http\Request;

class CollectorController extends Controller
{
    use Guards;

    public function store(Request $r)
    {
        $u = $this->mustBe($r, 'collector');
        $data = $r->validate([
            'business_name' => 'required|string|max:120',
            'market_name' => 'nullable|string|max:120',
            'lga' => 'nullable|string|max:60',
        ]);
        $collector = Collector::firstOrCreate(['user_id' => $u->id], $data + ['status' => 'pending']);
        Track::event('collector_profile', $u->id, [], $r);

        return response()->json($collector, 201);
    }

    /** The collector's daily dashboard: who has been marked today and who has not. */
    public function me(Request $r)
    {
        $u = $this->mustBe($r, 'collector');
        $col = $u->collector;
        abort_unless($col, 404, 'Create your collector profile first.');

        $today = today()->toDateString();
        $todayMarks = Mark::where('collector_id', $col->id)->whereDate('mark_date', $today);
        $markedIds = (clone $todayMarks)->pluck('saver_id');

        return [
            'collector' => $col,
            'savers' => $col->savers()->where('status', 'active')->count(),
            'today' => [
                'date' => $today,
                'marked' => $markedIds->count(),
                'collected_kobo' => (int) (clone $todayMarks)->sum('amount_kobo'),
                'not_yet_marked' => $col->savers()->where('status', 'active')->whereNotIn('id', $markedIds)
                    ->get(['public_id', 'name', 'daily_kobo']),
            ],
        ];
    }

    /** Savers who are behind, worst first: the collector's daily follow-up list. */
    public function attention(Request $r)
    {
        $u = $this->mustBe($r, 'collector');
        $col = $u->collector;
        abort_unless($col, 404, 'Create your collector profile first.');

        $rank = ['defaulted' => 0, 'at_risk' => 1, 'late' => 2];
        $rows = [];
        foreach ($col->savers()->where('status', 'active')->get() as $s) {
            $dates = $s->marks()->whereBetween('mark_date', [$s->cycleStart()->toDateString(), $s->cycleEnd()->toDateString()])
                ->get(['mark_date'])->map(fn ($m) => $m->mark_date->toDateString())->all();
            $h = SaverHealth::assess($s->started_on->toDateString(), $s->cycle_days, $dates, today()->toDateString(), (int) config('markd.default_after_days'));
            if (isset($rank[$h['status']])) {
                $rows[] = ['id' => $s->public_id, 'name' => $s->name, 'daily_naira' => \App\Support\Pay::naira($s->daily_kobo)] + $h;
            }
        }
        usort($rows, fn ($a, $b) => [$rank[$a['status']], -$a['consecutive_missed']] <=> [$rank[$b['status']], -$b['consecutive_missed']]);

        return $rows;
    }
}
