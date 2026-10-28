<?php

namespace App\Http\Controllers;

use App\Services\NormkreditPricing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

class MarketingController extends Controller
{
    private const CONTACT_TOPICS = [
        'id' => [
            'Pertanyaan Umum',
            'Harga dan Pembayaran',
            'Integrasi AI',
            'Grup dan Akses Anggota',
            'Dukungan Teknis',
            'Lainnya',
        ],
        'en' => [
            'General Question',
            'Pricing and Payments',
            'AI Integration',
            'Groups and Member Access',
            'Technical Support',
            'Other',
        ],
    ];

    private const CONTACT_ATTRIBUTES = [
        'id' => ['name' => 'nama lengkap', 'email' => 'alamat email', 'topic' => 'topik pertanyaan', 'message' => 'pesan'],
        'en' => ['name' => 'full name', 'email' => 'email address', 'topic' => 'topic', 'message' => 'message'],
    ];

    public function about(Request $request): View
    {
        $lang = $this->lang($request);

        return view("marketing.{$lang}.about", ['lang' => $lang]);
    }

    public function pricing(Request $request, NormkreditPricing $pricing): View
    {
        $lang = $this->lang($request);

        return view("marketing.{$lang}.pricing", [
            'lang' => $lang,
            'groupQuote' => $pricing->quoteGroupCreation(),
            'packageQuote' => $pricing->quotePackages(1),
            'maxPackages' => $pricing->maxPackagesPerPayment(),
            'rupiahPerCredit' => $pricing->rupiahPerCredit(),
            'feeLabel' => $pricing->feePercentLabel(),
        ]);
    }

    public function contact(Request $request): View
    {
        $lang = $this->lang($request);
        $contactEmail = $this->contactEmail();

        return view("marketing.{$lang}.contact", [
            'lang' => $lang,
            'contactEmail' => $contactEmail,
            'formEnabled' => $contactEmail !== '',
            'topics' => self::CONTACT_TOPICS[$lang],
        ]);
    }

    public function sendContact(Request $request): RedirectResponse
    {
        $lang = $this->lang($request);
        $to = $this->contactEmail();
        if ($to === '') {
            return back()->with('contact_status', 'failed');
        }

        // Kolom jebakan untuk bot: manusia tidak melihatnya, jadi pura-pura berhasil.
        if ($request->filled('website')) {
            return back()->with('contact_status', 'sent');
        }

        app()->setLocale($lang);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160'],
            'topic' => ['required', 'in:'.implode(',', self::CONTACT_TOPICS[$lang])],
            'message' => ['required', 'string', 'min:10', 'max:4000'],
        ], [], self::CONTACT_ATTRIBUTES[$lang]);

        $body = "Nama : {$data['name']}\nEmail: {$data['email']}\nTopik: {$data['topic']}\n\n{$data['message']}\n";

        try {
            Mail::raw($body, function ($mail) use ($to, $data) {
                $mail->to($to)
                    ->replyTo($data['email'], $data['name'])
                    ->subject('[Normi Chat] '.$data['topic'].' — '.$data['name']);
            });
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('contact_status', 'failed');
        }

        return redirect(route($lang === 'en' ? 'marketing.en.contact' : 'marketing.contact').'#form')->with('contact_status', 'sent');
    }

    /** Bahasa halaman, ditentukan oleh route (default 'id', '/en' → 'en'). */
    private function lang(Request $request): string
    {
        return $request->route('lang', 'id') === 'en' ? 'en' : 'id';
    }

    private function contactEmail(): string
    {
        return trim((string) config('normchat.contact_email', ''));
    }
}
