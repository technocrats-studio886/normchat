<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Login tidak lagi memakai "remember me": sesi habis setelah 5 hari tanpa aktivitas
 * (App\Http\Middleware\EndIdleSession). Token lama dibatalkan agar cookie remember yang
 * masih tersimpan di browser tidak bisa membuka sesi tanpa batas.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->whereNotNull('remember_token')->update(['remember_token' => null]);
    }

    public function down(): void
    {
        // Token yang dibatalkan tidak bisa dikembalikan.
    }
};
