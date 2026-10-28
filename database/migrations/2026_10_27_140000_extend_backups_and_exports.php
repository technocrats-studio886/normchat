<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cadangan: status (sedang dibuat/siap/gagal), ringkasan isi, dan cadangan otomatis tanpa pembuat.
 * Unduhan: filter yang dipakai dan jumlah pesannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_backups', function (Blueprint $table) {
            $table->string('status', 12)->default('ready');
            $table->unsignedInteger('message_count')->default(0);
            $table->unsignedInteger('attachment_count')->default(0);
            $table->unsignedBigInteger('last_message_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->change();
        });

        Schema::table('exports', function (Blueprint $table) {
            $table->json('filters')->nullable();
            $table->unsignedInteger('message_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('exports', fn (Blueprint $table) => $table->dropColumn(['filters', 'message_count']));
        Schema::table('group_backups', fn (Blueprint $table) => $table->dropColumn(['status', 'message_count', 'attachment_count', 'last_message_id']));
    }
};
