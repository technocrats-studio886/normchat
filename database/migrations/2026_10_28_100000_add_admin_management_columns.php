<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manajemen superadmin: user yang diblokir, akun yang dihapus (soft delete, bisa dipulihkan),
 * dan alasan penyesuaian Normkredit oleh admin (kontribusi bersumber "admin_adjustment").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('blocked_at')->nullable();
            $table->softDeletes();
        });
        Schema::table('group_token_contributions', fn (Blueprint $table) => $table->text('note')->nullable());
    }

    public function down(): void
    {
        Schema::table('group_token_contributions', fn (Blueprint $table) => $table->dropColumn('note'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['blocked_at', 'deleted_at']));
    }
};
