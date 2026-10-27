<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tema tidak lagi per grup: satu tema per user (App\Support\Themes) untuk seluruh aplikasi.
 * Pilihan yang sudah tersimpan di group_members.chat_theme dibawa ke akun pemiliknya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('theme', 20)->nullable();
        });

        DB::table('group_members')
            ->whereNotNull('chat_theme')
            ->orderByDesc('updated_at')
            ->get(['user_id', 'chat_theme'])
            ->unique('user_id')
            ->each(fn ($member) => DB::table('users')->where('id', $member->user_id)->update(['theme' => $member->chat_theme]));

        Schema::table('group_members', fn (Blueprint $table) => $table->dropColumn('chat_theme'));
    }

    public function down(): void
    {
        Schema::table('group_members', function (Blueprint $table) {
            $table->string('chat_theme', 20)->nullable();
        });
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('theme'));
    }
};
