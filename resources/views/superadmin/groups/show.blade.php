@extends('layouts.app', ['title' => $group->name . ' - Admin Normi Chat'])

@section('content')
    @php
        $nk = fn ($value) => number_format((float) $value, 2, ',', '.');
        $rp = fn ($value) => 'Rp' . number_format((float) $value, 0, ',', '.');
        $time = fn ($value) => $value ? \App\Support\UserTime::format(\Illuminate\Support\Carbon::parse($value)) : '–';
        $roles = ['owner' => 'Pemilik', 'admin' => 'Admin', 'member' => 'Anggota'];
        $sources = ['admin_adjustment' => 'Penyesuaian admin', 'topup' => 'Isi Normkredit', 'topup_midtrans' => 'Isi Normkredit', 'patungan' => 'Patungan', 'patungan_midtrans' => 'Patungan', 'group_creation' => 'Buat grup', 'group_creation_midtrans' => 'Buat grup'];
    @endphp
    <section class="page-shell">
        @include('superadmin._nav')

        <x-page-header :back="route('superadmin.groups.index')" :title="$group->name" :subtitle="$group->isFrozen() ? 'Dibekukan' : 'Aktif'" />

        <div class="nc-stats">
            <div class="nc-stat"><p>Saldo</p><b>{{ $nk($token->credits) }} NK</b><span>Total masuk {{ $nk($token->total_credits) }} NK</span></div>
            <div class="nc-stat"><p>Pemakaian AI 30 hari</p><b>{{ $nk($usage->charged) }} NK</b><span>{{ (int) $usage->requests }} permintaan · biaya {{ $rp($usage->cost_idr) }}</span></div>
            <div class="nc-stat"><p>Aktivitas</p><b>{{ number_format($messageCount, 0, ',', '.') }} pesan</b><span>Terakhir {{ $time($lastActivity) }}</span></div>
        </div>
        <p class="mt-2 text-xs text-ink-soft">Isi pesan dan lampiran tidak ditampilkan untuk admin.</p>

        <div class="nc-section mt-6">
            <h2 class="nc-section-title">Anggota</h2>
            <div class="nc-list">
                <div class="nc-list-row"><div class="min-w-0 flex-1"><p class="text-sm font-semibold">{{ $group->owner?->name ?? 'Pemilik dihapus' }}</p><p class="text-xs text-ink-soft">{{ $group->owner?->email }} · Pemilik</p></div></div>
                @foreach($group->members->where('user_id', '!=', $group->owner_id) as $member)
                    <div class="nc-list-row"><div class="min-w-0 flex-1"><p class="text-sm font-semibold">{{ $member->user?->name ?? 'Akun dihapus' }}</p><p class="text-xs text-ink-soft">{{ $member->user?->email }} · {{ $roles[$member->role?->key] ?? 'Anggota' }}{{ $member->status !== 'active' ? ' · ' . $member->status : '' }}</p></div></div>
                @endforeach
            </div>
        </div>

        <div class="nc-section">
            <h2 class="nc-section-title">Tambah atau kurangi Normkredit</h2>
            <p class="nc-section-note">Angka negatif mengurangi saldo. Alasan wajib dan tercatat di riwayat transaksi grup serta log audit.</p>
            <form method="POST" action="{{ route('superadmin.groups.credits', $group) }}" class="mt-3 grid gap-2 sm:grid-cols-[10rem_1fr_auto]">
                @csrf
                <input type="number" name="amount" step="0.01" required placeholder="mis. 50 atau -20" class="nc-input" value="{{ old('amount') }}" aria-label="Jumlah Normkredit">
                <input type="text" name="reason" required minlength="5" maxlength="500" placeholder="Alasan, mis. kompensasi gangguan 27 Okt" class="nc-input" value="{{ old('reason') }}" aria-label="Alasan">
                <button type="submit" class="btn-primary px-4 py-2">Simpan</button>
            </form>
        </div>

        <div class="nc-section">
            <h2 class="nc-section-title">Transaksi</h2>
            <div class="nc-list">
                @forelse($transactions as $row)
                    <div class="nc-list-row">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold">{{ $sources[$row->source] ?? $row->source }} · {{ $row->credit_amount > 0 ? '+' : '' }}{{ $nk($row->credit_amount) }} NK</p>
                            <p class="text-xs text-ink-soft">{{ $time($row->created_at) }} · {{ $row->user?->name ?? 'Akun dihapus' }}{{ $row->price_paid > 0 ? ' · ' . $rp($row->price_paid) : '' }}{{ $row->note ? ' · ' . $row->note : '' }}</p>
                        </div>
                    </div>
                @empty
                    <p class="nc-list-empty">Belum ada transaksi.</p>
                @endforelse
            </div>
        </div>

        <div class="nc-section">
            <h2 class="nc-section-title">Tindakan admin</h2>
            <div class="nc-list">
                @forelse($audit as $log)
                    <div class="nc-list-row"><div class="min-w-0 flex-1"><p class="text-sm">{{ \App\Support\AdminActions::label($log) }}</p><p class="text-xs text-ink-soft">{{ $time($log->created_at) }} · {{ $log->actor?->name ?? 'Sistem' }}</p></div></div>
                @empty
                    <p class="nc-list-empty">Belum ada tindakan admin untuk grup ini.</p>
                @endforelse
            </div>
        </div>

        <div class="nc-section">
            <h2 class="nc-section-title">Bekukan atau hapus</h2>
            <form method="POST" action="{{ route('superadmin.groups.freeze', $group) }}" class="mt-2" data-confirm="{{ $group->isFrozen() ? 'Aktifkan kembali grup ini?' : 'Bekukan grup ini? Anggota masih bisa membaca riwayat, tetapi tidak bisa mengirim pesan atau memakai Normi.' }}" data-confirm-label="{{ $group->isFrozen() ? 'Aktifkan' : 'Bekukan' }}">
                @csrf
                <button type="submit" class="btn-secondary w-full py-2.5">{{ $group->isFrozen() ? 'Aktifkan kembali' : 'Bekukan grup' }}</button>
            </form>
            <form method="POST" action="{{ route('superadmin.groups.destroy', $group) }}" class="mt-3 grid gap-2 sm:grid-cols-[1fr_auto]">
                @csrf
                @method('DELETE')
                <input type="text" name="confirm_name" required class="nc-input" placeholder="Ketik {{ $group->name }} untuk menghapus" aria-label="Ketik nama grup untuk menghapus" autocomplete="off">
                <button type="submit" class="nc-button-danger px-4 py-2">Hapus grup</button>
            </form>
        </div>
    </section>
@endsection
