<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Kategori 1 (Cepat, gpt-6-luna) turun dari ×2 ke ×1,5. Smart tetap ×2,5, Advanced tetap ×3. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('ai_models')->where('model_id', 'gpt-6-luna')->update(['multiplier' => 1.5, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('ai_models')->where('model_id', 'gpt-6-luna')->update(['multiplier' => 2.0, 'updated_at' => now()]);
    }
};
