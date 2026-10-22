<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('total_tokens')->default(0);
            $table->bigInteger('used_tokens')->default(0);
            $table->bigInteger('remaining_tokens')->default(0);
            $table->timestamps();

            $table->unique('group_id');
        });

        // Track individual token contributions (from subscription or top-up)
        Schema::create('group_token_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source'); // 'subscription', 'topup'
            $table->bigInteger('token_amount');
            $table->integer('price_paid')->default(0);
            $table->string('payment_reference')->nullable();
            $table->timestamps();

            $table->index(['group_id', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_token_contributions');
        Schema::dropIfExists('group_tokens');
    }
};
