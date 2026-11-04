@extends('layouts.admin')

@section('heading', $group->name)
@section('back', route('superadmin.groups.index'))

@section('actions')
    <form method="POST" action="{{ route('superadmin.groups.freeze', $group) }}" data-confirm="{{ $group->isFrozen() ? 'Aktifkan kembali grup ini?' : 'Bekukan grup ini? Anggota masih bisa membaca riwayat, tetapi tidak bisa mengirim pesan atau memakai Normi.' }}" data-confirm-label="{{ $group->isFrozen() ? 'Aktifkan' : 'Bekukan' }}">
        @csrf
        <button type="submit" class="btn-secondary px-3 py-2 text-sm">{{ $group->isFrozen() ? 'Aktifkan kembali' : 'Bekukan grup' }}</button>
    </form>
@endsection

@section('content')
    @php
        $nk = fn ($value) => number_format((float) $value, 2, ',', '.');
        $rp = fn ($value) => 'Rp' . number_format((float) $value, 0, ',', '.');
        $time = fn ($value) => $value ? \App\Support\UserTime::format(\Illuminate\Support\Carbon::parse($value), 'j M Y, H.i') : '–';
        $roles = ['owner' => 'Pemilik', 'admin' => 'Admin', 'member' => 'Anggota'];
        $sources = \App\Models\GroupTokenContribution::SOURCE_LABELS;
    @endphp

    <p class="mb-3"><span @class(['nc-status', 'is-ok' => ! $group->isFrozen(), 'is-danger' => $group->isFrozen()])>{{ $group->isFrozen() ? 'Dibekukan' : 'Aktif' }}</span>@if($group->isFree()) <span class="nc-status">Gratis · anggota gratis {{ $group->free_joins_used }}/{{ $group->free_member_slots }}</span>@endif</p>

    <dl class="nc-kpis">
        <div class="nc-kpi"><dt>Saldo</dt><dd>{{ $nk($token->credits) }} NK<small>Total masuk {{ $nk($token->total_credits) }} NK</small></dd></div>
        <div class="nc-kpi"><dt>Pemakaian Normi 30 hari</dt><dd>{{ $nk($usage->charged) }} NK<small>{{ (int) $usage->requests }} jawaban · biaya {{ $rp($usage->cost_idr) }}</small></dd></div>
        <div class="nc-kpi"><dt>Pesan</dt><dd>{{ number_format($messageCount, 0, ',', '.') }}<small>Terakhir {{ $time($lastActivity) }}</small></dd></div>
    </dl>
    <p class="mt-2 text-xs text-ink-soft">Isi pesan dan lampiran tidak ditampilkan untuk admin.</p>

    <div class="nc-admin-cols lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
        <div>
            <section class="nc-panel">
                <div class="nc-panel-head"><h2 class="nc-panel-title">Anggota</h2></div>
                <div class="nc-table-wrap">
                    <table class="nc-table">
                        <thead><tr><th>Nama</th><th>Peran</th></tr></thead>
                        <tbody>
                            <tr>
                                <td><span class="block font-medium">{{ $group->owner?->name ?? 'Pemilik dihapus' }}</span><span class="block text-xs text-ink-soft">{{ $group->owner?->email }}</span></td>
                                <td>Pemilik</td>
                            </tr>
                            @foreach($group->members->where('user_id', '!=', $group->owner_id) as $member)
                                <tr>
                                    <td><span class="block font-medium">{{ $member->user?->name ?? 'Akun dihapus' }}</span><span class="block text-xs text-ink-soft">{{ $member->user?->email }}</span></td>
                                    <td>{{ $roles[$member->role?->key] ?? 'Anggota' }}{{ $member->status !== 'active' ? ' · ' . $member->status : '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="nc-panel">
                <div class="nc-panel-head"><h2 class="nc-panel-title">Transaksi Normkredit</h2></div>
                <div class="nc-table-wrap">
                    <table class="nc-table">
                        <thead><tr><th>Jenis</th><th>NK</th><th class="max-sm:hidden">Bayar</th><th>Waktu</th></tr></thead>
                        <tbody>
                            @forelse($transactions as $row)
                                <tr>
                                    <td class="max-w-[14rem]">
                                        <span class="block truncate font-medium">{{ $sources[$row->source] ?? $row->source }}</span>
                                        <span class="block truncate text-xs text-ink-soft">{{ $row->user?->name ?? 'Akun dihapus' }}{{ $row->note ? ' · ' . $row->note : '' }}</span>
                                    </td>
                                    <td>{{ $row->credit_amount > 0 ? '+' : '' }}{{ $nk($row->credit_amount) }}</td>
                                    <td class="max-sm:hidden">{{ $row->price_paid > 0 ? $rp($row->price_paid) : '–' }}</td>
                                    <td>{{ $time($row->created_at) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-ink-soft">Belum ada transaksi.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div>
            <section class="nc-panel">
                <div class="nc-panel-head"><h2 class="nc-panel-title">Tambah atau kurangi Normkredit</h2></div>
                <form method="POST" action="{{ route('superadmin.groups.credits', $group) }}" class="nc-box space-y-3 p-4">
                    @csrf
                    <label class="nc-field"><span>Jumlah NK</span><input type="number" name="amount" step="0.01" required placeholder="50 atau -20" class="nc-input" value="{{ old('amount') }}"></label>
                    <label class="nc-field"><span>Alasan</span><input type="text" name="reason" required minlength="5" maxlength="500" placeholder="mis. kompensasi gangguan 27 Okt" class="nc-input" value="{{ old('reason') }}"></label>
                    <p class="nc-field-hint">Angka negatif mengurangi saldo. Alasan tercatat di transaksi grup dan log audit.</p>
                    <button type="submit" class="btn-primary w-full py-2">Simpan</button>
                </form>
            </section>

            <section class="nc-panel">
                <div class="nc-panel-head"><h2 class="nc-panel-title">Tindakan admin</h2></div>
                <div class="nc-box">
                    @forelse($audit as $log)
                        <div class="nc-box-row">
                            <span class="min-w-0 flex-1">
                                <span class="block">{{ \App\Support\AdminActions::label($log) }}</span>
                                <span class="block text-xs text-ink-soft">{{ $log->actor?->name ?? 'Sistem' }} · {{ $time($log->created_at) }}</span>
                            </span>
                        </div>
                    @empty
                        <p class="nc-box-empty">Belum ada tindakan admin untuk grup ini.</p>
                    @endforelse
                </div>
            </section>

            <section class="nc-panel">
                <div class="nc-panel-head"><h2 class="nc-panel-title">Hapus grup</h2></div>
                <form method="POST" action="{{ route('superadmin.groups.destroy', $group) }}" class="nc-box space-y-3 border-danger-line p-4">
                    @csrf
                    @method('DELETE')
                    <p class="text-sm text-ink-soft">Grup, pesan, dan anggotanya dihapus. Ketik nama grup untuk melanjutkan.</p>
                    <input type="text" name="confirm_name" required class="nc-input w-full" placeholder="{{ $group->name }}" aria-label="Ketik nama grup untuk menghapus" autocomplete="off">
                    <button type="submit" class="nc-button-danger w-full py-2">Hapus grup</button>
                </form>
            </section>
        </div>
    </div>
@endsection
