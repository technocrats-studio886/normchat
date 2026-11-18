<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->boolean('created_free')->default(false);
        });
        // Sebelum perubahan ini, hanya grup gratis yang memiliki slot tanpa patungan.
        DB::table('groups')->where('free_member_slots', '>', 0)->update(['created_free' => true]);
        DB::table('app_settings')->updateOrInsert(['key' => 'free_groups_enabled'], ['value' => '0']);
    }

    public function down(): void
    {
        Schema::table('groups', fn (Blueprint $table) => $table->dropColumn('created_free'));
        // Tidak menghidupkan kembali pemberian saldo gratis secara otomatis.
    }
};
