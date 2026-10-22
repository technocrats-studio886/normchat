<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - Multiplier kini satu angka efektif per model (Cepat ×2, Smart ×2,5, Advanced ×3),
 *   bukan base ×2 global dikali premium. Nilai lama dikonversi: multiplier = 2 × premium.
 * - Base multiplier global hanya tersisa untuk media (gambar & transkripsi): media_multiplier.
 * - Harga beralih ke paket (buat grup 250 NK, patungan/top-up kelipatan 125 NK); minimum lama dibuang.
 */
return new class extends Migration
{
    private const LEGACY_BASE_MULTIPLIER = 2.0;

    private const CATALOG = [
        'gpt-6-luna' => [
            'label' => 'Cepat',
            'description' => 'Untuk pertanyaan sederhana, ringkasan, dan brainstorming ringan.',
            'input_usd_per_mtok' => 0.10,
            'cached_input_usd_per_mtok' => 0.01,
            'output_usd_per_mtok' => 0.50,
            'multiplier' => 2.0,
            'is_default' => false,
            'sort_order' => 1,
        ],
        'gpt-6.1-sol' => [
            'label' => 'Smart',
            'description' => 'Untuk diskusi kelompok, penjelasan materi, penulisan, dan analisis umum.',
            'input_usd_per_mtok' => 2.00,
            'cached_input_usd_per_mtok' => 0.10,
            'output_usd_per_mtok' => 10.00,
            'multiplier' => 2.5,
            'is_default' => true,
            'sort_order' => 2,
        ],
        'gpt-6-astra' => [
            'label' => 'Advanced',
            'description' => 'Untuk coding, penalaran lebih rumit, dan tugas bertahap.',
            'input_usd_per_mtok' => 10.00,
            'cached_input_usd_per_mtok' => 1.00,
            'output_usd_per_mtok' => 50.00,
            'multiplier' => 3.0,
            'is_default' => false,
            'sort_order' => 3,
        ],
    ];

    public function up(): void
    {
        Schema::table('ai_models', fn (Blueprint $table) => $table->renameColumn('premium_multiplier', 'multiplier'));
        DB::table('ai_models')->update(['multiplier' => DB::raw('multiplier * ' . self::LEGACY_BASE_MULTIPLIER)]);

        DB::table('ai_models')->update(['is_active' => false, 'is_default' => false]);
        foreach (self::CATALOG as $modelId => $attributes) {
            DB::table('ai_models')->updateOrInsert(
                ['model_id' => $modelId],
                $attributes + ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        Schema::table('ai_usage_logs', fn (Blueprint $table) => $table->decimal('multiplier', 6, 3)->default(1));
        DB::table('ai_usage_logs')->update(['multiplier' => DB::raw('base_multiplier * premium_multiplier')]);
        Schema::table('ai_usage_logs', fn (Blueprint $table) => $table->dropColumn(['base_multiplier', 'premium_multiplier']));

        DB::table('app_settings')->where('key', 'base_multiplier')->update(['key' => 'media_multiplier']);
        DB::table('app_settings')->whereIn('key', ['min_creator_credits', 'min_member_credits', 'min_topup_credits'])->delete();
    }

    public function down(): void
    {
        DB::table('app_settings')->where('key', 'media_multiplier')->update(['key' => 'base_multiplier']);

        Schema::table('ai_usage_logs', function (Blueprint $table) {
            $table->decimal('base_multiplier', 6, 3)->default(self::LEGACY_BASE_MULTIPLIER);
            $table->decimal('premium_multiplier', 6, 3)->default(1);
        });
        DB::table('ai_usage_logs')->update(['premium_multiplier' => DB::raw('multiplier / ' . self::LEGACY_BASE_MULTIPLIER)]);
        Schema::table('ai_usage_logs', fn (Blueprint $table) => $table->dropColumn('multiplier'));

        DB::table('ai_models')->update(['multiplier' => DB::raw('multiplier / ' . self::LEGACY_BASE_MULTIPLIER)]);
        Schema::table('ai_models', fn (Blueprint $table) => $table->renameColumn('multiplier', 'premium_multiplier'));
    }
};
