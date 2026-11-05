<?php

namespace App\Http\Controllers;

use App\Models\Mark;
use App\Services\LedgerService;
use App\Support\Pay;
use App\Support\Track;
use Illuminate\Http\Request;

class VerifyController extends Controller
{
    /** Public: anyone with a receipt reference can check the record is real and untouched. */
    public function show(Request $r, string $ref)
    {
        $ref = strtolower(preg_replace('/[^0-9a-fA-F]/', '', $ref) ?? '');
        abort_if(strlen($ref) < 8, 422, 'Enter the reference from your receipt.');

        $mark = Mark::where('hash', 'like', $ref . '%')->orderBy('id')->first();
        Track::event('verify_viewed', null, ['found' => (bool) $mark], $r);
        abort_unless($mark, 404, 'No record was found for that reference.');

        $prev = Mark::where('collector_id', $mark->collector_id)->where('id', '<', $mark->id)->orderByDesc('id')->first();
        $next = Mark::where('collector_id', $mark->collector_id)->where('id', '>', $mark->id)->orderBy('id')->first();

        $expected = LedgerService::hash($prev?->hash, $mark->collector_id, $mark->saver_id, $mark->mark_date->toDateString(), $mark->amount_kobo, LedgerService::stamp($mark->created_at));
        $intact = $mark->hash === $expected && $mark->prev_hash === $prev?->hash && (! $next || $next->prev_hash === $mark->hash);

        $saver = $mark->saver()->with('collector.user:id,name')->first();
        $initials = collect(preg_split('/\s+/', trim($saver->name)))->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->take(2)->implode('.') . '.';

        return [
            'found' => true,
            'intact' => $intact,
            'date' => $mark->mark_date->toDateString(),
            'amount_naira' => Pay::naira($mark->amount_kobo),
            'channel' => $mark->channel,
            'saver' => $initials,                                   // initials only, to protect privacy
            'collector' => $saver->collector->business_name,
            'market' => $saver->collector->market_name,
            'recorded_at' => $mark->created_at->format('Y-m-d H:i'),
            'ref' => substr($mark->hash, 0, 12),
            'fingerprint' => $mark->hash,
        ];
    }
}
