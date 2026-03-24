<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('payment_type')->default('add_seat_dummy');
            $table->string('reference')->unique();
            $table->unsignedInteger('seat_count')->default(0);
            $table->unsignedInteger('unit_price')->default(0);
            $table->unsignedInteger('total_amount')->default(0);
            $table->string('status')->default('paid');
            $table->json('metadata_json')->nullable();
            $table->timestamps();

            $table->index(['group_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
    }
};
