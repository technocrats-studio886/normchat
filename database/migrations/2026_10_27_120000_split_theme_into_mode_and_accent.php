<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tema lama (default/navy/coral) diganti mode + aksen (App\Support\Themes).
 * navy = mode gelap; coral = mode terang dengan aksen bata; selebihnya ikut sistem.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('theme_mode', 10)->nullable();
            $table->string('theme_accent', 10)->nullable();
        });

        DB::table('users')->where('theme', 'navy')->update(['theme_mode' => 'dark']);
        DB::table('users')->where('theme', 'coral')->update(['theme_mode' => 'light', 'theme_accent' => 'bata']);

        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('theme'));
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->string('theme', 20)->nullable());

        DB::table('users')->where('theme_mode', 'dark')->update(['theme' => 'navy']);
        DB::table('users')->where('theme_accent', 'bata')->update(['theme' => 'coral']);

        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['theme_mode', 'theme_accent']));
    }
};
