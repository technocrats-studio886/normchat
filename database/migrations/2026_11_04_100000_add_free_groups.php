<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Grup gratis (docs/rancangan-grup-gratis-login.md): slot anggota gratis per grup, dikunci saat grup dibuat.
 * Bonus 20 NK buat grup dicabut; pengaturannya di app_settings dihapus (riwayat bonus di ledger tetap).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            // 0 = grup berbayar: semua yang gabung ikut patungan.
            $table->unsignedSmallInteger('free_member_slots')->default(0);
            // Slot gratis yang sudah terpakai; tidak kembali walau anggotanya keluar.
            $table->unsignedSmallInteger('free_joins_used')->default(0);
        });

        DB::table('app_settings')->whereIn('key', [
            'group_bonus_enabled', 'group_bonus_credits', 'group_bonus_starts_at', 'group_bonus_ends_at',
        ])->delete();
        // Cache harga (App\Services\NormkreditPricing) masih memuat kunci bonus lama.
        Cache::forget('normchat:pricing-settings');
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn(['free_member_slots', 'free_joins_used']);
        });
    }
};
