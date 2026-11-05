<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\Guards;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class AskController extends Controller
{
    use Guards;

    /** Forwards to the AI service when one is configured. Without it, answers politely and logs the question. */
    public function ask(Request $r)
    {
        $u = $this->mustBe($r, 'saver', 'collector', 'admin');
        $q = $r->validate(['question' => 'required|string|min:3|max:500'])['question'];

        $started = microtime(true);
        $answer = null;
        $sources = [];
        $url = rtrim((string) config('markd.ai_url'), '/');
        if ($url !== '') {
            try {
                $res = Http::timeout(25)->acceptJson()->post($url . '/ask', ['question' => $q]);
                if ($res->successful()) {
                    $answer = $res->json('answer');
                    $sources = $res->json('sources') ?? [];
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        DB::table('ai_queries')->insert([
            'user_id' => $u->id, 'question' => $q, 'answer' => $answer, 'sources' => json_encode($sources),
            'latency_ms' => (int) ((microtime(true) - $started) * 1000), 'ok' => $answer !== null, 'created_at' => now(),
        ]);

        return ['answer' => $answer ?? 'The assistant is not available yet. Please ask your collector.', 'sources' => $sources];
    }
}
