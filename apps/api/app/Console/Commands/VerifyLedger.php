<?php

namespace App\Console\Commands;

use App\Models\Collector;
use App\Models\Mark;
use App\Services\LedgerService;
use Illuminate\Console\Command;

class VerifyLedger extends Command
{
    protected $signature = 'markd:verify {collector? : collector id (default: all)}';
    protected $description = 'Recompute the mark hash chain and report any tampering';

    public function handle(): int
    {
        $ids = $this->argument('collector') ? [(int) $this->argument('collector')] : Collector::pluck('id')->all();
        $broken = 0;

        foreach ($ids as $id) {
            $prev = null;
            $n = 0;
            foreach (Mark::where('collector_id', $id)->orderBy('id')->cursor() as $m) {
                $expected = LedgerService::hash($prev, $m->collector_id, $m->saver_id, $m->mark_date->toDateString(), $m->amount_kobo, LedgerService::stamp($m->created_at));
                if ($m->prev_hash !== $prev || $m->hash !== $expected) {
                    $this->error("Collector {$id}: chain BROKEN at mark #{$m->id}");
                    $broken++;
                    continue 2;
                }
                $prev = $m->hash;
                $n++;
            }
            $this->info("Collector {$id}: {$n} marks verified");
        }

        return $broken ? self::FAILURE : self::SUCCESS;
    }
}
