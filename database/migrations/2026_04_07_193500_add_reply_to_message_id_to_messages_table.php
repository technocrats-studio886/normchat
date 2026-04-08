<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('reply_to_message_id')
                ->nullable()
                ->after('sender_id')
                ->constrained('messages')
                ->nullOnDelete();

            $table->index(['group_id', 'reply_to_message_id']);
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_group_id_reply_to_message_id_index');
            $table->dropConstrainedForeignId('reply_to_message_id');
        });
    }
};
