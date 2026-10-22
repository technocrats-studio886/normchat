<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Saldo grup tetap satu angka yang terlihat (group_tokens.remaining_credits), tetapi
 * dipecah menjadi kredit bersama (semua anggota) dan kredit terlindungi per orang.
 * Request AI menahan estimasi NK lebih dulu (credit_holds) lalu disesuaikan dengan
 * pemakaian aktual. Saldo lama seluruhnya dianggap kredit bersama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_tokens', function (Blueprint $table) {
            $table->decimal('shared_credits', 14, 4)->default(0);
            $table->decimal('reserved_credits', 14, 4)->default(0);
        });
        DB::table('group_tokens')->update(['shared_credits' => DB::raw('remaining_credits')]);

        Schema::create('group_member_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('protected_credits', 14, 4)->default(0);
            // Default: pakai kredit terlindungi milik sendiri dulu, baru kredit bersama.
            $table->boolean('spend_shared_first')->default(false);
            $table->timestamps();

            $table->unique(['group_id', 'user_id']);
        });

        Schema::create('credit_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('shared_amount', 14, 4)->default(0);
            $table->decimal('protected_amount', 14, 4)->default(0);
            $table->boolean('shared_first');
            $table->string('status', 16)->default('held'); // held, settled, released
            $table->timestamps();

            $table->index(['group_id', 'user_id', 'created_at']);
        });

        Schema::table('groups', function (Blueprint $table) {
            $table->decimal('member_daily_shared_limit', 14, 4)->nullable();
        });

        Schema::table('group_token_contributions', function (Blueprint $table) {
            $table->decimal('shared_credit_amount', 14, 4)->default(0);
        });
        DB::table('group_token_contributions')->update(['shared_credit_amount' => DB::raw('credit_amount')]);
    }

    public function down(): void
    {
        Schema::table('group_token_contributions', fn (Blueprint $table) => $table->dropColumn('shared_credit_amount'));
        Schema::table('groups', fn (Blueprint $table) => $table->dropColumn('member_daily_shared_limit'));
        Schema::dropIfExists('credit_holds');
        Schema::dropIfExists('group_member_credits');
        Schema::table('group_tokens', fn (Blueprint $table) => $table->dropColumn(['shared_credits', 'reserved_credits']));
    }
};
