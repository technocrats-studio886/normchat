@extends('layouts.app', ['title' => ($mail['subject'] ?? 'Detail Pesan') . ' - Normchat'])

@section('content')
    <section class="px-4 pb-6 pt-5">
        <a href="{{ route('mailbox.inbox') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-slate-500 transition hover:text-slate-700">
            <span aria-hidden="true">&larr;</span> Kembali
        </a>

        @if($error)
            <div class="mt-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {{ $error }}
            </div>
        @elseif($mail)
            @php
                $from = $mail['from'] ?? $mail['sender'] ?? $mail['from_name'] ?? '-';
                $to = $mail['to'] ?? $mail['recipient'] ?? $mail['to_name'] ?? '-';
                $subject = $mail['subject'] ?? '(Tanpa subjek)';
                $body = $mail['body'] ?? $mail['content'] ?? '';
                $date = $mail['created_at'] ?? $mail['sent_at'] ?? $mail['date'] ?? '';
                $mailId = $mail['id'] ?? $mail['mail_id'] ?? '';
            @endphp

            <div class="mt-4 rounded-2xl border border-[#dbe6ff] bg-white px-4 py-4 shadow-sm">
                <h1 class="text-lg font-bold text-slate-900">{{ $subject }}</h1>

                <div class="mt-3 space-y-1.5 border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2 text-xs">
                        <span class="font-semibold text-slate-500 w-12">Dari</span>
                        <span class="text-slate-800">{{ is_array($from) ? ($from['name'] ?? $from['email'] ?? json_encode($from)) : $from }}</span>
                    </div>
                    <div class="flex items-center gap-2 text-xs">
                        <span class="font-semibold text-slate-500 w-12">Kepada</span>
                        <span class="text-slate-800">{{ is_array($to) ? ($to['name'] ?? $to['email'] ?? json_encode($to)) : $to }}</span>
                    </div>
                    <div class="flex items-center gap-2 text-xs">
                        <span class="font-semibold text-slate-500 w-12">Tanggal</span>
                        <span class="text-slate-600">
                            @if($date)
                                {{ \Carbon\Carbon::parse($date)->timezone(config('app.display_timezone', config('app.timezone')))->translatedFormat('d M Y, H:i') }}
                            @else
                                -
                            @endif
                        </span>
                    </div>
                </div>

                <div class="mt-3 prose prose-sm prose-slate max-w-none text-sm text-slate-800 leading-relaxed">
                    {!! $body !!}
                </div>
            </div>

            <div class="mt-3 flex gap-2">
                <a href="{{ route('mailbox.compose', ['reply_to' => is_array($from) ? ($from['email'] ?? '') : $from, 'subject' => 'Re: ' . $subject]) }}" class="btn-cta flex-1 text-center">
                    Balas
                </a>
                <form method="POST" action="{{ route('mailbox.destroy', $mailId) }}" onsubmit="return confirm('Hapus pesan ini?')" class="flex-shrink-0">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="flex h-11 w-11 items-center justify-center rounded-2xl border border-rose-200 bg-rose-50 text-rose-500 transition hover:bg-rose-100">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                        </svg>
                    </button>
                </form>
            </div>
        @else
            <div class="mt-8 text-center">
                <p class="text-sm text-slate-500">Pesan tidak ditemukan.</p>
            </div>
        @endif
    </section>
@endsection
