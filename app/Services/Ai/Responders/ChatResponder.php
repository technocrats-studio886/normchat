<?php

namespace App\Services\Ai\Responders;

use App\Services\Ai\AiReply;
use App\Services\Ai\AiTask;
use App\Services\Ai\PromptBuilder;

final class ChatResponder implements AiResponder
{
    public function respond(AiTask $task): AiReply
    {
        $messages = $task->prompts->conversation(
            PromptBuilder::chatSystemPrompt($task->group),
            $task->recentMessages,
            $task->message
        );

        $task->planChat($messages);
        [$completion, $model] = $task->complete($messages);

        if (! $completion->hasText()) {
            return AiReply::failed('chat');
        }

        $task->recordChat('chat', $model, $completion);
        $reply = AiReply::text('chat', (string) $completion->text, $model);

        return $task->isOutputCapped() && $completion->finishReason === 'length'
            ? $reply->withNote('Jawaban dibatasi karena kredit yang bisa kamu pakai tidak cukup untuk jawaban lebih panjang.')
            : $reply;
    }
}
