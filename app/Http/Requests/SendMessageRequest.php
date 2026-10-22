<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class SendMessageRequest extends FormRequest
{
    private const ALLOWED_EXTENSIONS = 'jpg,jpeg,png,webp,gif,heic,heif,mp3,wav,ogg,webm,mp4,aac,m4a,pdf,txt,csv,doc,docx,xls,xlsx,ppt,pptx,zip,rar,7z';

    public function rules(): array
    {
        return [
            'content' => ['nullable', 'string', 'max:3000'],
            'attachment' => ['nullable', 'file', 'max:15360', 'mimes:' . self::ALLOWED_EXTENSIONS],
            'reply_to_message_id' => ['nullable', 'integer'],
            'reply_quote_text' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            if ($this->content() === '' && ! $this->hasFile('attachment')) {
                $validator->errors()->add('content', 'Kirim teks, gambar, atau voice note.');
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
}
