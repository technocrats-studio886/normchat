<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_message_queues', function (Blueprint $table) {
            $table->unsignedInteger('attempt')->default(0);
            $table->boolean('retry_allowed')->default(false);
            $table->string('activity', 32)->nullable();
        });
        Schema::table('credit_holds', function (Blueprint $table) {
            $table->unsignedBigInteger('request_message_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('credit_holds', fn (Blueprint $table) => $table->dropColumn('request_message_id'));
        Schema::table('chat_message_queues', fn (Blueprint $table) => $table->dropColumn(['attempt', 'retry_allowed', 'activity']));
    }
};
