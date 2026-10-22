<?php

namespace App\Http\Requests;

use App\Enums\AiMode;
use App\Support\AttachmentRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SendMessageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'content' => ['nullable', 'string', 'max:3000'],
            'attachment' => AttachmentRules::validation(),
            'reply_to_message_id' => ['nullable', 'integer'],
            'reply_quote_text' => ['nullable', 'string', 'max:500'],
            'ai_model' => ['nullable', 'string', Rule::exists('ai_models', 'model_id')->where('is_active', true)],
            'ai_mode' => ['nullable', Rule::enum(AiMode::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'attachment.extensions' => AttachmentRules::rejectionMessage(),
            'attachment.max' => 'Ukuran file maksimal 15 MB.',
            'ai_model.exists' => 'Model ini sudah tidak tersedia. Pilih model lain.',
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            if ($this->content() === '' && ! $this->hasFile('attachment')) {
                $validator->errors()->add('content', 'Pesan masih kosong.');
            }

            $file = $this->file('attachment');
            if ($file && AttachmentRules::hasBlockedContent($file)) {
                $validator->errors()->add('attachment', AttachmentRules::rejectionMessage());
            }
        }];
    }

    public function content(): string
    {
        return trim((string) $this->input('content', ''));
    }

    public function replyQuoteText(): string
    {
        return (string) Str::of((string) $this->input('reply_quote_text', ''))->squish()->substr(0, 500);
    }

    public function aiMode(): ?AiMode
    {
        return AiMode::tryFrom((string) $this->input('ai_mode', ''));
    }

    public function aiModel(): ?string
    {
        $model = trim((string) $this->input('ai_model', ''));

        return $model !== '' ? $model : null;
    }
}
