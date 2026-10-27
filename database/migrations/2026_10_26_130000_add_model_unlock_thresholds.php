<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kategori AI yang dikunci saldo (App\Services\Credits\ModelAccess). Ambang dalam kelipatan
 * modal pembuatan grup: terbuka saat saldo ≥ unlock_multiple × modal, terkunci lagi saat saldo
 * < lock_multiple × modal. group_tokens.unlocked_models menyimpan kategori yang sedang terbuka.
 * Advanced dibuka di 2× modal dan dikunci di 1× modal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_models', function (Blueprint $table) {
            $table->decimal('unlock_multiple', 5, 2)->nullable();
            $table->decimal('lock_multiple', 5, 2)->nullable();
        });
        Schema::table('group_tokens', function (Blueprint $table) {
            $table->json('unlocked_models')->nullable();
        });

        DB::table('ai_models')->where('model_id', 'gpt-6-astra')->update(['unlock_multiple' => 2, 'lock_multiple' => 1]);
    }

    public function down(): void
    {
        Schema::table('group_tokens', fn (Blueprint $table) => $table->dropColumn('unlocked_models'));
        Schema::table('ai_models', fn (Blueprint $table) => $table->dropColumn(['unlock_multiple', 'lock_multiple']));
    }
};
