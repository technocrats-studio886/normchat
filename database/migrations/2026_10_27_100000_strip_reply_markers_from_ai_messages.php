<?php

use App\Services\Ai\GroupAiAssistant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Bersihkan penanda "[reply]" yang sempat ditiru Normi AI di akhir jawabannya. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('messages')
            ->where('sender_type', 'ai')
            ->where('content', 'ilike', '%[reply%')
            ->orderBy('id')
            ->each(function (object $message) {
                DB::table('messages')->where('id', $message->id)
                    ->update(['content' => GroupAiAssistant::withoutReplyMarkers($message->content)]);
            });
    }

    public function down(): void
    {
        // Penanda yang dibuang tidak dikembalikan.
    }
};
