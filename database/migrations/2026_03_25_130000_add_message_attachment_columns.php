<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE messages ALTER COLUMN content DROP NOT NULL");

        Schema::table('messages', function ($table) {
            $table->string('message_type')->default('text')->after('sender_id');
            $table->string('attachment_disk')->nullable()->after('content');
            $table->string('attachment_path')->nullable()->after('attachment_disk');
            $table->string('attachment_mime')->nullable()->after('attachment_path');
            $table->string('attachment_original_name')->nullable()->after('attachment_mime');
            $table->unsignedBigInteger('attachment_size')->nullable()->after('attachment_original_name');
            $table->index(['group_id', 'message_type']);
        });
    }

    public function down(): void
    {
        DB::statement("UPDATE messages SET content = '' WHERE content IS NULL");

        Schema::table('messages', function ($table) {
            $table->dropIndex('messages_group_id_message_type_index');
            $table->dropColumn([
                'message_type',
                'attachment_disk',
                'attachment_path',
                'attachment_mime',
                'attachment_original_name',
                'attachment_size',
            ]);
        });

        DB::statement("ALTER TABLE messages ALTER COLUMN content SET NOT NULL");
    }
};
