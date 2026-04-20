@extends('layouts.app', ['title' => 'Aktivitas Akun - Normchat'])

@section('content')
    <section class="page-shell pt-5">
        <a href="{{ route('profile.show') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-slate-500 transition hover:text-slate-700">
            <span aria-hidden="true">&larr;</span> Kembali
        </a>

        <h1 class="mt-3 font-display text-xl font-extrabold text-slate-900">Aktivitas Akun</h1>
        <p class="mt-1 text-sm text-slate-500">Riwayat login dan transaksi yang dilakukan akun kamu.</p>

        <div class="mt-6 space-y-4">
            <div class="panel-card p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-bold text-slate-900">Login History</h2>
                    <span class="text-[11px] text-slate-400">{{ $loginLogs->total() ?? $loginLogs->count() }} aktivitas</span>
                </div>

                <div class="space-y-2">
                    @forelse($loginLogs as $log)
                        @php
                            $meta = is_array($log->metadata_json) ? $log->metadata_json : [];
                            $ip = (string) ($meta['ip'] ?? '-');
                            $userAgent = (string) ($meta['user_agent'] ?? 'Unknown device');
                        @endphp
                        <div class="rounded-xl border border-slate-200 bg-white px-3 py-2.5">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold text-slate-800">Login Interdotz</p>
                                    <p class="mt-0.5 truncate text-[11px] text-slate-500">{{ \Illuminate\Support\Str::limit($userAgent, 80) }}</p>
                                </div>
                                <p class="shrink-0 text-[10px] text-slate-400">{{ $log->created_at?->timezone(config('app.display_timezone', config('app.timezone')))->format('d M Y, H:i') }}</p>
                            </div>
                            <p class="mt-1 text-[11px] text-slate-500">IP: <span class="font-mono">{{ $ip }}</span></p>
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-slate-200 bg-white px-3 py-5 text-center text-xs text-slate-500">
                            Belum ada login history.
                        </div>
                    @endforelse
                </div>

                @if(method_exists($loginLogs, 'links'))
                    <div class="mt-3">
                        {{ $loginLogs->links() }}
                    </div>
                @endif
            </div>

            <div class="panel-card p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-bold text-slate-900">Riwayat Pembayaran User</h2>
                    <span class="text-[11px] text-slate-400">{{ $paymentHistory->total() ?? $paymentHistory->count() }} transaksi</span>
                </div>

                <div class="space-y-2">
                    @forelse($paymentHistory as $item)
                        @php
                            $sourceLabel = match($item->source) {
                                'patungan' => 'Patungan gabung (DU)',
                                'patungan_midtrans' => 'Patungan gabung (IDR)',
                                'topup' => 'Top-up normkredit (DU)',
                                'topup_midtrans' => 'Top-up normkredit (IDR)',
                                'interdotz_topup', 'interdotz_charge_topup' => 'Top-up normkredit (DU)',
                                'group_creation' => 'Pembuatan grup (DU)',
                                'group_creation_midtrans' => 'Pembuatan grup (IDR)',
                                default => ucfirst(str_replace('_', ' ', (string) $item->source)),
                            };
                            $isIdr = in_array($item->source, ['patungan_midtrans', 'topup_midtrans', 'group_creation_midtrans'], true);
                            $groupName = $item->group?->name ?? 'Tanpa grup';
                        @endphp

                        <div class="rounded-xl border border-slate-200 bg-white px-3 py-2.5">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-xs font-semibold text-slate-800">{{ $sourceLabel }}</p>
                                    <p class="mt-0.5 truncate text-[11px] text-slate-500">Grup: {{ $groupName }}</p>
                                    @if($item->payment_reference)
                                        <p class="mt-1 truncate font-mono text-[10px] text-slate-400">Ref: {{ $item->payment_reference }}</p>
                                    @endif
                                </div>
                                <p class="shrink-0 text-[10px] text-slate-400">{{ $item->created_at?->timezone(config('app.display_timezone', config('app.timezone')))->format('d M Y, H:i') }}</p>
                            </div>

                            <div class="mt-2 flex items-end justify-between gap-3">
                                <div>
                                    <p class="text-[10px] uppercase tracking-wide text-slate-400">Dibayar</p>
                                    <p class="text-sm font-bold text-slate-900">
                                        @if($isIdr)
                                            Rp{{ number_format((int) $item->price_paid, 0, ',', '.') }}
                                        @else
                                            {{ number_format((int) $item->price_paid) }} DU
                                        @endif
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-[10px] uppercase tracking-wide text-slate-400">Normkredit</p>
                                    <p class="text-sm font-bold text-emerald-700">+{{ number_format(round(((int) $item->token_amount) / 2500, 1), 1, ',', '.') }}</p>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-slate-200 bg-white px-3 py-5 text-center text-xs text-slate-500">
                            Belum ada transaksi yang kamu lakukan.
                        </div>
                    @endforelse
                </div>

                @if(method_exists($paymentHistory, 'links'))
                    <div class="mt-3">
                        {{ $paymentHistory->links() }}
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection
