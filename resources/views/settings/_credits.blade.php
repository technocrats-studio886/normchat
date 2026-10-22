{{-- Pembagian Normkredit grup. Params: $group, $creditBalance (CreditBalance) --}}
@php
    $fmtNk = fn ($value) => number_format((float) $value, 2, ',', '.');
    $isOwner = $group->isOwnedBy(auth()->user());
    $protectedHolders = $group->memberCredits->filter(fn ($wallet) => $wallet->protected_credits > 0)->sortByDesc('protected_credits');
@endphp
<h2 class="section-title mt-6">Kredit Grup</h2>
<div class="card-soft space-y-4">
    <div class="grid grid-cols-2 gap-2 text-sm">
        <div class="rounded-xl bg-blue-50 px-3 py-2">
            <p class="text-[10px] font-semibold uppercase tracking-wider text-blue-600">Bersama</p>
            <p class="font-bold text-slate-900">{{ $fmtNk($creditBalance->shared) }} NK</p>
        </div>
        <div class="rounded-xl bg-emerald-50 px-3 py-2">
            <p class="text-[10px] font-semibold uppercase tracking-wider text-emerald-700">Terlindungi milikmu</p>
            <p class="font-bold text-slate-900">{{ $fmtNk($creditBalance->protected) }} NK</p>
        </div>
    </div>
    <p class="text-xs text-slate-600">
        Bisa kamu pakai sekarang: <strong>{{ $fmtNk($creditBalance->usable()) }} NK</strong>
        @if($creditBalance->sharedQuotaLeft !== null)
            · sisa kuota bersama hari ini {{ $fmtNk($creditBalance->sharedQuotaLeft) }} NK
        @endif
    </p>

    @if($creditBalance->protected > 0)
        <form method="POST" action="{{ route('groups.credits.share', $group) }}" class="flex items-end gap-2">
            @csrf
            <div class="flex-1">
                <label for="share_amount" class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Bagikan ke bersama</label>
                <input id="share_amount" name="amount" type="number" step="0.01" min="0.01" max="{{ $creditBalance->protected }}" required class="input-field mt-1.5" placeholder="Jumlah NK" />
            </div>
            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-bold text-white">Bagikan</button>
        </form>
        @error('amount')
            <p class="text-xs text-red-500">{{ $message }}</p>
        @enderror
    @endif

    <form method="POST" action="{{ route('groups.credits.order', $group) }}">
        @csrf
        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Saat kamu bertanya ke AI, pakai</p>
        <div class="mt-1.5 grid grid-cols-2 gap-2 text-xs">
            @foreach([0 => 'Terlindungi dulu', 1 => 'Bersama dulu'] as $value => $label)
                <button type="submit" name="spend_shared_first" value="{{ $value }}"
                        class="rounded-xl border-2 px-3 py-2 font-semibold {{ $creditBalance->sharedFirst === (bool) $value ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-slate-200 text-slate-600' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </form>

    @if($isOwner)
        <form method="POST" action="{{ route('groups.credits.limit', $group) }}" class="flex items-end gap-2">
            @csrf
            <div class="flex-1">
                <label for="member_daily_shared_limit" class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Batas harian kredit bersama per anggota (NK)</label>
                <input id="member_daily_shared_limit" name="member_daily_shared_limit" type="number" step="0.01" min="0"
                       value="{{ $group->member_daily_shared_limit }}" placeholder="0 = tanpa batas" class="input-field mt-1.5" />
            </div>
            <button type="submit" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700">Simpan</button>
        </form>
    @elseif($group->member_daily_shared_limit)
        <p class="text-xs text-slate-600">Owner membatasi pemakaian kredit bersama: {{ $fmtNk($group->member_daily_shared_limit) }} NK per anggota per hari.</p>
    @endif

    @if($protectedHolders->isNotEmpty())
        <div>
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Kredit terlindungi anggota</p>
            <ul class="mt-1.5 space-y-1 text-xs text-slate-600">
                @foreach($protectedHolders as $wallet)
                    <li class="flex justify-between"><span>{{ $wallet->user?->name ?? 'User' }}</span><span class="font-semibold">{{ $fmtNk($wallet->protected_credits) }} NK</span></li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
