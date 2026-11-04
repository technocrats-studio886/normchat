<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/rancangan-ux-admin-pengumuman-bonus.md:
 * - Akun > Pengaturan: zona waktu otomatis/manual, notifikasi, versi sesi (keluar dari perangkat lain).
 * - user_sessions: perangkat yang sedang masuk.
 * - announcements: pengumuman superadmin (popup/banner).
 * Promo bonus buat grup memakai app_settings; ledger jenis "bonus" memakai kolom type yang ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('timezone_mode', 10)->default('auto');
            $table->boolean('notify_mentions')->default(true);
            $table->boolean('notify_sound')->default(false);
            $table->unsignedInteger('session_version')->default(0);
        });

        Schema::create('user_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('session_hash', 64)->unique();
            $table->string('device', 120)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('location', 120)->nullable();
            $table->timestamp('last_active_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'last_active_at']);
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 80);
            $table->text('body');
            $table->string('display', 10)->default('popup'); // popup | banner
            $table->string('tone', 10)->default('info'); // info | penting
            $table->string('cta_label', 40)->nullable();
            $table->string('cta_url', 500)->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('user_sessions');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['timezone_mode', 'notify_mentions', 'notify_sound', 'session_version']));
    }
};
