@extends('layouts.app', ['title' => 'Group Setting - '.$group->name, 'group' => $group])

@section('content')
    <section class="page-shell">
        @php
            $canEditProfile = $canEditProfile ?? ($canManageSettings ?? false);
            $canManageBilling = $canManageBilling ?? false;
            $canManageAiPersona = $canManageAiPersona ?? false;
            $canExportChat = $canExportChat ?? false;
            $canCreateBackup = $canCreateBackup ?? false;
            $canManageMembers = $canManageMembers ?? false;
            $isReadOnly = ! $canEditProfile;
            $gt = $group->groupToken;
            $credits = $gt ? $gt->credits : 0;
            $groupInitial = strtoupper(substr($group->name, 0, 1));
        @endphp

        <div class="sticky top-0 z-20 -mx-4 mb-4 bg-[var(--nc-bg)] px-4 pb-3 pt-1">
            {{-- Header --}}
            <div class="mb-3 flex items-center gap-3">
                <a href="{{ route('chat.show', $group) }}" class="flex h-10 w-10 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-500 shadow-sm hover:bg-slate-50" aria-label="Kembali">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="m15 19-7-7 7-7"/></svg>
                </a>
                <div>
                    <p class="text-xs font-medium text-slate-500">Group Setting</p>
                    <h1 class="page-title text-xl">{{ $group->name }}</h1>
                </div>
            </div>

            {{-- Hero group card --}}
            <div class="card-glow">
                <div class="flex items-center gap-3">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 text-xl font-extrabold backdrop-blur">
                        {{ $groupInitial }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-display text-lg font-extrabold">{{ $group->name }}</p>
                        <p class="mt-0.5 text-[11px] text-white/80">ID: <span class="font-mono font-bold">{{ $group->share_id }}</span></p>
                    </div>
                </div>
                <div class="mt-4 flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-white/70">Normkredit</p>
                        <p class="font-display text-2xl font-extrabold">{{ number_format($credits, 1) }}</p>
                    </div>
                    @if($canManageBilling)
                        <a href="{{ route('subscription.tokens.buy', ['group' => $group->id]) }}" class="rounded-2xl bg-white/15 px-4 py-2 text-xs font-bold text-white backdrop-blur hover:bg-white/25">Top-up →</a>
                    @endif
                </div>
            </div>
        </div>

        @if($isReadOnly)
            <div class="mt-4 flex items-start gap-2 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800">
                <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/></svg>
                <span>Kamu sedang dalam mode read-only. Hanya owner/admin dengan izin yang bisa mengubah pengaturan.</span>
            </div>
        @endif

        {{-- Profil Grup --}}
        <h2 class="section-title mt-6">Profil Grup</h2>
        <form method="POST" action="{{ route('settings.profile.update', $group) }}" class="card-soft space-y-4">
            @csrf
            <div>
                <label for="group_name" class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Nama Group</label>
                <input id="group_name" type="text" name="name" value="{{ old('name', $group->name) }}" required @if($isReadOnly) readonly @endif class="input-field mt-1.5 @if($isReadOnly) cursor-not-allowed bg-slate-50 text-slate-500 @endif" />
            </div>

            <div>
                <label for="group_description" class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Deskripsi</label>
                <textarea id="group_description" name="description" rows="3" @if($isReadOnly) readonly @endif class="input-field mt-1.5 @if($isReadOnly) cursor-not-allowed bg-slate-50 text-slate-500 @endif">{{ old('description', $group->description) }}</textarea>
            </div>

            <div>
                <label for="group_password" class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Password Grup Baru</label>
                <p class="mt-1 text-[11px] text-slate-400">Kosongkan jika tidak ingin ganti password.</p>
                <div class="relative mt-1.5">
                    <input id="group_password" type="password" name="password" @if($isReadOnly) readonly @endif class="input-field pr-10 @if($isReadOnly) cursor-not-allowed bg-slate-50 text-slate-500 @endif" placeholder="Masukkan password baru" />
                    @if(! $isReadOnly)
                    <button type="button" onclick="toggleSettingsGroupPassword()" class="absolute inset-y-0 right-3 inline-flex items-center text-slate-400 hover:text-slate-600" aria-label="Lihat password">
                        <svg id="settingsPassShow" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg id="settingsPassHide" xmlns="http://www.w3.org/2000/svg" class="hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m3 3 18 18"/><path stroke-linecap="round" stroke-linejoin="round" d="M10.58 10.58a2 2 0 1 0 2.83 2.83"/><path stroke-linecap="round" stroke-linejoin="round" d="M9.88 5.09A10.94 10.94 0 0 1 12 5c4.48 0 8.27 2.94 9.54 7a11.05 11.05 0 0 1-4.2 5.17"/><path stroke-linecap="round" stroke-linejoin="round" d="M6.61 6.61A11.05 11.05 0 0 0 2.46 12c1.27 4.06 5.06 7 9.54 7 1.58 0 3.09-.37 4.42-1.03"/></svg>
                    </button>
                    @endif
                </div>
            </div>

            <label class="flex items-center gap-2 text-xs text-slate-600">
                <input type="checkbox" name="approval_enabled" value="1" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" {{ old('approval_enabled', $group->approval_enabled) ? 'checked' : '' }} @if($isReadOnly) disabled @endif>
                Wajib approval sebelum member baru masuk
            </label>

            @if($isReadOnly)
                <button type="button" disabled class="w-full cursor-not-allowed rounded-2xl bg-slate-100 py-3 text-sm font-semibold text-slate-400">
                    Read-Only
                </button>
            @else
                <button type="submit" class="btn-cta">Simpan Pengaturan</button>
            @endif
        </form>

        <h2 class="section-title mt-6">User Management</h2>
        <div class="card-soft space-y-2.5">
            @if(($members ?? collect())->isEmpty())
                <p class="rounded-xl bg-slate-50 px-3 py-3 text-center text-xs text-slate-500">Belum ada anggota aktif.</p>
            @else
                @foreach($members as $member)
                    @php
                        $memberUser = $member->user;
                        $roleKey = $member->role->key ?? 'member';
                        $isOwnerMember = (int) ($member->user_id ?? 0) === (int) $group->owner_id;
                        $isSelfMember = (int) ($member->user_id ?? 0) === (int) auth()->id();
                    @endphp
                    <div class="rounded-2xl border border-slate-200 bg-white px-3 py-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-bold text-slate-800">{{ $memberUser?->name ?? 'User' }}</p>
                                <p class="mt-0.5 text-[11px] text-slate-500">
                                    @if($isOwnerMember)
                                        Pemilik Grup
                                    @elseif($roleKey === 'admin')
                                        Admin
                                    @else
                                        Member
                                    @endif
                                    @if($isSelfMember)
                                        • Kamu
                                    @endif
                                </p>
                            </div>

                            @if($isOwnerMember)
                                <span class="chip-indigo">Owner</span>
                            @elseif(! $canManageMembers)
                                <span class="chip-slate">Read-only</span>
                            @elseif($isSelfMember)
                                <span class="chip-slate">Akun kamu</span>
                            @else
                                <div class="flex items-center gap-2">
                                    <form method="POST" action="{{ route('groups.members.promote', ['group' => $group, 'member' => $member]) }}" class="flex items-center gap-1.5">
                                        @csrf
                                        <input type="hidden" name="role" value="{{ $roleKey === 'admin' ? 'member' : 'admin' }}" />
                                        <button type="submit" class="rounded-lg border border-slate-200 px-2.5 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-50">
                                            {{ $roleKey === 'admin' ? 'Jadikan Member' : 'Jadikan Admin' }}
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('groups.members.remove', ['group' => $group, 'member' => $member]) }}" onsubmit="return confirm('Hapus anggota ini dari grup?');">
                                        @csrf
                                        <button type="submit" class="rounded-lg border border-rose-200 px-2.5 py-1 text-[11px] font-semibold text-rose-600 hover:bg-rose-50">Keluarkan</button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            @endif
        </div>

        {{-- Media / Tautan / Berkas tabs --}}
        <h2 class="section-title mt-6">Arsip Grup</h2>
        <div class="card-soft p-0 overflow-hidden">
            <div class="flex border-b border-slate-200" role="tablist" data-archive-tabs>
                <button type="button" role="tab" aria-selected="true" data-archive-tab="media" class="flex-1 px-3 py-2.5 text-xs font-semibold text-slate-700 border-b-2 border-blue-500 bg-blue-50">Media</button>
                <button type="button" role="tab" aria-selected="false" data-archive-tab="links" class="flex-1 px-3 py-2.5 text-xs font-semibold text-slate-500 border-b-2 border-transparent hover:bg-slate-50">Tautan</button>
                <button type="button" role="tab" aria-selected="false" data-archive-tab="files" class="flex-1 px-3 py-2.5 text-xs font-semibold text-slate-500 border-b-2 border-transparent hover:bg-slate-50">Berkas</button>
            </div>

            <div class="p-3" data-archive-panel="media">
                @if($mediaMessages->isEmpty())
                    <p class="px-1 py-6 text-center text-xs text-slate-400">Belum ada media di grup ini.</p>
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
                                    <a href="{{ $url }}" target="_blank" rel="noopener" class="block truncate text-sm text-blue-600 hover:underline">{{ $url }}</a>
                                    <p class="mt-0.5 text-[11px] text-slate-400">{{ optional($m->created_at)->format('d M Y H:i') }}</p>
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
                                    <p class="text-[11px] text-slate-400">{{ optional($m->created_at)->format('d M Y H:i') }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        <script>
            (function () {
                const root = document.querySelector('[data-archive-tabs]');
                if (!root) return;
                const tabs = root.querySelectorAll('[data-archive-tab]');
                const panels = document.querySelectorAll('[data-archive-panel]');
                tabs.forEach((tab) => {
                    tab.addEventListener('click', () => {
                        const target = tab.getAttribute('data-archive-tab');
                        tabs.forEach((t) => {
                            const active = t === tab;
                            t.setAttribute('aria-selected', active ? 'true' : 'false');
                            t.classList.toggle('text-slate-700', active);
                            t.classList.toggle('border-blue-500', active);
                            t.classList.toggle('bg-blue-50', active);
                            t.classList.toggle('text-slate-500', !active);
                            t.classList.toggle('border-transparent', !active);
                        });
                        panels.forEach((p) => {
                            p.classList.toggle('hidden', p.getAttribute('data-archive-panel') !== target);
                        });
                    });
                });
            })();
        </script>

        <h2 class="section-title mt-6">Ringkasan Grup</h2>
        <div class="card-soft space-y-2.5">
            <p class="text-xs text-slate-500">Backup, export, dan AI persona tersedia di menu cepat chat. Theme chat diatur dari halaman ini.</p>
            <a href="{{ route('settings.transactions', $group) }}" class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">
                <span>Riwayat Transaksi</span>
                <span aria-hidden="true">></span>
            </a>
        </div>

        <h2 class="section-title mt-6">Theme Chat</h2>
        <div class="card-soft space-y-3" data-chat-theme-picker="1" data-chat-theme-group-id="{{ (int) $group->id }}">
            <p class="text-xs text-slate-500">Theme berlaku untuk perangkat ini saat membuka chat grup ini.</p>
            <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-4" data-chat-theme-list="1">
                @foreach ([
                    ['id' => 'default', 'label' => 'Default', 'color' => '#f5f8ff'],
                    ['id' => 'midnight', 'label' => 'Midnight', 'color' => '#041126'],
                    ['id' => 'ocean', 'label' => 'Ocean', 'color' => '#0a4f76'],
                    ['id' => 'forest', 'label' => 'Forest', 'color' => '#0f5132'],
                    ['id' => 'sunset', 'label' => 'Sunset', 'color' => '#7c2d12'],
                    ['id' => 'slate', 'label' => 'Slate', 'color' => '#1e293b'],
                    ['id' => 'charcoal', 'label' => 'Charcoal', 'color' => '#111827'],
                ] as $theme)
                    <button
                        type="button"
                        class="overflow-hidden rounded-xl border border-slate-200 text-left text-[11px] font-semibold text-slate-700 transition hover:border-indigo-300"
                        data-chat-theme-option="{{ $theme['id'] }}"
                    >
                        <span class="block h-11 w-full" style="background: {{ $theme['color'] }};"></span>
                        <span class="block px-2 py-1.5">{{ $theme['label'] }}</span>
                    </button>
                @endforeach
            </div>
            <p class="text-[11px] text-slate-500" data-chat-theme-status="1">Theme aktif: Default</p>
            <a href="{{ route('chat.show', $group) }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                Kembali ke chat untuk lihat perubahan
            </a>
        </div>

        @if((int) $group->owner_id === (int) auth()->id())
            <h2 class="section-title mt-6 text-rose-600">Zona Berbahaya</h2>
            <div class="card-soft space-y-3 border border-rose-200">
                <p class="text-xs text-slate-600">Menghapus grup akan menghilangkan semua pesan, anggota, dan data terkait secara permanen. Tindakan ini tidak dapat dibatalkan.</p>
                <button type="button" class="w-full rounded-2xl border border-rose-300 bg-rose-50 py-3 text-sm font-bold text-rose-600 transition hover:bg-rose-100" data-open-delete-group="1">
                    Hapus Grup
                </button>
            </div>

            <div class="hidden fixed inset-0 z-[90] items-center justify-center bg-slate-900/45 px-5" data-delete-group-overlay="1">
                <div class="w-full max-w-sm rounded-2xl bg-white p-5 shadow-2xl">
                    <p class="text-sm font-bold text-slate-900">Hapus grup "{{ $group->name }}"?</p>
                    <p class="mt-1.5 text-[13px] leading-relaxed text-slate-600">Semua pesan, anggota, dan backup akan dihapus. Ketik nama grup untuk konfirmasi.</p>
                    <input type="text" class="input-field mt-3" placeholder="Ketik nama grup persis" data-delete-group-confirm-input="1" autocomplete="off" />
                    <div class="mt-4 flex items-center justify-end gap-2">
                        <button type="button" class="rounded-xl px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100" data-close-delete-group="1">Batal</button>
                        <form method="POST" action="{{ route('groups.destroy', $group) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rounded-xl bg-rose-500 px-3 py-2 text-xs font-bold text-white hover:bg-rose-600 disabled:cursor-not-allowed disabled:opacity-50" data-delete-group-submit="1" disabled>Hapus permanen</button>
                        </form>
                    </div>
                </div>
            </div>

            <script>
                (function () {
                    const openBtn = document.querySelector('[data-open-delete-group]');
                    const overlay = document.querySelector('[data-delete-group-overlay]');
                    const closeBtn = document.querySelector('[data-close-delete-group]');
                    const input = document.querySelector('[data-delete-group-confirm-input]');
                    const submit = document.querySelector('[data-delete-group-submit]');
                    const expected = @json($group->name);
                    if (!openBtn || !overlay || !closeBtn || !input || !submit) return;
                    const show = () => { overlay.classList.remove('hidden'); overlay.classList.add('flex'); input.value=''; submit.disabled=true; input.focus(); };
                    const hide = () => { overlay.classList.add('hidden'); overlay.classList.remove('flex'); };
                    openBtn.addEventListener('click', show);
                    closeBtn.addEventListener('click', hide);
                    overlay.addEventListener('click', (e) => { if (e.target === overlay) hide(); });
                    input.addEventListener('input', () => { submit.disabled = input.value.trim() !== expected; });
                })();
            </script>
        @endif

        <div class="h-6"></div>
    </section>

    <script>
        function toggleSettingsGroupPassword() {
            const input = document.getElementById('group_password');
            const show = document.getElementById('settingsPassShow');
            const hide = document.getElementById('settingsPassHide');
            if (!input) return;
            const makeVisible = input.type === 'password';
            input.type = makeVisible ? 'text' : 'password';
            if (show && hide) {
                show.classList.toggle('hidden', makeVisible);
                hide.classList.toggle('hidden', !makeVisible);
            }
        }

        (function () {
            const picker = document.querySelector('[data-chat-theme-picker="1"]');
            if (!picker) {
                return;
            }

            const groupId = String(picker.getAttribute('data-chat-theme-group-id') || 'global');
            const storageKey = `nc:chatTheme:${groupId}`;
            const status = picker.querySelector('[data-chat-theme-status="1"]');
            const options = Array.from(picker.querySelectorAll('[data-chat-theme-option]'));
            const names = {
                default: 'Default',
                midnight: 'Midnight',
                ocean: 'Ocean',
                forest: 'Forest',
                sunset: 'Sunset',
                slate: 'Slate',
                charcoal: 'Charcoal',
            };

            const setActive = (themeId) => {
                const selected = names[themeId] ? themeId : 'default';
                options.forEach((button) => {
                    const isActive = button.getAttribute('data-chat-theme-option') === selected;
                    button.classList.toggle('ring-2', isActive);
                    button.classList.toggle('ring-indigo-500', isActive);
                    button.classList.toggle('border-indigo-400', isActive);
                });
                if (status) {
                    status.textContent = `Theme aktif: ${names[selected] || 'Default'}`;
                }
            };

            let initial = 'default';
            try {
                const stored = String(window.localStorage.getItem(storageKey) || '').trim().toLowerCase();
                if (stored && names[stored]) {
                    initial = stored;
                }
            } catch (_error) {
                initial = 'default';
            }
            setActive(initial);

            options.forEach((button) => {
                button.addEventListener('click', () => {
                    const next = String(button.getAttribute('data-chat-theme-option') || 'default').toLowerCase();
                    try {
                        if (next === 'default') {
                            window.localStorage.removeItem(storageKey);
                        } else {
                            window.localStorage.setItem(storageKey, next);
                        }
                    } catch (_error) {
                        // Ignore storage failures.
                    }
                    setActive(next);
                });
            });
        })();

        (function () {
            if (!window.Echo) {
                return;
            }

            const groupId = Number(@json((int) $group->id));
            const authUserId = Number(@json((int) auth()->id()));
            if (!groupId || !authUserId) {
                return;
            }

            window.Echo.private(`group.${groupId}`).listen('.group.membership.changed', (payload) => {
                const targetUserId = Number(payload?.target_user_id || 0);
                if (!targetUserId || targetUserId !== authUserId) {
                    return;
                }

                if (String(payload?.action || '') === 'removed') {
                    window.location.href = @json(route('groups.index'));
                    return;
                }

                window.location.reload();
            });
        })();
    </script>
@endsection
