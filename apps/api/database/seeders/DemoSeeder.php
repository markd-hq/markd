<?php

namespace Database\Seeders;

use App\Models\Collector;
use App\Models\Saver;
use App\Models\User;
use App\Services\LedgerService;
use App\Support\Pay;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

// LOCAL DEMO DATA ONLY. These logins are public on purpose; never seed them in production.
class DemoSeeder extends Seeder
{
    public function run(LedgerService $ledger): void
    {
        $make = fn (array $a) => User::create($a + [
            'public_id' => (string) Str::uuid(), 'referral_code' => User::makeCode(), 'email_verified_at' => now(),
        ]);

        if (User::where('email', 'admin@markd.test')->exists()) {
            return; // already seeded
        }

        $make(['email' => 'admin@markd.test', 'password' => 'Password123!', 'name' => 'Markd Admin', 'role' => 'admin']);
        $boss = $make(['email' => 'mama@markd.test', 'password' => 'Password123!', 'name' => 'Mama Ngozi', 'role' => 'collector']);
        $saverUser = $make(['email' => 'amaka@markd.test', 'password' => 'Password123!', 'name' => 'Amaka Eze', 'role' => 'saver']);

        $collector = Collector::create([
            'user_id' => $boss->id, 'business_name' => 'Ngozi Daily Thrift', 'market_name' => 'Onitsha Main Market',
            'lga' => 'Onitsha North', 'status' => 'verified', 'verified_at' => now(),
        ]);

        $people = [
            ['Chinedu Okafor', 500], ['Amaka Eze', 1000], ['Ifeanyi Nwosu', 2000], ['Blessing Obi', 1000],
            ['Emeka Umeh', 3000], ['Ngozi Anyanwu', 500], ['Tunde Adeyemi', 2000], ['Zainab Bello', 5000],
            ['Kelechi Okonkwo', 1000], ['Ada Nnamdi', 2000], ['Musa Ibrahim', 500], ['Funke Balogun', 3000],
        ];

        mt_srand(7); // the same demo data every time
        foreach ($people as [$name, $naira]) {
            $s = Saver::create([
                'public_id' => (string) Str::uuid(), 'collector_id' => $collector->id, 'name' => $name,
                'daily_kobo' => Pay::toKobo($naira), 'cycle_days' => 30, 'started_on' => today()->subDays(24)->toDateString(),
                'claim_code' => Saver::makeClaimCode(),
            ]);
            if ($name === 'Amaka Eze') {
                $s->forceFill(['user_id' => $saverUser->id, 'claimed_at' => now()])->save(); // already linked for the demo
            }
            for ($d = 24; $d >= 1; $d--) {            // leave today empty so the dashboard has work to do
                if (mt_rand(1, 100) <= 88) {
                    $ledger->record($s, today()->subDays($d), $s->daily_kobo, mt_rand(1, 100) <= 85 ? 'cash' : 'transfer', $boss->id);
                }
            }
        }
    }
}
