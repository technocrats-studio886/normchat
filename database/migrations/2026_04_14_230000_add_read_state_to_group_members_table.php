<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_members', function (Blueprint $table) {
            $table->foreignId('last_read_message_id')
                ->nullable()
                ->after('joined_at')
                ->constrained('messages')
                ->nullOnDelete();
            $table->timestamp('last_read_at')->nullable()->after('last_read_message_id');
            $table->index(['group_id', 'user_id', 'last_read_message_id'], 'group_members_read_state_idx');
        });
    }

    public function down(): void
    {
        Schema::table('group_members', function (Blueprint $table) {
            $table->dropIndex('group_members_read_state_idx');
            $table->dropConstrainedForeignId('last_read_message_id');
            $table->dropColumn('last_read_at');
        });
    }
};
