<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Dokumen kanonik hasil konversi lampiran (docs/rancangan-worker-dan-file-nov.md bagian D). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->unique()->constrained('messages')->cascadeOnDelete();
            $table->foreignId('group_id')->index()->constrained('groups')->cascadeOnDelete();
            $table->string('status', 16)->default('pending')->index();
            $table->string('converter', 24)->nullable();
            $table->string('ext', 16)->nullable();
            $table->string('version', 16)->nullable();
            $table->unsignedInteger('page_count')->nullable();
            $table->unsignedInteger('chars')->default(0);
            $table->unsignedInteger('tokens')->default(0);
            $table->json('outline')->nullable();
            $table->string('storage_disk', 40)->nullable();
            $table->string('storage_path')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('error', 255)->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_documents');
    }
};
