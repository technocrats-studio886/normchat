<?php

namespace App\Services\Ai\Responders;

use App\Services\Ai\AiReply;
use App\Services\Ai\AiTask;
use App\Services\Ai\ImageResult;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Pembuatan gambar dalam kategori yang dipilih grup:
 * 1. model kategori (Luna/Sol/Astra) membaca konteks dan menyusun instruksi gambar;
 * 2. mesin gambar internal kategori itu (Flare/Sunburst) membuat atau mengedit gambar.
 * Kedua biaya dicatat terpisah sesuai pemakaian nyata dan ditagih dengan multiplier kategori.
 */
final class ImageResponder implements AiResponder
{
    private const TRIGGERS = [
        'buatkan gambar', 'buat gambar', 'bikin gambar', 'generate gambar', 'generate image',
        'generate an image', 'create image', 'create an image', 'draw ', 'gambarkan', 'buatkan foto',
        'buat foto', 'bikin foto', 'tolong gambar', 'coba gambar', 'gambarin', 'bikinin gambar', 'buatin gambar',
    ];

    private const PLANNER_MAX_OUTPUT_TOKENS = 600;

    /** Perkiraan panjang instruksi gambar, untuk menahan NK sebelum instruksinya ada. */
    private const ESTIMATED_PROMPT_TOKENS = 300;

    private const PLANNER_PROMPT = "Kamu menyiapkan instruksi untuk mesin pembuat gambar di grup chat Normchat.\n"
        . "Baca permintaan terbaru beserta konteks percakapan dan gambar yang dilampirkan, lalu balas HANYA dengan JSON:\n"
        . '{"prompt": "instruksi gambar yang detail dalam bahasa Inggris (subjek, gaya, komposisi, warna, teks dalam gambar bila ada)", '
        . '"precise": false, "use_reference": false, "caption": "satu kalimat Bahasa Indonesia untuk grup"}' . "\n"
        . "precise = true bila hasil harus sangat presisi: mengedit detail gambar referensi, teks di dalam gambar, diagram, atau banyak elemen spesifik.\n"
        . 'use_reference = true bila user ingin mengedit atau memakai gambar yang dilampirkan / dibalas.';

    public static function wantsImage(string $content): bool
    {
        $content = strtolower($content);

        return collect(self::TRIGGERS)->contains(fn (string $trigger) => str_contains($content, $trigger));
    }

    public function respond(AiTask $task): AiReply
    {
        $reference = $task->prompts->referenceImage($task->message);
        $messages = $task->prompts->conversation(self::PLANNER_PROMPT, $task->recentMessages, $task->message);

        $task->reserve($this->estimateCredits($task, $messages, $reference !== null));

        $plan = $this->plan($task, $messages, $reference !== null);
        $engine = $task->model->imageEngine($plan['precise']);
        $config = self::engineConfig($engine);
        $useReference = $plan['use_reference'] && $reference !== null;

        $result = $useReference
            ? $task->client->editImage($engine, $plan['prompt'], $reference['binary'], $reference['filename'], $config['size'], $config['quality'])
            : $task->client->generateImage($engine, $plan['prompt'], $config['size'], $config['quality']);

        if ($result->blockedByContentPolicy()) {
            return AiReply::notice('image', 'Tidak bisa membuat gambar ini karena melanggar kebijakan konten (misalnya: tokoh nyata, konten sensitif). Coba dengan deskripsi lain ya!');
        }
        if (! $result->successful()) {
            return AiReply::notice('image', 'Gagal membuat gambar, coba lagi nanti.');
        }

        $this->recordEngineUsage($task, $engine, $config, $plan['prompt'], $result, $useReference);

        $path = sprintf('group-%d/%s/ai-%s.png', $task->group->id, now()->format('Y/m'), Str::uuid());
        Storage::disk('normchat_attachments')->put($path, $result->binary);

        $caption = $plan['caption'] !== '' ? $plan['caption'] : (string) $result->revisedPrompt;

        return AiReply::attachment('image', $caption !== '' ? "🎨 {$caption}" : null, [
            'message_type' => 'image',
            'path' => $path,
            'mime' => 'image/png',
            'original_name' => 'normai-generated.png',
            'size' => strlen($result->binary),
        ], $task->model);
    }

