<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Web Push (docs/rancangan-perbaikan-fix-bug-nov.md): satu baris per perangkat/browser yang mengizinkan notifikasi,
 * dan bisu notifikasi per grup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Endpoint layanan push browser (FCM, Mozilla, Apple); unik per perangkat.
            $table->string('endpoint', 1000);
            $table->string('endpoint_hash', 64)->unique();
            $table->string('public_key', 200);
            $table->string('auth_token', 100);
            $table->string('device', 120)->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->unsignedSmallInteger('failures')->default(0);
            $table->timestamps();
            $table->index('user_id');
        });

        Schema::table('group_members', function (Blueprint $table) {
            // Notifikasi grup ini dibisukan sampai waktu ini (tahun 2100 = selamanya).
            $table->timestamp('muted_until')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
        Schema::table('group_members', fn (Blueprint $table) => $table->dropColumn('muted_until'));
    }
};
