<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Infrastruktur llm-rules (docs/rancangan-llm-rules.md):
 * - harga berversi, kurs harian, buku besar NC
 * - anggaran saldo OpenAI (SpendGuard), rekonsiliasi harian, peringatan superadmin
 * - ingatan Normi: ringkasan grup, ringkasan topik, embedding pesan (pgvector), indeks full-text pesan
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::ensureVectorExtensionExists();

        Schema::create('pricing_versions', function (Blueprint $table) {
            $table->id();
            $table->timestamp('effective_from');
            $table->json('settings');
            $table->json('models');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->decimal('market_rate', 12, 2);
            $table->decimal('card_margin_percent', 6, 3);
            $table->decimal('applied_rate', 12, 2);
            $table->foreignId('set_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('credit_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20); // opening, deposit, hold, settle, release, admin_adjust
            $table->decimal('amount', 14, 4);
            $table->decimal('balance_before', 14, 4);
            $table->decimal('balance_after', 14, 4);
            $table->decimal('reserved_before', 14, 4);
            $table->decimal('reserved_after', 14, 4);
            $table->foreignId('credit_hold_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ai_usage_log_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contribution_id')->nullable()->constrained('group_token_contributions')->nullOnDelete();
            $table->string('admin_action', 40)->nullable();
            $table->foreignId('pricing_version_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('exchange_rate_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['group_id', 'id']);
        });

        Schema::table('credit_holds', function (Blueprint $table) {
            $table->foreignId('pricing_version_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('exchange_rate_id')->nullable()->constrained()->nullOnDelete();
            $table->index(['status', 'created_at']);
        });

        Schema::table('ai_usage_logs', function (Blueprint $table) {
            $table->string('kind', 40)->change();
            $table->string('provider_request_id')->nullable();
            $table->decimal('balance_before', 14, 4)->nullable();
            $table->decimal('cache_hit_percent', 5, 2)->nullable();
            $table->foreignId('pricing_version_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('exchange_rate_id')->nullable()->constrained()->nullOnDelete();
            $table->index(['kind', 'created_at']);
        });

        // Satu baris: anggaran bulanan, saldo prabayar OpenAI yang diisi superadmin, dan biaya yang sedang ditahan.
        Schema::create('ai_spend_guard', function (Blueprint $table) {
            $table->id();
            $table->decimal('monthly_budget_usd', 14, 4)->nullable();
            // Waktu anggaran terakhir diubah: bagian dari key peringatan, supaya bisa muncul lagi setelah diubah.
            $table->timestamp('budget_set_at')->nullable();
            $table->decimal('balance_usd', 14, 4)->nullable();
            $table->decimal('reserve_usd', 14, 4)->default(0);
            $table->timestamp('balance_set_at')->nullable();
            $table->foreignId('balance_set_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('spent_since_set_usd', 16, 8)->default(0);
            $table->decimal('reserved_usd', 16, 8)->default(0);
            $table->timestamps();
        });

        DB::table('ai_spend_guard')->insert(['created_at' => now(), 'updated_at' => now()]);

        Schema::create('ai_spend_periods', function (Blueprint $table) {
            $table->id();
            $table->string('period', 7)->unique(); // 2026-11 (WIB)
            $table->decimal('spent_usd', 16, 8)->default(0);
            $table->timestamps();
        });

        Schema::create('ai_spend_holds', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 40);
            $table->foreignId('group_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('credit_hold_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('reserved_usd', 16, 8);
            $table->decimal('actual_usd', 16, 8)->nullable();
            $table->string('status', 12); // held, settled, released
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('ai_cost_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique(); // hari UTC, sama dengan bucket OpenAI
            $table->decimal('computed_usd', 16, 8);
            $table->decimal('openai_usd', 16, 8);
            $table->decimal('diff_usd', 16, 8);
            // Koreksi yang sudah diterapkan ke SpendGuard (bisa < diff_usd selama data OpenAI belum lengkap).
            $table->decimal('applied_usd', 16, 8)->default(0);
            $table->decimal('diff_percent', 8, 2)->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
        });

        Schema::create('admin_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // mis. budget:2026-11:85 — satu peringatan per kejadian
            $table->string('level', 10); // warning, danger
            $table->string('title');
            $table->text('body');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_request_refusals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason', 30); // credits, model_locked, daily_limit, spend_guard, no_model
            $table->string('model')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['created_at', 'reason']);
        });

        // Ingatan Normi per grup: ringkasan grup + status pemeliharaan.
        Schema::create('group_context_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('summary')->nullable();
            $table->unsignedBigInteger('covered_until_message_id')->nullable();
            $table->unsignedBigInteger('window_start_message_id')->nullable();
            $table->unsignedInteger('source_message_count')->default(0);
            $table->string('model')->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->decimal('provider_cost_usd', 14, 8)->default(0);
            $table->unsignedBigInteger('embedded_until_message_id')->nullable();
            $table->timestamp('stale_at')->nullable();
            $table->timestamp('rebuild_requested_at')->nullable();
            $table->timestamps();
        });

        Schema::create('group_context_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('summary');
            $table->unsignedBigInteger('first_message_id');
            $table->unsignedBigInteger('last_message_id');
            $table->json('key_message_ids')->nullable();
            $table->vector('embedding', 1536)->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('stale_at')->nullable();
            $table->timestamps();

            $table->index(['group_id', 'last_message_id']);
        });

        Schema::create('message_embeddings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->vector('embedding', 1536);
            $table->string('model');
            $table->string('content_hash', 64);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['group_id', 'message_id']);
        });
        Schema::table('message_embeddings', fn (Blueprint $table) => $table->vectorIndex('embedding'));

        DB::statement("CREATE INDEX messages_content_fulltext ON messages USING gin (to_tsvector('simple', coalesce(content, '')))");

        // Saldo yang sudah ada sebelum ledger dimulai: satu baris pembuka per grup. amount = total dana
        // (tersedia + ditahan), sama dengan konvensi ledger, supaya total ledger = saldo tersedia + ditahan.
        DB::table('group_tokens')
            ->where(fn ($query) => $query->where('remaining_credits', '>', 0)->orWhere('reserved_credits', '>', 0))
            ->orderBy('group_id')
            ->get(['group_id', 'remaining_credits', 'reserved_credits'])
            ->each(fn ($token) => DB::table('credit_ledger')->insert([
                'group_id' => $token->group_id,
                'type' => 'opening',
                'amount' => round($token->remaining_credits + $token->reserved_credits, 4),
                'balance_before' => 0,
                'balance_after' => $token->remaining_credits,
                'reserved_before' => 0,
                'reserved_after' => $token->reserved_credits,
                'created_at' => now(),
            ]));
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS messages_content_fulltext');
        Schema::dropIfExists('message_embeddings');
        Schema::dropIfExists('group_context_topics');
        Schema::dropIfExists('group_context_summaries');
        Schema::dropIfExists('ai_request_refusals');
        Schema::dropIfExists('admin_alerts');
        Schema::dropIfExists('ai_cost_reconciliations');
        Schema::dropIfExists('ai_spend_holds');
        Schema::dropIfExists('ai_spend_periods');
        Schema::dropIfExists('ai_spend_guard');

        Schema::table('ai_usage_logs', function (Blueprint $table) {
            $table->dropIndex(['kind', 'created_at']);
            $table->dropConstrainedForeignId('exchange_rate_id');
            $table->dropConstrainedForeignId('pricing_version_id');
            $table->dropColumn(['provider_request_id', 'balance_before', 'cache_hit_percent']);
        });
        Schema::table('credit_holds', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
            $table->dropConstrainedForeignId('exchange_rate_id');
            $table->dropConstrainedForeignId('pricing_version_id');
        });

        Schema::dropIfExists('credit_ledger');
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('pricing_versions');
    }
};
