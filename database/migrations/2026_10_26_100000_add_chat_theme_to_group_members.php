<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tema chat pilihan anggota untuk grup ini (App\Support\ChatThemes), ikut ke semua perangkatnya.
 * Kosong = tema default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_members', function (Blueprint $table) {
            $table->string('chat_theme', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('group_members', fn (Blueprint $table) => $table->dropColumn('chat_theme'));
    }
};
