<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Foto grup (docs/rancangan-info-grup.md): path berkas di disk normchat_avatars (S3 di produksi). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->string('avatar_path')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('groups', fn (Blueprint $table) => $table->dropColumn('avatar_path'));
    }
};
