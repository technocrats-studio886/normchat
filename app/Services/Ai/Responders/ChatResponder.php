<?php

namespace App\Services\Ai\Responders;

use App\Services\Ai\AiReply;
use App\Services\Ai\AiTask;
use App\Services\Ai\PromptBuilder;

/** Jawaban teks biasa; dengan $modeInstructions untuk mode seperti Guided Learning. */
final class ChatResponder implements AiResponder
{
    public function __construct(
        private readonly ?string $modeInstructions = null,
        private readonly string $kind = 'chat',
    ) {
    }

    public function respond(AiTask $task): AiReply
    {
        $systemPrompt = PromptBuilder::chatSystemPrompt($task->group);
        if ($this->modeInstructions !== null) {
            $systemPrompt .= "\n\n" . $this->modeInstructions;
        }

        $messages = $task->prompts->conversation($systemPrompt, $task->recentMessages, $task->message);

        $task->planChat($messages);
        [$completion, $model] = $task->complete($messages);

        if (! $completion->hasText()) {
            return AiReply::failed($this->kind);
        }

        $task->recordChat($this->kind, $model, $completion);
        $reply = AiReply::text($this->kind, (string) $completion->text, $model);

        return $task->isOutputCapped() && $completion->finishReason === 'length'
            ? $reply->withNote('Jawaban dipersingkat karena Normkredit tidak cukup.')
            : $reply;
    }
}
