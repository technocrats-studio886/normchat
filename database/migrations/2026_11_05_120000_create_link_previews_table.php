<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Pratinjau tautan (docs/rancangan-pratinjau-tautan.md): satu baris per URL, dipakai semua grup. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('link_previews', function (Blueprint $table) {
            $table->id();
            $table->char('url_hash', 64)->unique();
            $table->text('url');
            $table->string('title', 300)->nullable();
            $table->string('site_name', 120)->nullable();
            $table->string('description', 300)->nullable();
            $table->text('image_url')->nullable();
            $table->string('status', 10)->default('pending');
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('link_previews');
    }
};