    /**
     * Instruksi gambar dari model kategori. Bila gagal, pakai teks permintaan user apa adanya.
     *
     * @return array{prompt: string, precise: bool, use_reference: bool, caption: string}
     */
    private function plan(AiTask $task, array $messages, bool $hasReference): array
    {
        [$completion, $model] = $task->complete($messages, 60, [
            'response_format' => ['type' => 'json_object'],
            'max_completion_tokens' => self::PLANNER_MAX_OUTPUT_TOKENS,
        ]);

        $plan = $completion->hasText() ? json_decode((string) $completion->text, true) : null;
        if ($completion->successful) {
            $task->recordChat('image_planner', $model, $completion);
        }

        $prompt = is_array($plan) ? trim((string) ($plan['prompt'] ?? '')) : '';
        if ($prompt === '') {
            return [
                'prompt' => self::promptFromRequest((string) $task->message->content),
                'precise' => false,
                'use_reference' => $hasReference,
                'caption' => '',
            ];
        }

        return [
            'prompt' => $prompt,
            'precise' => (bool) ($plan['precise'] ?? false),
            'use_reference' => (bool) ($plan['use_reference'] ?? $hasReference),
            'caption' => trim((string) ($plan['caption'] ?? '')),
        ];
    }

    /** NK yang ditahan: penyusun instruksi + mesin gambar termahal kategori ini. */
    private function estimateCredits(AiTask $task, array $messages, bool $hasReference): float
    {
        $model = $task->model;
        $plannerCost = $model->costUsd(AiTask::estimateInputTokens($messages), 0, self::PLANNER_MAX_OUTPUT_TOKENS);

        $engineCost = max(array_map(function (string $engine) use ($hasReference) {
            $config = self::engineConfig($engine);

            return self::engineCostUsd(
                $config,
                self::ESTIMATED_PROMPT_TOKENS,
                $hasReference ? (int) $config['fallback_image_input_tokens'] : 0,
                (int) $config['fallback_output_tokens']
            );
        }, array_unique([$model->imageEngine(false), $model->imageEngine(true)])));

        return $task->pricing->creditsForCost($plannerCost + $engineCost, $model->multiplier);
    }

    /** Pakai usage dari API bila ada; bila tidak, perkiraan dari config mesin gambar. */
    private function recordEngineUsage(AiTask $task, string $engine, array $config, string $prompt, ImageResult $result, bool $usedReference): void
    {
        $usage = $result->usage;
        $details = (array) ($usage['input_tokens_details'] ?? []);
        $textTokens = (int) ($details['text_tokens'] ?? $usage['input_tokens'] ?? ceil(mb_strlen($prompt) / 4));
        $imageTokens = (int) ($details['image_tokens'] ?? ($usedReference ? $config['fallback_image_input_tokens'] : 0));
        $outputTokens = (int) ($usage['output_tokens'] ?? $config['fallback_output_tokens']);

        $task->meter->add(
            'image',
            $engine,
            self::engineCostUsd($config, $textTokens, $imageTokens, $outputTokens),
            $task->model->multiplier,
            [
                'input_tokens' => $textTokens,
                'image_input_tokens' => $imageTokens,
                'output_tokens' => $outputTokens,
                'estimated' => $usage === null,
            ],
        );
    }

    private static function engineCostUsd(array $config, int $textTokens, int $imageTokens, int $outputTokens): float
    {
        return ($textTokens * (float) $config['text_input_usd_per_mtok']
            + $imageTokens * (float) $config['image_input_usd_per_mtok']
            + $outputTokens * (float) $config['image_output_usd_per_mtok']) / 1_000_000;
    }

    /** ID mesin mengandung titik (gpt-image-2.5-…), jadi dibaca lewat array, bukan dot-notation config(). */
    private static function engineConfig(string $engine): array
    {
        $engines = (array) config('normchat.image_models', []);

        return (array) ($engines[$engine] ?? reset($engines));
    }

    private static function promptFromRequest(string $content): string
    {
        $prompt = trim((string) preg_replace('/@\w+\s*/', '', $content));
        $prompt = trim((string) preg_replace(
            '/^(buatkan|buat|bikin|generate|create|draw|gambarkan|tolong|coba|gambarin|bikinin|buatin)\s*(gambar|foto|image|an image)?\s*/iu',
            '',
            $prompt
        ));

        return $prompt !== '' ? $prompt : 'A beautiful creative illustration';
    }
}
