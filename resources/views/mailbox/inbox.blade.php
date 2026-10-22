@extends('layouts.app', ['title' => ($tab === 'sent' ? 'Pesan Terkirim' : 'Kotak Masuk') . ' - Normchat'])

@section('content')
    <section class="px-4 pb-6 pt-5">
        <a href="{{ route('profile.show') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-slate-500 transition hover:text-slate-700">
            <span aria-hidden="true">&larr;</span> Kembali
        </a>

        <div class="mt-3 flex items-center justify-between">
            <div>
                <h1 class="font-display text-xl font-extrabold text-slate-900">
                    {{ $tab === 'sent' ? 'Pesan Terkirim' : 'Kotak Masuk' }}
                </h1>
                <p class="mt-1 text-sm text-slate-500">Kelola pesan Interdotz kamu.</p>
            </div>
            <a href="{{ route('mailbox.compose') }}" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-blue-600 text-white shadow-md transition hover:bg-blue-700" title="Tulis Pesan">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-5 w-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                </svg>
            </a>
        </div>

        {{-- Tabs --}}
        <div class="mt-4 flex gap-1 rounded-xl bg-slate-100 p-1">
            <a href="{{ route('mailbox.inbox') }}" class="flex-1 rounded-lg px-3 py-2 text-center text-xs font-semibold transition {{ $tab === 'inbox' ? 'bg-white text-blue-600 shadow-sm' : 'text-slate-500 hover:text-slate-700' }}">
                Kotak Masuk
            </a>
            <a href="{{ route('mailbox.sent') }}" class="flex-1 rounded-lg px-3 py-2 text-center text-xs font-semibold transition {{ $tab === 'sent' ? 'bg-white text-blue-600 shadow-sm' : 'text-slate-500 hover:text-slate-700' }}">
                Terkirim
            </a>
        </div>

        @if($tab === 'inbox')
            <div class="mt-2 flex justify-end">
                <form method="POST" action="{{ route('mailbox.read-all') }}">
                    @csrf
                    @method('PUT')
                    <button type="submit" class="text-[11px] font-semibold text-blue-600 transition hover:text-blue-800">
                        Tandai semua dibaca
                    </button>
                </form>
            </div>
        @endif

        @if($error)
            <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                <p>{{ $error }}</p>
                @if(str_contains($error, 'login'))
                    <a href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('mailbox-logout-form').submit();" class="mt-2 inline-block text-xs font-bold text-blue-600 hover:text-blue-800">
                        Login Ulang
                    </a>
                    <form id="mailbox-logout-form" action="{{ route('logout') }}" method="POST" class="hidden">@csrf</form>
                @endif
            </div>
        @endif

        {{-- Mail List --}}
        <div class="mt-3 space-y-2">
            @forelse($mails as $mail)
                @php
                    $isRead = !empty($mail['read_at']) || !empty($mail['is_read']);
                    $mailId = $mail['id'] ?? $mail['mail_id'] ?? $mail['_id'] ?? $mail['mailId'] ?? $mail['message_id'] ?? $mail['messageId'] ?? '';
                    $senderUser = $mail['sender_username'] ?? null;
                    $recipientUser = $mail['recipient_username'] ?? null;
                    $from = $mail['from'] ?? $mail['sender_email'] ?? ($senderUser ? $senderUser.'@interdotz.com' : null) ?? $mail['sender'] ?? $mail['from_name'] ?? '-';
                    $to = $mail['to'] ?? $mail['recipient_email'] ?? ($recipientUser ? $recipientUser.'@interdotz.com' : null) ?? $mail['recipient'] ?? $mail['to_name'] ?? '-';
                    $subject = $mail['subject'] ?? '(Tanpa subjek)';
                    $preview = $mail['preview'] ?? $mail['excerpt'] ?? \Illuminate\Support\Str::limit(strip_tags($mail['body'] ?? ''), 80);
                    $date = $mail['created_at'] ?? $mail['sent_at'] ?? $mail['date'] ?? '';
                @endphp
                <a href="{{ $mailId !== '' ? route('mailbox.show', $mailId) : '#' }}" class="block rounded-2xl border {{ $isRead ? 'border-slate-200 bg-white' : 'border-blue-200 bg-blue-50' }} px-4 py-3 shadow-sm transition hover:bg-slate-50">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm {{ $isRead ? 'font-medium text-slate-700' : 'font-bold text-slate-900' }}">
                                {{ $tab === 'sent' ? "Ke: {$to}" : $from }}
                            </p>
                            <p class="truncate text-sm {{ $isRead ? 'text-slate-600' : 'font-semibold text-slate-800' }}">{{ $subject }}</p>
                            <p class="mt-0.5 truncate text-xs text-slate-500">{{ $preview }}</p>
                        </div>
                        <span class="shrink-0 text-[10px] text-slate-400">
                            @if($date)
                                <time datetime="{{ \Carbon\Carbon::parse($date, 'Asia/Jakarta')->toIso8601String() }}" data-mail-time="rel">{{ \Carbon\Carbon::parse($date, 'Asia/Jakarta')->diffForHumans() }}</time>
                            @endif
                        </span>
                    </div>
                    @if(!$isRead)
                        <span class="mt-1 inline-block h-2 w-2 rounded-full bg-blue-500"></span>
                    @endif
                </a>
            @empty
                <div class="mt-8 text-center">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-slate-100">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-8 w-8 text-slate-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 9v.906a2.25 2.25 0 0 1-1.183 1.981l-6.478 3.488M2.25 9v.906a2.25 2.25 0 0 0 1.183 1.981l6.478 3.488m8.839 2.51-4.66-2.51m0 0-1.023-.55a2.25 2.25 0 0 0-2.134 0l-1.022.55m0 0-4.661 2.51m16.5 1.615a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V8.844a2.25 2.25 0 0 1 1.183-1.981l7.5-4.039a2.25 2.25 0 0 1 2.134 0l7.5 4.039a2.25 2.25 0 0 1 1.183 1.98V19.5Z" />
                        </svg>
                    </div>
                    <p class="mt-3 text-sm font-semibold text-slate-600">Tidak ada pesan</p>
                    <p class="mt-1 text-xs text-slate-400">{{ $tab === 'sent' ? 'Belum ada pesan terkirim.' : 'Kotak masuk kamu kosong.' }}</p>
                </div>
            @endforelse
        </div>
    </section>

    <script>
        (function () {
            const rtf = new Intl.RelativeTimeFormat(navigator.language || 'id', { numeric: 'auto' });
            const units = [
                ['year', 31536000], ['month', 2592000], ['week', 604800],
                ['day', 86400], ['hour', 3600], ['minute', 60], ['second', 1],
            ];
            document.querySelectorAll('time[data-mail-time="rel"]').forEach((el) => {
                const t = new Date(el.getAttribute('datetime'));
                if (isNaN(t)) return;
                const diff = (t.getTime() - Date.now()) / 1000;
                for (const [unit, secs] of units) {
                    if (Math.abs(diff) >= secs || unit === 'second') {
                        el.textContent = rtf.format(Math.round(diff / secs), unit);
                        break;
                    }
                }
                el.title = t.toLocaleString();
            });
        })();
    </script>
@endsection
