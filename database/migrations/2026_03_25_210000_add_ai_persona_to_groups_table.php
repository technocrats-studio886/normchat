<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->text('ai_persona_style')->nullable()->after('ai_model');
            $table->text('ai_persona_guardrails')->nullable()->after('ai_persona_style');
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn(['ai_persona_style', 'ai_persona_guardrails']);
        });
    }
};
