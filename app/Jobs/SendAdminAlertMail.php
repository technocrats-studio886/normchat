<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

/** Email peringatan ke alamat NORMCHAT_SUPERADMIN_EMAILS, lewat antrean agar tidak menunda request. */
class SendAdminAlertMail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public string $subject, public string $body)
    {
    }

    public function handle(): void
    {
        $recipients = (array) config('normchat.superadmin_emails', []);
        if ($recipients === []) {
            return;
        }

        Mail::raw($this->body . "\n\n" . route('superadmin.openai'), function ($mail) use ($recipients) {
            $mail->to($recipients)->subject('[Normi Chat] ' . $this->subject);
        });
    }
}
