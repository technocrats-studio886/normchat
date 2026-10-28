<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aksi pesan: hapus untuk semua (dengan jejak siapa yang menghapus), hapus untuk saya,
 * pemberitahuan Normi yang hanya untuk penanya, pesan terusan, versi jawaban Normi saat
 * pertanyaan diedit, dan permintaan jawab ulang yang mengganti jawaban lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('visible_to_user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('forwarded_from_name', 120)->nullable();
            $table->unsignedBigInteger('forwarded_from_message_id')->nullable();
        });

        Schema::table('message_versions', function (Blueprint $table) {
            $table->json('answer_snapshot')->nullable();
        });

        Schema::create('message_hides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['user_id', 'message_id']);
        });

        Schema::table('chat_message_queues', function (Blueprint $table) {
            $table->unsignedBigInteger('replace_message_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('chat_message_queues', fn (Blueprint $table) => $table->dropColumn('replace_message_id'));
        Schema::dropIfExists('message_hides');
        Schema::table('message_versions', fn (Blueprint $table) => $table->dropColumn('answer_snapshot'));
        Schema::table('messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deleted_by');
            $table->dropConstrainedForeignId('visible_to_user_id');
            $table->dropColumn(['forwarded_from_name', 'forwarded_from_message_id']);
        });
    }
};
