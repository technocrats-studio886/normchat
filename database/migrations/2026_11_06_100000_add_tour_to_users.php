<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tur pengenalan (docs/rancangan-tur-dan-blok-kode.md): kapan tur terakhir dilihat, dan kapan user terakhir
 * aktif (baris user_sessions dihapus saat keluar, jadi aktivitas terakhir disimpan juga di users).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('tour_seen_at')->nullable();
            $table->timestamp('last_active_at')->nullable();
        });
        DB::statement('UPDATE users SET last_active_at = (SELECT max(us.last_active_at) FROM user_sessions us WHERE us.user_id = users.id)');
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['tour_seen_at', 'last_active_at']));
    }
};
