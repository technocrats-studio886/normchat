<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Normkredit grup kembali menjadi satu kantong yang dipakai penuh oleh semua anggota.
 * Kredit terlindungi per orang dihapus: nilainya sudah termasuk di remaining_credits,
 * jadi cukup kolom pemecahnya yang dibuang. Batas harian per anggota tetap ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Tahanan yang masih berjalan: gabungkan kedua kantongnya sebelum kolomnya hilang.
        DB::table('credit_holds')->update(['shared_amount' => DB::raw('shared_amount + protected_amount')]);

        Schema::table('credit_holds', function (Blueprint $table) {
            $table->dropColumn(['protected_amount', 'shared_first']);
        });
        Schema::table('credit_holds', function (Blueprint $table) {
            $table->renameColumn('shared_amount', 'amount');
        });

        Schema::dropIfExists('group_member_credits');

        Schema::table('group_tokens', fn (Blueprint $table) => $table->dropColumn('shared_credits'));
        Schema::table('group_token_contributions', fn (Blueprint $table) => $table->dropColumn('shared_credit_amount'));

        Schema::table('groups', function (Blueprint $table) {
            $table->renameColumn('member_daily_shared_limit', 'member_daily_credit_limit');
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->renameColumn('member_daily_credit_limit', 'member_daily_shared_limit');
        });

        Schema::table('group_token_contributions', function (Blueprint $table) {
            $table->decimal('shared_credit_amount', 14, 4)->default(0);
        });
        DB::table('group_token_contributions')->update(['shared_credit_amount' => DB::raw('credit_amount')]);

        Schema::table('group_tokens', function (Blueprint $table) {
            $table->decimal('shared_credits', 14, 4)->default(0);
        });
        DB::table('group_tokens')->update(['shared_credits' => DB::raw('remaining_credits')]);

        Schema::create('group_member_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('protected_credits', 14, 4)->default(0);
            $table->boolean('spend_shared_first')->default(false);
            $table->timestamps();

            $table->unique(['group_id', 'user_id']);
        });

        Schema::table('credit_holds', function (Blueprint $table) {
            $table->renameColumn('amount', 'shared_amount');
        });
        Schema::table('credit_holds', function (Blueprint $table) {
            $table->decimal('protected_amount', 14, 4)->default(0);
            $table->boolean('shared_first')->default(true);
        });
    }
};
