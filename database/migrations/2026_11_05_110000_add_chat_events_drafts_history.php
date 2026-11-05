<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/rancangan-alur-chat-wa.md:
 * - messages.system_event/system_meta: pemberitahuan di tengah chat (bergabung, keluar, ganti nama, dll.).
 * - group_members.history_from_message_id: anggota baru hanya melihat pesan sesudah id ini (null = seluruh riwayat).
 * - group_drafts: draf pesan per user per grup, sinkron antar perangkat, berlaku 24 jam.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('system_event', 40)->nullable()->after('message_type');
            $table->json('system_meta')->nullable()->after('system_event');
        });
        Schema::table('group_members', function (Blueprint $table) {
            $table->unsignedBigInteger('history_from_message_id')->nullable()->after('last_read_at');
        });
        Schema::create('group_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->unsignedBigInteger('reply_to_message_id')->nullable();
            $table->timestamps();
            $table->unique(['group_id', 'user_id']);
            $table->index(['user_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_drafts');
        Schema::table('group_members', fn (Blueprint $table) => $table->dropColumn('history_from_message_id'));
        Schema::table('messages', fn (Blueprint $table) => $table->dropColumn(['system_event', 'system_meta']));
    }
};
