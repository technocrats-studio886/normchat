<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Normkredit berpindah dari "1 NK = 2.500 token" ke "1 NK = Rp100" dengan
 * potongan berbasis biaya OpenAI asli. Saldo lama dikonversi berdasarkan nilai
 * rupiahnya: 1 NK lama (2.500 token ≈ Rp2.500) = 25 NK baru, jadi token / 100.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_models', function (Blueprint $table) {
            $table->id();
            $table->string('model_id')->unique();
            $table->string('label');
            $table->string('description')->nullable();
            $table->decimal('input_usd_per_mtok', 12, 6);
            $table->decimal('cached_input_usd_per_mtok', 12, 6)->nullable();
            $table->decimal('output_usd_per_mtok', 12, 6);
            $table->decimal('premium_multiplier', 6, 3)->default(1);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        DB::table('ai_models')->insert([
            [
                'model_id' => 'gpt-6-luna',
                'label' => 'Fast',
                'description' => 'Paling hemat Normkredit, cocok untuk tanya-jawab ringan.',
                'input_usd_per_mtok' => 0.10,
                'cached_input_usd_per_mtok' => 0.01,
                'output_usd_per_mtok' => 0.50,
                'premium_multiplier' => 1.0,
                'is_active' => true,
                'is_default' => false,
                'sort_order' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'model_id' => 'gpt-5.4-mini',
                'label' => 'Balanced',
                'description' => 'Seimbang antara kualitas jawaban dan pemakaian Normkredit.',
                'input_usd_per_mtok' => 0.75,
                'cached_input_usd_per_mtok' => 0.075,
                'output_usd_per_mtok' => 4.50,
                'premium_multiplier' => 1.2,
                'is_active' => true,
                'is_default' => true,
                'sort_order' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'model_id' => 'gpt-6.1-sol',
                'label' => 'Advanced',
                'description' => 'Untuk tugas berat. Pemakaian Normkredit lebih tinggi.',
                'input_usd_per_mtok' => 2.00,
                'cached_input_usd_per_mtok' => 0.10,
                'output_usd_per_mtok' => 10.00,
                'premium_multiplier' => 1.5,
                'is_active' => true,
                'is_default' => false,
                'sort_order' => 3,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        Schema::create('app_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::table('group_tokens', function (Blueprint $table) {
            $table->decimal('total_credits', 14, 4)->default(0);
            $table->decimal('used_credits', 14, 4)->default(0);
            $table->decimal('remaining_credits', 14, 4)->default(0);
        });
        DB::table('group_tokens')->update([
            'total_credits' => DB::raw('total_tokens / 100.0'),
            'used_credits' => DB::raw('used_tokens / 100.0'),
            'remaining_credits' => DB::raw('remaining_tokens / 100.0'),
        ]);
        Schema::table('group_tokens', function (Blueprint $table) {
            $table->dropColumn(['total_tokens', 'used_tokens', 'remaining_tokens']);
        });

        Schema::table('group_token_contributions', function (Blueprint $table) {
            $table->decimal('credit_amount', 14, 4)->default(0);
        });
        DB::table('group_token_contributions')->update([
            'credit_amount' => DB::raw('token_amount / 100.0'),
        ]);
        Schema::table('group_token_contributions', function (Blueprint $table) {
            $table->dropColumn('token_amount');
        });

        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('request_message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->foreignId('response_message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 20); // chat, document, image
            $table->string('model');
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('cached_input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->json('breakdown_json')->nullable();
            $table->decimal('provider_cost_usd', 14, 8)->default(0);
            $table->decimal('usd_to_idr', 10, 2);
            $table->decimal('provider_cost_idr', 14, 4)->default(0);
            $table->decimal('base_multiplier', 6, 3);
            $table->decimal('premium_multiplier', 6, 3);
            $table->decimal('charged_credits', 14, 4)->default(0);
            $table->decimal('balance_after', 14, 4)->default(0);
            $table->timestamps();

            $table->index(['group_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');

        Schema::table('group_token_contributions', function (Blueprint $table) {
            $table->bigInteger('token_amount')->default(0);
        });
        DB::table('group_token_contributions')->update([
            'token_amount' => DB::raw('CAST(credit_amount * 100 AS BIGINT)'),
        ]);
        Schema::table('group_token_contributions', function (Blueprint $table) {
            $table->dropColumn('credit_amount');
        });

        Schema::table('group_tokens', function (Blueprint $table) {
            $table->bigInteger('total_tokens')->default(0);
            $table->bigInteger('used_tokens')->default(0);
            $table->bigInteger('remaining_tokens')->default(0);
        });
        DB::table('group_tokens')->update([
            'total_tokens' => DB::raw('CAST(total_credits * 100 AS BIGINT)'),
            'used_tokens' => DB::raw('CAST(used_credits * 100 AS BIGINT)'),
            'remaining_tokens' => DB::raw('CAST(remaining_credits * 100 AS BIGINT)'),
        ]);
        Schema::table('group_tokens', function (Blueprint $table) {
            $table->dropColumn(['total_credits', 'used_credits', 'remaining_credits']);
        });

        Schema::dropIfExists('app_settings');
        Schema::dropIfExists('ai_models');
    }
};
