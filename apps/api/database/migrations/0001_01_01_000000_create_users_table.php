<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Replaces Laravel's default users migration. Works on SQLite (local) and MySQL (Hostinger).
// All money is stored in kobo (integers).
return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->string('email')->unique();                    // always stored lower-case
            $t->string('password');
            $t->string('name')->nullable();
            $t->string('phone', 20)->nullable();              // optional, NOT verified, never used to link accounts
            $t->string('role', 20)->default('saver');         // saver | collector | admin
            $t->string('language', 5)->default('en');         // en | pcm | yo | ig | ha
            $t->string('referral_code', 12)->unique();
            $t->foreignId('referred_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('wallet_address', 80)->nullable()->unique(); // Sui address, set only after a signed challenge
            $t->timestamp('wallet_verified_at')->nullable();
            $t->timestamp('email_verified_at')->nullable();
            $t->unsignedTinyInteger('failed_logins')->default(0);
            $t->timestamp('locked_until')->nullable();
            $t->timestamp('last_seen_at')->nullable();
            $t->timestamps();
            $t->index('role');
        });

        Schema::create('auth_challenges', function (Blueprint $t) {   // one-time messages a wallet must sign
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('nonce', 40)->unique();
            $t->text('message');
            $t->timestamp('expires_at');
            $t->timestamp('used_at')->nullable();
            $t->timestamps();
        });

        Schema::create('collectors', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('business_name');
            $t->string('market_name')->nullable();
            $t->string('state', 40)->default('Anambra');
            $t->string('lga', 60)->nullable();
            $t->string('status', 20)->default('pending');     // pending | verified | suspended
            $t->timestamp('verified_at')->nullable();
            $t->unsignedSmallInteger('commission_days')->default(1); // tradition: one day's contribution per cycle
            $t->bigInteger('bond_kobo')->default(0);
            $t->string('bond_status', 20)->default('none');   // none | pending | active | slashed
            $t->string('bond_object_id')->nullable();         // Sui object, later
            $t->timestamps();
        });

        Schema::create('savers', function (Blueprint $t) {  // one row per person saving with one collector
            $t->id();
            $t->uuid('public_id')->unique();
            $t->foreignId('collector_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name');
            $t->string('phone', 20)->nullable();
            $t->bigInteger('daily_kobo');
            $t->unsignedSmallInteger('cycle_days')->default(30);
            $t->date('started_on');
            $t->string('status', 20)->default('active');     // active | completed | paused | left
            $t->string('claim_code', 12)->unique();           // the saver uses this to link their own login
            $t->timestamp('claimed_at')->nullable();
            $t->timestamps();
            $t->index(['collector_id', 'status']);
        });

        Schema::create('marks', function (Blueprint $t) {   // the ledger: append-only, hash-chained per collector
            $t->id();
            $t->foreignId('saver_id')->constrained()->cascadeOnDelete();
            $t->foreignId('collector_id')->constrained()->cascadeOnDelete();
            $t->date('mark_date');
            $t->bigInteger('amount_kobo');
            $t->string('channel', 12)->default('cash');      // cash | transfer
            $t->string('idempotency_key', 64)->nullable()->unique();
            $t->foreignId('recorded_by')->constrained('users');
            $t->string('prev_hash', 64)->nullable();
            $t->string('hash', 64);
            $t->dateTime('created_at');                       // plain datetime: no timezone conversion, so hashes stay verifiable
            $t->dateTime('updated_at')->nullable();
            $t->unique(['saver_id', 'mark_date']);
            $t->index(['collector_id', 'mark_date']);
        });

        Schema::create('payouts', function (Blueprint $t) {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->foreignId('saver_id')->constrained()->cascadeOnDelete();
            $t->foreignId('collector_id')->constrained()->cascadeOnDelete();
            $t->date('cycle_start');
            $t->date('cycle_end');
            $t->unsignedSmallInteger('days_marked');
            $t->bigInteger('gross_kobo');
            $t->bigInteger('commission_kobo');
            $t->bigInteger('platform_fee_kobo');
            $t->bigInteger('net_kobo');
            $t->string('status', 20)->default('requested');  // requested | approved | paid | failed
            $t->string('bank_code', 10)->nullable();
            $t->text('account_number')->nullable();           // stored encrypted
            $t->string('account_name')->nullable();
            $t->string('partner_reference')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
            $t->unique(['saver_id', 'cycle_start']);
            $t->index('status');
        });

        Schema::create('referrals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('referred_id')->unique()->constrained('users')->cascadeOnDelete();
            $t->string('status', 20)->default('pending');    // pending | qualified | paid | rejected
            $t->timestamp('qualified_at')->nullable();
            $t->bigInteger('reward_kobo')->default(0);
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
        });

        Schema::create('events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('type', 40);
            $t->json('meta')->nullable();
            $t->string('ip_hash', 64)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['type', 'created_at']);
        });

        Schema::create('ai_queries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->text('question');
            $t->text('answer')->nullable();
            $t->json('sources')->nullable();
            $t->unsignedInteger('latency_ms')->nullable();
            $t->boolean('ok')->default(true);
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::create('messages', function (Blueprint $t) {  // collector's group chat with their savers
            $t->id();
            $t->foreignId('collector_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->text('body');
            $t->timestamp('created_at')->useCurrent();
            $t->index(['collector_id', 'created_at']);
        });

        Schema::create('anchors', function (Blueprint $t) {   // daily fingerprint of a ledger, posted to Sui later
            $t->id();
            $t->foreignId('collector_id')->constrained()->cascadeOnDelete();
            $t->date('anchor_date');
            $t->string('root_hash', 64);
            $t->unsignedInteger('marks_count');
            $t->string('chain_digest')->nullable();
            $t->timestamp('anchored_at')->nullable();
            $t->timestamps();
            $t->unique(['collector_id', 'anchor_date']);
        });
    }

    public function down(): void
    {
        foreach (['anchors', 'messages', 'ai_queries', 'events', 'referrals', 'payouts', 'marks', 'savers', 'collectors', 'auth_challenges', 'users'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
