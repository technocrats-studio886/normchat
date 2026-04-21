<?php

namespace App\Http\Controllers;

use App\Services\InterdotzService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MailboxController extends Controller
{
    private const TOKEN_EXPIRED_MSG = 'Sesi Interdotz kamu sudah kedaluwarsa. Silakan logout dan login kembali untuk memperbarui sesi.';

    public function __construct(private InterdotzService $interdotz)
    {
    }

    private function getAccessToken(): ?string
    {
        return Auth::user()->getAccessToken();
    }

    private function getInterdotzUserId(): ?string
    {
        $user = Auth::user();

        return $user->interdotz_id ?? $user->provider_user_id ?? null;
    }

    private function resolveError(): string
    {
        $raw = $this->interdotz->getLastError() ?? '';
        $lower = strtolower($raw);

        if (str_contains($lower, 'expired') || str_contains($lower, 'authentication required') || str_contains($lower, 'token')) {
            return self::TOKEN_EXPIRED_MSG;
        }

        return $raw !== '' ? $raw : 'Gagal memuat data mailbox.';
    }

    public function inbox(Request $request): View
    {
        $token = $this->getAccessToken();
        $mails = [];
        $error = null;

        if ($token) {
            $result = $this->interdotz->getMailboxInbox($token, $this->getInterdotzUserId());
            if ($result) {
                $payload = $result['payload'] ?? $result['data'] ?? $result;
                $mails = $payload['items'] ?? $payload['mails'] ?? (is_array($payload) && array_is_list($payload) ? $payload : []);
            } else {
                $error = $this->resolveError();
            }
        } else {
            $error = self::TOKEN_EXPIRED_MSG;
        }

        return view('mailbox.inbox', [
            'mails' => is_array($mails) ? $mails : [],
            'error' => $error,
            'tab' => 'inbox',
        ]);
    }

    public function sent(Request $request): View
    {
        $token = $this->getAccessToken();
        $mails = [];
        $error = null;

        if ($token) {
            $result = $this->interdotz->getMailboxSent($token, $this->getInterdotzUserId());
            if ($result) {
                $payload = $result['payload'] ?? $result['data'] ?? $result;
                $mails = $payload['items'] ?? $payload['mails'] ?? (is_array($payload) && array_is_list($payload) ? $payload : []);
            } else {
                $error = $this->resolveError();
            }
        } else {
            $error = self::TOKEN_EXPIRED_MSG;
        }

        return view('mailbox.inbox', [
            'mails' => is_array($mails) ? $mails : [],
            'error' => $error,
            'tab' => 'sent',
        ]);
    }

    public function show(string $mailId): View
    {
        $token = $this->getAccessToken();
        $mail = null;
        $error = null;

        if ($token) {
            $result = $this->interdotz->getMailDetail($mailId, $token, $this->getInterdotzUserId());
            if ($result) {
                $mail = $result['payload'] ?? $result['data'] ?? $result;
                $this->interdotz->markMailAsRead($mailId, $token, $this->getInterdotzUserId());
            } else {
                $sent = $this->interdotz->getMailboxSent($token, $this->getInterdotzUserId());
                if ($sent) {
                    $payload = $sent['payload'] ?? $sent['data'] ?? $sent;
                    $items = $payload['items'] ?? $payload['mails'] ?? (is_array($payload) && array_is_list($payload) ? $payload : []);
                    foreach ($items as $item) {
                        if (($item['id'] ?? null) === $mailId) {
                            $mail = $item;
                            break;
                        }
                    }
                }
                if (! $mail) {
                    $error = $this->resolveError();
                }
            }
        } else {
            $error = self::TOKEN_EXPIRED_MSG;
        }

        return view('mailbox.show', [
            'mail' => $mail,
            'error' => $error,
            'currentUser' => Auth::user(),
        ]);
    }

    public function compose(): View
    {
        return view('mailbox.compose', [
            'user' => Auth::user(),
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'to' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ]);

        $token = $this->getAccessToken();

        if (! $token) {
            return redirect()->route('mailbox.compose')
                ->with('error', self::TOKEN_EXPIRED_MSG);
        }

        $result = $this->interdotz->sendMail([
            'recipient_email' => trim($validated['to']),
            'subject' => trim($validated['subject']),
            'body' => $validated['body'],
        ], $token, $this->getInterdotzUserId());

        if ($result) {
            return redirect()->route('mailbox.sent')
                ->with('success', 'Pesan berhasil dikirim.');
        }

        return redirect()->route('mailbox.compose')
            ->withInput()
            ->with('error', $this->resolveError());
    }

    public function markAllRead(): RedirectResponse
    {
        $token = $this->getAccessToken();

        if ($token) {
            $this->interdotz->markAllMailAsRead($token, $this->getInterdotzUserId());
        }

        return redirect()->route('mailbox.inbox')
            ->with('success', 'Semua pesan ditandai sudah dibaca.');
    }

    public function destroy(string $mailId): RedirectResponse
    {
        $token = $this->getAccessToken();

        if ($token) {
            $result = $this->interdotz->deleteMail($mailId, $token, $this->getInterdotzUserId());
            if ($result) {
                return redirect()->route('mailbox.inbox')
                    ->with('success', 'Pesan berhasil dihapus.');
            }
        }

        return redirect()->route('mailbox.inbox')
            ->with('error', $this->resolveError());
    }
}
