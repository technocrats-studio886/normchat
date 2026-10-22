<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Setiap kategori AI punya mesin gambar internal. Model kategori menyusun instruksi
 * gambar, mesin gambar membuat pikselnya; user tetap hanya memilih kategori.
 * image_model_precise dipakai bila model kategori menilai tugas butuh presisi tinggi.
 */
return new class extends Migration
{
    private const ROUTING = [
        'gpt-6-luna' => ['gpt-image-2.5-flare', null],
        'gpt-6.1-sol' => ['gpt-image-2.5-flare', 'gpt-image-2.5-sunburst'],
        'gpt-6-astra' => ['gpt-image-2.5-sunburst', null],
    ];

    public function up(): void
    {
        Schema::table('ai_models', function (Blueprint $table) {
            $table->string('image_model')->default('gpt-image-2.5-flare');
            $table->string('image_model_precise')->nullable();
        });

        foreach (self::ROUTING as $modelId => [$imageModel, $precise]) {
            DB::table('ai_models')->where('model_id', $modelId)->update([
                'image_model' => $imageModel,
                'image_model_precise' => $precise,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('ai_models', fn (Blueprint $table) => $table->dropColumn(['image_model', 'image_model_precise']));
    }
};
