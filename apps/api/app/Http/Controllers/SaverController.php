<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\Guards;
use App\Models\Saver;
use App\Support\Pay;
use App\Support\SaverHealth;
use App\Support\Phone;
use App\Support\Track;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SaverController extends Controller
{
    use Guards;

    public function index(Request $r)
    {
        $col = $this->mustBe($r, 'collector')->collector;
        abort_unless($col, 404, 'Create your collector profile first.');

        return $col->savers()->orderBy('name')->get()->map(fn ($s) => $this->json($s));
    }

    public function store(Request $r)
    {
        $u = $this->mustBe($r, 'collector');
        $col = $u->collector;
        abort_unless($col, 404, 'Create your collector profile first.');

        $data = $r->validate([
            'name' => 'required|string|max:80',
            'phone' => 'nullable|string|max:20',
            'daily_naira' => 'required|numeric|min:100|max:1000000',
            'started_on' => 'nullable|date_format:Y-m-d',
            'cycle_days' => 'nullable|integer|min:7|max:366',
        ]);
        $phone = ! empty($data['phone']) ? Phone::normalize($data['phone']) : null;
        abort_if(! empty($data['phone']) && ! $phone, 422, 'That phone number is not valid.');

        $s = Saver::create([
            'public_id' => (string) Str::uuid(),
            'collector_id' => $col->id,
            'name' => $data['name'],
            'phone' => $phone,
            'daily_kobo' => Pay::toKobo($data['daily_naira']),
            'cycle_days' => $data['cycle_days'] ?? 30,
            'started_on' => $data['started_on'] ?? today()->toDateString(),
            'claim_code' => Saver::makeClaimCode(),
        ]);
        Track::event('saver_added', $u->id, ['daily_kobo' => $s->daily_kobo], $r);

        return response()->json($this->json($s) + [
            'claim_code' => $s->claim_code,
            'share_message' => "Hi {$s->name}, your Markd code is {$s->claim_code}. Sign up at " . config('app.url') . ' and enter it to see your savings.',
        ], 201);
    }

    public function show(Request $r, Saver $saver)
    {
        $u = $this->mustBe($r, 'collector');
        abort_unless($saver->collector->user_id === $u->id, 403, 'Not your saver.');

        return $this->json($saver) + [
            'marks' => $saver->marks()->orderByDesc('mark_date')->limit(60)->get(['mark_date', 'amount_kobo', 'channel', 'hash']),
        ];
    }

    /** A saver links their own login to the record their collector made, using the claim code. */
    public function claim(Request $r)
    {
        $u = $this->mustBe($r, 'saver', 'collector', 'admin');
        $code = strtoupper(trim($r->validate(['claim_code' => 'required|string|max:12'])['claim_code']));

        $s = Saver::where('claim_code', $code)->whereNull('user_id')->first();
        abort_unless($s, 422, 'That code is wrong or already used.');

        $s->forceFill(['user_id' => $u->id, 'claimed_at' => now()])->save();
        Track::event('saver_claimed', $u->id, [], $r);

        return $this->json($s);
    }

    /** What a saver sees: their own savings. */
    public function mine(Request $r)
    {
        $u = $this->mustBe($r, 'saver', 'collector', 'admin');

        return Saver::where('user_id', $u->id)->with('collector:id,business_name,market_name,status')
            ->get()->map(fn ($s) => $this->json($s) + ['collector' => $s->collector]);
    }

    public function json(Saver $s): array
    {
        $window = $s->marks()->whereBetween('mark_date', [$s->cycleStart()->toDateString(), $s->cycleEnd()->toDateString()]);
        $dates = (clone $window)->get(['mark_date'])->map(fn ($m) => $m->mark_date->toDateString())->all();

        return [
            'id' => $s->public_id,
            'name' => $s->name,
            'phone' => $s->phone,
            'daily_kobo' => $s->daily_kobo,
            'daily_naira' => Pay::naira($s->daily_kobo),
            'cycle_days' => $s->cycle_days,
            'started_on' => $s->started_on->toDateString(),
            'cycle_ends_on' => $s->cycleEnd()->toDateString(),
            'status' => $s->status,
            'claimed' => $s->user_id !== null,
            'days_marked' => (clone $window)->count(),
            'saved_kobo' => (int) (clone $window)->sum('amount_kobo'),
            'health' => SaverHealth::assess($s->started_on->toDateString(), $s->cycle_days, $dates, today()->toDateString(), (int) config('markd.default_after_days')),
        ];
    }

    /** A ready-to-send reminder for a saver who is behind. The collector sends it from their own WhatsApp. */
    public function nudge(Request $r, Saver $saver)
    {
        $u = $this->mustBe($r, 'collector');
        abort_unless($saver->collector->user_id === $u->id, 403, 'Not your saver.');

        $h = $this->json($saver)['health'];
        $text = "Hi {$saver->name}, we have not marked {$h['consecutive_missed']} day(s) for your Markd savings. Please pay your collector today so you stay on track.";
        $digits = $saver->phone ? ltrim($saver->phone, '+') : null;
        Track::event('saver_nudged', $u->id, ['status' => $h['status']], $r);

        return [
            'message' => $text,
            'whatsapp_url' => $digits ? "https://wa.me/{$digits}?text=" . rawurlencode($text) : null,
        ];
    }
}
