<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupDraft;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Draf pesan per grup (docs/rancangan-alur-chat-wa.md): disimpan otomatis dari kolom ketik, sinkron antar
 * perangkat, berlaku 24 jam. Isi kosong menghapus draf. Juga menerima POST dari navigator.sendBeacon.
 */
class GroupDraftController extends Controller
{
    public function update(Request $request, Group $group): JsonResponse
    {
        $this->authorize('chat', $group);
        $validated = $request->validate([
            'content' => ['nullable', 'string', 'max:' . GroupDraft::MAX_LENGTH],
            'reply_to_message_id' => ['nullable', 'integer'],
        ]);
        $content = (string) ($validated['content'] ?? '');
        $key = ['group_id' => $group->id, 'user_id' => $request->user()->id];

        if (trim($content) === '') {
            GroupDraft::where($key)->delete();

            return response()->json(['ok' => true, 'draft' => null]);
        }

        $replyTo = (int) ($validated['reply_to_message_id'] ?? 0);
        $draft = GroupDraft::updateOrCreate($key, [
            'content' => $content,
            'reply_to_message_id' => $replyTo > 0 && $group->messages()->whereKey($replyTo)->exists() ? $replyTo : null,
        ]);
        $draft->touch();

        return response()->json(['ok' => true, 'draft' => ['content' => $draft->content, 'updated_at' => $draft->updated_at?->toIso8601String()]]);
    }
}
