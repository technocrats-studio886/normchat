@extends('layouts.app', ['title' => 'Pengaturan Grup - '.$group->name, 'group' => $group])

@section('content')
    <section class="page-shell" data-membership-watch="{{ (int) $group->id }}" data-groups-url="{{ route('groups.index') }}">

        <x-page-header :back="route('chat.show', $group)" :title="$group->name" subtitle="Pengaturan grup" />

        @php
            $fmtNk = fn ($value) => number_format((float) $value, 2, ',', '.');
            $isOwner = $group->isOwnedBy(auth()->user());
        @endphp

        {{-- Normkredit: satu kantong untuk semua anggota --}}
        <div class="nc-section">
            <h2 class="nc-section-title">Normkredit</h2>
            <div class="nc-list">
                <div class="nc-list-row">
                    <div class="min-w-0 flex-1">
                        <p class="text-xs text-ink-soft">Saldo grup</p>
                        <p class="text-lg font-bold">{{ $fmtNk($creditBalance->groupTotal) }} NK</p>
                    </div>
                    @if($canManageBilling)
                        <a href="{{ route('subscription.tokens.buy', ['group' => $group->id]) }}" class="btn-primary shrink-0 px-4 py-2">Isi Normkredit</a>
                    @endif
                </div>
                @if($isOwner)
                    <form method="POST" action="{{ route('groups.credits.limit', $group) }}" class="nc-list-row items-end">
                        @csrf
                        <label class="min-w-0 flex-1">
                            <span class="text-xs text-ink-soft">Batas pemakaian harian per anggota</span>
                            <input name="member_daily_credit_limit" type="number" step="0.01" min="0" value="{{ $group->member_daily_credit_limit }}" placeholder="Kosong = tanpa batas" class="nc-input mt-1 w-full">
                        </label>
                        <button type="submit" class="btn-secondary shrink-0 px-4 py-2">Simpan</button>
                    </form>
                @endif
            </div>
            <p class="nc-section-note mt-2">
                Dipakai semua anggota untuk Normi AI.
                @if(! $isOwner && $group->member_daily_credit_limit)
                    Batas pemakaian {{ $fmtNk($group->member_daily_credit_limit) }} NK per anggota per hari.
                @endif
                @if($creditBalance->dailyQuotaLeft !== null)
                    Sisa batas harianmu {{ $fmtNk($creditBalance->dailyQuotaLeft) }} NK.
                @endif
            </p>
        </div>

        {{-- Info grup: form untuk pemilik/admin, teks untuk anggota lain --}}
        <div class="nc-section">
            <h2 class="nc-section-title">Info grup</h2>
            @if($canEditProfile)
                <form method="POST" action="{{ route('settings.profile.update', $group) }}" class="nc-list space-y-3 p-4">
                    @csrf
                    <label class="block">
                        <span class="text-xs font-semibold text-ink-soft">Nama grup</span>
                        <input type="text" name="name" value="{{ old('name', $group->name) }}" required class="nc-input mt-1 w-full">
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold text-ink-soft">Deskripsi</span>
                        <textarea name="description" rows="3" class="nc-input mt-1 w-full">{{ old('description', $group->description) }}</textarea>
                    </label>
                    <div>
                        <label for="group_password" class="text-xs font-semibold text-ink-soft">Kata sandi baru</label>
                        <div class="relative mt-1">
                            <input id="group_password" type="password" name="password" class="nc-input w-full pr-10" placeholder="Kosongkan bila tidak diganti" autocomplete="new-password">
                            <button type="button" data-password-toggle="group_password" class="absolute inset-y-0 right-3 inline-flex items-center text-ink-soft" aria-label="Lihat kata sandi">
                                <svg data-eye-icon="off" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                                <svg data-eye-icon="on" xmlns="http://www.w3.org/2000/svg" class="hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88"/></svg>
                            </button>
                        </div>
                    </div>
                    <label class="nc-switch">
                        <span>Anggota baru perlu persetujuan</span>
                        <input type="checkbox" name="approval_enabled" value="1" role="switch" @checked(old('approval_enabled', $group->approval_enabled))>
                    </label>
                    <button type="submit" class="btn-primary w-full py-2.5">Simpan</button>
                </form>
            @else
                <div class="nc-list">
                    <div class="nc-list-row"><div class="min-w-0 flex-1"><p class="text-xs text-ink-soft">Nama grup</p><p class="text-sm font-semibold">{{ $group->name }}</p></div></div>
                    @if($group->description)
                        <div class="nc-list-row"><div class="min-w-0 flex-1"><p class="text-xs text-ink-soft">Deskripsi</p><p class="whitespace-pre-line text-sm">{{ $group->description }}</p></div></div>
                    @endif
                </div>
            @endif
            <div class="nc-list mt-3">
                <div class="nc-list-row">
                    <div class="min-w-0 flex-1"><p class="text-xs text-ink-soft">Kode grup untuk mengajak teman</p><p class="font-mono text-sm font-semibold">{{ $group->share_id }}</p></div>
                    <button type="button" class="btn-secondary shrink-0 px-3 py-1.5 text-xs" data-copy-share-id="{{ $group->share_id }}">Salin</button>
                    <button type="button" class="btn-secondary shrink-0 px-3 py-1.5 text-xs" data-share-group-url="{{ route('groups.join', $group->share_id) }}" data-share-group-name="{{ $group->name }}">Bagikan</button>
                </div>
            </div>
        </div>

        <div class="nc-section">
            <h2 class="nc-section-title">Anggota</h2>
            <div class="nc-list">
                @forelse($members ?? [] as $member)
                    @php
                        $roleKey = $member->role->key ?? 'member';
                        $isOwnerMember = (int) $member->user_id === (int) $group->owner_id;
                        $isSelfMember = (int) $member->user_id === (int) auth()->id();
                    @endphp
                    <div class="nc-list-row">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold">{{ $member->user?->name ?? 'Anggota' }}{{ $isSelfMember ? ' (kamu)' : '' }}</p>
                            <p class="text-xs text-ink-soft">{{ $isOwnerMember ? 'Pemilik' : ($roleKey === 'admin' ? 'Admin' : 'Anggota') }}</p>
                        </div>
                        @if($canManageMembers && ! $isOwnerMember && ! $isSelfMember)
                            <form method="POST" action="{{ route('groups.members.promote', ['group' => $group, 'member' => $member]) }}">
                                @csrf
                                <input type="hidden" name="role" value="{{ $roleKey === 'admin' ? 'member' : 'admin' }}">
                                <button type="submit" class="btn-secondary px-3 py-1.5 text-xs">{{ $roleKey === 'admin' ? 'Jadikan anggota' : 'Jadikan admin' }}</button>
                            </form>
                            <form method="POST" action="{{ route('groups.members.remove', ['group' => $group, 'member' => $member]) }}" data-confirm="Keluarkan {{ $member->user?->name ?? 'anggota ini' }} dari grup?" data-confirm-label="Keluarkan">
                                @csrf
                                <button type="submit" class="nc-button-danger px-3 py-1.5 text-xs">Keluarkan</button>
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="nc-list-empty">Belum ada anggota aktif.</p>
                @endforelse
            </div>
        </div>

        {{-- Media / Tautan / Berkas tabs --}}
        <h2 class="nc-section-title">Media, tautan &amp; berkas</h2>
        <div class="card-soft p-0 overflow-hidden">
            <div class="flex border-b border-slate-200" role="tablist" data-archive-tabs>
                <button type="button" role="tab" aria-selected="true" data-archive-tab="media" class="flex-1 px-3 py-2.5 text-xs font-semibold text-slate-700 border-b-2 border-primary bg-primary-soft">Media</button>
                <button type="button" role="tab" aria-selected="false" data-archive-tab="links" class="flex-1 px-3 py-2.5 text-xs font-semibold text-slate-500 border-b-2 border-transparent hover:bg-slate-50">Tautan</button>
                <button type="button" role="tab" aria-selected="false" data-archive-tab="files" class="flex-1 px-3 py-2.5 text-xs font-semibold text-slate-500 border-b-2 border-transparent hover:bg-slate-50">Berkas</button>
            </div>

            <div class="p-3" data-archive-panel="media">
                @if($mediaMessages->isEmpty())
                    <p class="px-1 py-6 text-center text-xs text-slate-400">Belum ada media.</p>
                @else
                    <div class="grid grid-cols-3 gap-1.5">
                        @foreach($mediaMessages as $m)
                            <a href="{{ route('chat.attachment', ['group' => $group->id, 'message' => $m->id]) }}" target="_blank" rel="noopener" class="aspect-square overflow-hidden rounded-lg border border-slate-200 bg-slate-100">
                                <img src="{{ route('chat.attachment', ['group' => $group->id, 'message' => $m->id]) }}" alt="" class="h-full w-full object-cover" loading="lazy" />
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="hidden p-3" data-archive-panel="links">
                @if($linkMessages->isEmpty())
                    <p class="px-1 py-6 text-center text-xs text-slate-400">Belum ada tautan.</p>
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach($linkMessages as $m)
                            @foreach($m->extracted_urls as $url)
                                <li class="py-2.5">
                                    <a href="{{ $url }}" target="_blank" rel="noopener" class="block truncate text-sm text-primary-ink hover:underline">{{ $url }}</a>
                                    <p class="mt-0.5 text-[11px] text-slate-400">{{ \App\Support\UserTime::format($m->created_at) }}</p>
                                </li>
                            @endforeach
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="hidden p-3" data-archive-panel="files">
                @if($fileMessages->isEmpty())
                    <p class="px-1 py-6 text-center text-xs text-slate-400">Belum ada berkas.</p>
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach($fileMessages as $m)
                            <li class="flex items-center gap-3 py-2.5">
                                <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-500">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M14 2v6h6"/></svg>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <a href="{{ route('chat.attachment', ['group' => $group->id, 'message' => $m->id]) }}" target="_blank" rel="noopener" class="block truncate text-sm font-medium text-slate-800 hover:underline">{{ $m->attachment_original_name ?? basename($m->attachment_path) }}</a>
                                    <p class="text-[11px] text-slate-400">{{ \App\Support\UserTime::format($m->created_at) }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        <div class="nc-section">
            <h2 class="nc-section-title">Lainnya</h2>
            <div class="nc-list">
                <a href="{{ route('settings.transactions', $group) }}" class="nc-list-row hover:bg-surface-2"><span class="flex-1 text-sm font-semibold">Riwayat patungan</span><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 text-ink-soft" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg></a>
                <a href="{{ route('settings.history', $group) }}" class="nc-list-row hover:bg-surface-2"><span class="flex-1 text-sm font-semibold">Riwayat &amp; cadangan</span><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 text-ink-soft" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg></a>
                @if($canManageAiPersona)
                    <a href="{{ route('settings.ai.persona', $group) }}" class="nc-list-row hover:bg-surface-2"><span class="flex-1 text-sm font-semibold">Normi AI di grup ini</span><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 text-ink-soft" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg></a>
                @endif
            </div>
        </div>

        @if((int) $group->owner_id === (int) auth()->id())
            <div class="nc-section">
                <h2 class="nc-section-title">Hapus grup</h2>
                <p class="nc-section-note">Semua pesan dan anggota akan hilang permanen dan tidak bisa dikembalikan.</p>
                <button type="button" class="nc-button-danger mt-3 w-full py-2.5" data-open-delete-group="1">Hapus grup</button>
            </div>

            <div class="hidden fixed inset-0 z-[90] items-center justify-center bg-navy-950/45 px-5" data-delete-group-overlay="1" data-expected-name="{{ $group->name }}">
                <div class="w-full max-w-sm rounded-2xl bg-white p-5 shadow-2xl">
                    <p class="text-sm font-bold text-slate-900">Hapus grup "{{ $group->name }}"?</p>
                    <p class="mt-1.5 text-[13px] leading-relaxed text-slate-600">Semua pesan dan anggota akan hilang permanen. Ketik nama grup untuk lanjut.</p>
                    <input type="text" class="input-field mt-3" placeholder="Nama grup" data-delete-group-confirm-input="1" autocomplete="off" />
                    <div class="mt-4 flex items-center justify-end gap-2">
                        <button type="button" class="rounded-xl px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100" data-close-delete-group="1">Batal</button>
                        <form method="POST" action="{{ route('groups.destroy', $group) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rounded-xl bg-danger px-3 py-2 text-xs font-bold text-white hover:bg-danger-hover disabled:cursor-not-allowed disabled:opacity-50" data-delete-group-submit="1" disabled>Hapus permanen</button>
                        </form>
                    </div>
                </div>
            </div>
        @endif

        <div class="h-6"></div>
    </section>
@endsection
