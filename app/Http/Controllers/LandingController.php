<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

/** Situs pemasaran berbahasa Inggris (/home, /about, /pricing, /contact); normi.chat sendiri membuka halaman masuk. */
class LandingController extends Controller
{
    public const CONTACT_TOPICS = [
        'General question',
        'Prices and payment',
        'Groups and members',
        'Normi and AI models',
        'Technical problem',
        self::BUSINESS_TOPIC,
        'Other',
    ];

    /** Topik yang dikirim ke kotak masuk kerja sama bisnis (normchat.business_email), bukan dukungan. */
    public const BUSINESS_TOPIC = 'Business partnership';

    public function home(): View
    {
        return view('marketing.home');
    }

    public function terms(): View
    {
        return view('marketing.terms');
    }

    public function privacy(): View
    {
        return view('marketing.privacy');
    }

    public function about(): View
    {
        return view('marketing.about');
    }

    public function pricing(): View
    {
        return view('marketing.pricing');
    }

    public function contact(): View
    {
        return view('marketing.contact', ['topics' => self::CONTACT_TOPICS]);
    }

    public function sendContact(Request $request): RedirectResponse
    {
        // Kolom jebakan: bot mengisinya, orang sungguhan tidak melihatnya.
        if (filled($request->input('website'))) {
            return redirect()->route('contact')->with('contact_status', 'sent');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:190'],
            'topic' => ['required', 'in:'.implode(',', self::CONTACT_TOPICS)],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
        ]);

        $to = $data['topic'] === self::BUSINESS_TOPIC
            ? (config('normchat.business_email') ?: config('normchat.support_email'))
            : config('normchat.support_email');
        $body = "From: {$data['name']} <{$data['email']}>\nTopic: {$data['topic']}\n\n{$data['message']}";

        if (! $to) {
            Log::info('Contact form (NORMCHAT_SUPPORT_EMAIL not set)', $data);

            return redirect()->route('contact')->with('contact_status', 'sent');
        }

        try {
            Mail::raw($body, function ($mail) use ($to, $data) {
                $mail->to($to)
                    ->replyTo($data['email'], $data['name'])
                    ->subject("[Normi Chat contact] {$data['topic']}");
            });
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('contact')->withInput()->with('contact_status', 'failed');
        }

        return redirect()->route('contact')->with('contact_status', 'sent');
    }
}
