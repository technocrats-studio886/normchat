<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('access_token_encrypted')->nullable()->after('provider_user_id');
            $table->text('refresh_token_encrypted')->nullable()->after('access_token_encrypted');
            $table->timestamp('token_expires_at')->nullable()->after('refresh_token_encrypted');
            $table->string('api_key_encrypted')->nullable()->after('token_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'access_token_encrypted',
                'refresh_token_encrypted',
                'token_expires_at',
                'api_key_encrypted',
            ]);
        });
    }
};
