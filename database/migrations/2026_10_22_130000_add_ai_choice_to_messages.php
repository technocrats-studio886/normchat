<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pilihan AI per pesan dari composer: model katalog (ai_models.model_id) dan mode
 * jawaban (App\Enums\AiMode). Kosong = model grup, mode chat biasa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('ai_model', 100)->nullable();
            $table->string('ai_mode', 32)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('messages', fn (Blueprint $table) => $table->dropColumn(['ai_model', 'ai_mode']));
    }
};
