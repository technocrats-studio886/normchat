<?php

namespace App\Http\Controllers;

use App\Services\InterdotzService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\View\View;

class MailboxController extends Controller
{
    public function __construct(private InterdotzService $interdotz)
    {
    }

    private function getAccessToken(): ?string
    {
        $user = Auth::user();

        return $user->getAccessToken();
    }

    private function getInterdotzUserId(): ?string
    {
        $user = Auth::user();

        return $user->interdotz_id ?? $user->provider_user_id ?? null;
    }

    public function inbox(Request $request): View
    {
        $token = $this->getAccessToken();
        $userId = $this->getInterdotzUserId();
        $mails = [];
        $error = null;

        if ($token) {
            $result = $this->interdotz->getMailboxInbox($token, $userId);
            if ($result) {
                $mails = $result['payload'] ?? $result['data'] ?? $result;
            } else {
                $error = $this->interdotz->getLastError();
            }
        } else {
            $error = 'Sesi tidak valid. Silakan login ulang.';
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
        $userId = $this->getInterdotzUserId();
        $mails = [];
        $error = null;

        if ($token) {
            $result = $this->interdotz->getMailboxSent($token, $userId);
            if ($result) {
                $mails = $result['payload'] ?? $result['data'] ?? $result;
            } else {
                $error = $this->interdotz->getLastError();
            }
        } else {
            $error = 'Sesi tidak valid. Silakan login ulang.';
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
        $userId = $this->getInterdotzUserId();
        $mail = null;
        $error = null;

        if ($token) {
            $result = $this->interdotz->getMailDetail($mailId, $token, $userId);
            if ($result) {
                $mail = $result['payload'] ?? $result['data'] ?? $result;
                // Mark as read
                $this->interdotz->markMailAsRead($mailId, $token, $userId);
            } else {
                $error = $this->interdotz->getLastError();
            }
        } else {
            $error = 'Sesi tidak valid. Silakan login ulang.';
        }

        return view('mailbox.show', [
            'mail' => $mail,
            'error' => $error,
        ]);
    }

    public function compose(): View
    {
        $user = Auth::user();

        return view('mailbox.compose', [
            'user' => $user,
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
        $userId = $this->getInterdotzUserId();

        if (! $token) {
            return redirect()->route('mailbox.compose')
                ->with('error', 'Sesi tidak valid. Silakan login ulang.');
        }

        $result = $this->interdotz->sendMail([
            'to' => trim($validated['to']),
            'subject' => trim($validated['subject']),
            'body' => $validated['body'],
        ], $token, $userId);

        if ($result) {
            return redirect()->route('mailbox.sent')
                ->with('success', 'Pesan berhasil dikirim.');
        }

        return redirect()->route('mailbox.compose')
            ->withInput()
            ->with('error', $this->interdotz->getLastError() ?? 'Gagal mengirim pesan.');
    }

    public function markAllRead(): RedirectResponse
    {
        $token = $this->getAccessToken();
        $userId = $this->getInterdotzUserId();

        if ($token) {
            $this->interdotz->markAllMailAsRead($token, $userId);
        }

        return redirect()->route('mailbox.inbox')
            ->with('success', 'Semua pesan ditandai sudah dibaca.');
    }

    public function destroy(string $mailId): RedirectResponse
    {
        $token = $this->getAccessToken();
        $userId = $this->getInterdotzUserId();

        if ($token) {
            $result = $this->interdotz->deleteMail($mailId, $token, $userId);
            if ($result) {
                return redirect()->route('mailbox.inbox')
                    ->with('success', 'Pesan berhasil dihapus.');
            }
        }

        return redirect()->route('mailbox.inbox')
            ->with('error', $this->interdotz->getLastError() ?? 'Gagal menghapus pesan.');
    }
}
