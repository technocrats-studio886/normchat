<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->string('share_id', 8)->unique()->nullable()->after('id');
        });

        // Backfill existing groups with a share_id
        foreach (\App\Models\Group::withTrashed()->whereNull('share_id')->cursor() as $group) {
            $group->update(['share_id' => $this->generateUniqueShareId()]);
        }
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn('share_id');
        });
    }

    private function generateUniqueShareId(): string
    {
        do {
            $id = strtoupper(Str::random(6));
        } while (\App\Models\Group::withTrashed()->where('share_id', $id)->exists());

        return $id;
    }
};
