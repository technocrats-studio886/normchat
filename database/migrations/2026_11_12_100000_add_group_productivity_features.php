<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fitur produktivitas grup (docs/rancangan-fitur-produktivitas-nov.md): pengingat, pesan terjadwal, tugas bersama,
 * sematan, indeks dokumen grup, dan rekap mingguan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('source_message_id')->nullable();
            $table->string('text', 500);
            $table->timestamp('remind_at');
            // none, daily, weekly
            $table->string('repeat', 10)->default('none');
            // group = pesan untuk semua anggota; me = hanya untuk pembuatnya
            $table->string('audience', 10)->default('group');
            // pending, sent, cancelled, failed
            $table->string('status', 12)->default('pending');
            $table->unsignedBigInteger('last_message_id')->nullable();
            $table->unsignedInteger('sent_count')->default(0);
            $table->timestamps();
            $table->index(['status', 'remind_at']);
            $table->index(['group_id', 'status']);
        });

        Schema::create('scheduled_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->unsignedBigInteger('reply_to_message_id')->nullable();
            $table->string('ai_mode', 30)->nullable();
            $table->string('ai_model', 80)->nullable();
            $table->timestamp('send_at');
            // pending, sent, cancelled, failed
            $table->string('status', 12)->default('pending');
            $table->unsignedBigInteger('sent_message_id')->nullable();
            $table->string('error', 200)->nullable();
            $table->timestamps();
            $table->index(['status', 'send_at']);
            $table->index(['group_id', 'user_id', 'status']);
        });

        Schema::create('group_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->string('title', 300);
            $table->unsignedBigInteger('source_message_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['group_id', 'done_at']);
        });

        Schema::create('group_pins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('message_id');
            $table->foreignId('pinned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['group_id', 'message_id']);
        });

        Schema::create('document_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('message_id');
            $table->unsignedSmallInteger('chunk_index');
            $table->text('content');
            $table->vector('embedding', 1536)->nullable();
            $table->timestamps();
            $table->index(['group_id', 'message_id']);
        });
        Schema::table('document_chunks', fn (Blueprint $table) => $table->vectorIndex('embedding'));
        DB::statement("CREATE INDEX document_chunks_content_fulltext ON document_chunks USING gin (to_tsvector('simple', content))");

        Schema::table('messages', function (Blueprint $table) {
            // Dokumen lampiran sudah diindeks ke document_chunks (berhasil atau tidak bisa dibaca).
            $table->timestamp('doc_indexed_at')->nullable();
        });

        Schema::table('groups', function (Blueprint $table) {
            $table->boolean('weekly_recap_enabled')->default(false);
            // ISO: 1 = Senin … 7 = Minggu
            $table->unsignedTinyInteger('weekly_recap_day')->default(1);
            $table->unsignedTinyInteger('weekly_recap_hour')->default(7);
            $table->timestamp('weekly_recap_last_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('groups', fn (Blueprint $table) => $table->dropColumn(['weekly_recap_enabled', 'weekly_recap_day', 'weekly_recap_hour', 'weekly_recap_last_at']));
        Schema::table('messages', fn (Blueprint $table) => $table->dropColumn('doc_indexed_at'));
        Schema::dropIfExists('document_chunks');
        Schema::dropIfExists('group_pins');
        Schema::dropIfExists('group_tasks');
        Schema::dropIfExists('scheduled_messages');
        Schema::dropIfExists('reminders');
    }
};
