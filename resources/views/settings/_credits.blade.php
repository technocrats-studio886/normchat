{{-- Normkredit grup (satu kantong untuk semua anggota). Params: $group, $creditBalance (CreditBalance) --}}
@php
    $fmtNk = fn ($value) => number_format((float) $value, 2, ',', '.');
    $isOwner = $group->isOwnedBy(auth()->user());
@endphp
<h2 class="section-title mt-6">Normkredit</h2>
<div class="card-soft space-y-4">
    <div class="rounded-xl bg-navy-50 px-3 py-2 text-sm">
        <p class="text-[10px] font-semibold uppercase tracking-wider text-navy-700">Saldo grup</p>
        <p class="font-bold text-slate-900">{{ $fmtNk($creditBalance->groupTotal) }} Normkredit</p>
    </div>
    <p class="text-xs text-slate-600">
        Dipakai semua anggota untuk Normi AI.
        @if($creditBalance->dailyQuotaLeft !== null)
            Sisa batas harianmu: <strong>{{ $fmtNk($creditBalance->dailyQuotaLeft) }} Normkredit</strong>.
        @endif
    </p>

    @if($isOwner)
        <form method="POST" action="{{ route('groups.credits.limit', $group) }}" class="flex items-end gap-2">
            @csrf
            <div class="flex-1">
                <label for="member_daily_credit_limit" class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Batas harian per anggota</label>
                <input id="member_daily_credit_limit" name="member_daily_credit_limit" type="number" step="0.01" min="0"
                       value="{{ $group->member_daily_credit_limit }}" placeholder="0 = tanpa batas" class="input-field mt-1.5" />
            </div>
            <button type="submit" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700">Simpan</button>
        </form>
    @elseif($group->member_daily_credit_limit)
        <p class="text-xs text-slate-600">Batas pemakaian: {{ $fmtNk($group->member_daily_credit_limit) }} Normkredit per anggota per hari.</p>
    @endif
</div>
