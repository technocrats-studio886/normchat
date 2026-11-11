{{-- Info grup (docs/rancangan-info-grup.md, revisi 2). Data dari App\Services\Groups\GroupInfo.
     $mode = 'page' : halaman Info grup HP (ala WhatsApp).
     $mode = 'embed': ruang chat desktop; resources/js/ui/group-info.js memindahnya ke jendela atau panel kanan.
     Status di root: data-info-tab (members | media | manage), data-info-view (main | archive);
     bagian profil yang sedang diedit memakai data-editing di [data-info-field]. --}}
@php
    $fmtNk = fn ($value) => number_format((float) $value, 2, ',', '.');
    $isPage = ($mode ?? 'page') === 'page';
    $authId = (int) auth()->id();
    $view = $infoView ?? 'main';
    $tab = in_array($view, ['media', 'manage'], true) ? $view : ($errors->hasAny(['password', 'member_daily_credit_limit']) ? 'manage' : 'members');
    $limit = $group->member_daily_credit_limit;
    $archiveTotal = $mediaCount + $linkCount + $fileCount;
    $historyLabel = $canBackup ? 'Riwayat & cadangan' : 'Unduh riwayat chat';
    $mediaUrl = fn ($m) => \App\Services\Chat\MessagePresenter::attachmentUrl($m);
@endphp
<div class="nc-info {{ $isPage ? 'nc-info-page' : 'nc-info-embed' }}" data-group-info
     data-info-tab="{{ $tab }}" data-info-view="main"
     data-chat-url="{{ route('chat.show', $group) }}">

    {{-- Bilah atas --}}
    <div class="nc-info-bar" data-info-bar>
        @if($isPage)
            <a href="{{ route('chat.show', $group) }}" class="nc-info-icon-btn" aria-label="Kembali ke chat"><x-info-icon name="back" /></a>
        @endif
        <p class="nc-info-bar-title">Info grup</p>
        <div class="ml-auto flex items-center gap-1">
            @if($isPage)
                <button type="button" class="nc-info-icon-btn" data-open-share-sheet aria-label="Bagikan grup" title="Bagikan grup"><x-info-icon name="share" /></button>
                <button type="button" class="nc-info-icon-btn" data-info-more aria-label="Lainnya" aria-haspopup="menu" aria-expanded="false"><x-info-icon name="more-v" /></button>
            @endif
            <button type="button" class="nc-info-icon-btn" data-info-close aria-label="Tutup info grup" @if($isPage) hidden @endif><x-info-icon name="close" /></button>
        </div>
    </div>

    <div class="nc-info-main">
        {{-- Profil atas. Pengelola mengedit per bagian: ketuk foto (menu Ganti/Hapus, langsung diunggah),
             ketuk nama (kolom nama saja), atau ketuk deskripsi (kotak deskripsi saja). --}}
        <div class="nc-info-hero" data-info-hero>
            @if($canEditProfile)
                <div class="relative">
                    <button type="button" class="nc-info-photo nc-info-photo-button" data-info-menu-toggle aria-haspopup="menu" aria-expanded="false" aria-label="Ubah foto grup" data-info-photo-current>
                        <x-group-photo :group="$group" />
                        <span class="nc-info-photo-badge" aria-hidden="true"><x-info-icon name="camera" class="h-4 w-4" /></span>
                        <span class="nc-info-photo-busy" hidden data-info-photo-busy>Mengunggah…</span>
                    </button>
                    <div class="nc-info-menu nc-info-photo-menu" role="menu" hidden data-info-menu>
                        <label for="info_group_avatar" class="nc-info-menu-item cursor-pointer" role="menuitem" tabindex="0" data-info-photo-pick>
                            <x-info-icon name="camera" class="h-4 w-4" />{{ $group->avatar_path ? 'Ganti foto' : 'Pasang foto' }}
                        </label>
                        @if($group->avatar_path)
                            <form method="POST" action="{{ route('settings.profile.update', $group) }}" data-confirm="Hapus foto grup? Grup kembali memakai huruf awal namanya." data-confirm-label="Hapus foto">
                                @csrf
                                <input type="hidden" name="remove_avatar" value="1">
                                <button type="submit" class="nc-info-menu-item is-danger" role="menuitem"><x-info-icon name="trash" class="h-4 w-4" />Hapus foto</button>
                            </form>
                        @endif
                    </div>
                    <form method="POST" action="{{ route('settings.profile.update', $group) }}" enctype="multipart/form-data" hidden data-info-photo-form>
                        @csrf
                        <input id="info_group_avatar" type="file" name="avatar" accept="image/jpeg,image/png,image/webp" data-info-photo-input aria-label="Pilih foto grup">
                    </form>
                </div>
                @error('avatar')<p class="mt-2 text-xs font-semibold text-danger-ink" role="alert">{{ $message }}</p>@enderror

                <div class="nc-info-field" data-info-field="name" @if($errors->has('name')) data-editing @endif>
                    <button type="button" class="nc-info-field-view nc-info-name" data-info-field-edit aria-label="Ubah nama grup">
                        {{ $group->name }}<x-info-icon name="pencil" class="nc-info-name-edit h-4 w-4" />
                    </button>
                    <form method="POST" action="{{ route('settings.profile.update', $group) }}" class="nc-info-field-form" data-info-field-form>
                        @csrf
                        <div class="flex items-center gap-2">
                            <input type="text" name="name" value="{{ old('name', $group->name) }}" required maxlength="{{ \App\Models\Group::NAME_MAX }}" data-char-count class="nc-input min-w-0 flex-1 text-center text-base font-bold" aria-label="Nama grup" data-info-field-input data-original="{{ $group->name }}">
                            <button type="submit" class="nc-info-icon-btn nc-info-field-ok" aria-label="Simpan nama"><x-info-icon name="check" /></button>
                            <button type="button" class="nc-info-icon-btn" data-info-field-cancel aria-label="Batal"><x-info-icon name="close" /></button>
                        </div>
                        @error('name')<p class="mt-1 text-xs font-semibold text-danger-ink" role="alert">{{ $message }}</p>@enderror
                    </form>
                </div>
                <p class="nc-info-meta">Grup · {{ $memberCount }} anggota</p>
                <div class="nc-info-field" data-info-field="description" @if($errors->has('description')) data-editing @endif>
                    <button type="button" class="nc-info-field-view {{ $group->description ? 'nc-info-desc' : 'nc-info-add-desc' }}" data-info-field-edit aria-label="{{ $group->description ? 'Ubah deskripsi grup' : 'Tambah deskripsi grup' }}">{{ $group->description ?: 'Tambah deskripsi grup' }}</button>
                    <form method="POST" action="{{ route('settings.profile.update', $group) }}" class="nc-info-field-form" data-info-field-form>
                        @csrf
                        <textarea name="description" rows="3" maxlength="{{ \App\Models\Group::DESCRIPTION_MAX }}" data-char-count class="nc-input w-full text-sm" placeholder="Deskripsi grup (opsional)" aria-label="Deskripsi grup" data-info-field-input data-original="{{ $group->description }}">{{ old('description', $group->description) }}</textarea>
                        @error('description')<p class="mt-1 text-xs font-semibold text-danger-ink" role="alert">{{ $message }}</p>@enderror
                        <div class="mt-2 flex justify-end gap-2">
                            <button type="button" class="btn-secondary px-4 py-1.5 text-sm" data-info-field-cancel>Batal</button>
                            <button type="submit" class="btn-primary px-4 py-1.5 text-sm">Simpan</button>
                        </div>
                    </form>
                </div>
            @else
                <span class="nc-info-photo"><x-group-photo :group="$group" /></span>
                <p class="nc-info-name">{{ $group->name }}</p>
                <p class="nc-info-meta">Grup · {{ $memberCount }} anggota</p>
                @if($group->description)
                    <p class="nc-info-desc">{{ $group->description }}</p>
                @endif
            @endif
        </div>

        {{-- Saldo grup: tepat di bawah profil --}}
        <div class="nc-info-balance">
            <div class="flex items-center gap-3">
                <div class="min-w-0 flex-1">
                    <p class="text-xs text-ink-soft">Saldo grup</p>
                    <p class="text-lg font-bold">{{ $fmtNk($creditBalance->groupTotal) }} NK</p>
                </div>
                @if($canManageBilling)
                    <a href="{{ route('subscription.tokens.buy', ['group' => $group->id]) }}" class="btn-primary shrink-0 px-5 py-2 text-sm" aria-label="Tambah Normkredit">Tambah</a>
                @endif
            </div>
            <p class="nc-info-balance-note">
                @if($limit)
                    Batas pemakaian {{ $fmtNk($limit) }} NK per anggota per hari.
                    @if($creditBalance->dailyQuotaLeft !== null)
                        Sisa batas harianmu {{ $fmtNk($creditBalance->dailyQuotaLeft) }} NK.
                    @endif
                @else
                    Dipakai bersama semua anggota untuk Normi AI.
                @endif
                <a href="{{ route('settings.transactions', $group) }}" class="font-semibold underline-offset-2 hover:underline">Riwayat patungan</a>
            </p>
            @if($group->isFree())
                <p class="nc-info-balance-note" data-free-slots>{{ $group->free_joins_used }} dari {{ $group->free_member_slots }} slot anggota gratis sudah terpakai. Setelah itu, yang bergabung ikut patungan.</p>
            @endif
        </div>

        <div class="nc-info-actions">
            <a href="{{ route('chat.show', ['group' => $group, 'search' => 1]) }}" class="nc-info-action" data-info-search>
                <span class="nc-info-action-icon"><x-info-icon name="search" /></span><span>Cari</span>
            </a>
            <button type="button" class="nc-info-action" data-open-share-sheet>
                <span class="nc-info-action-icon"><x-info-icon name="share" /></span><span>Bagikan</span>
            </button>
            <button type="button" class="nc-info-action" data-info-more aria-haspopup="menu" aria-expanded="false">
                <span class="nc-info-action-icon"><x-info-icon name="more" /></span><span>Lainnya</span>
            </button>
        </div>

        <div class="nc-info-tabs" role="tablist" aria-label="Info grup">
            <button type="button" role="tab" id="nc-info-tab-members" class="nc-info-tab" data-info-tab-button="members" aria-controls="nc-info-pane-members" aria-selected="{{ $tab === 'members' ? 'true' : 'false' }}">Anggota</button>
            <button type="button" role="tab" id="nc-info-tab-media" class="nc-info-tab" data-info-tab-button="media" aria-controls="nc-info-pane-media" aria-selected="{{ $tab === 'media' ? 'true' : 'false' }}">Media</button>
            <button type="button" role="tab" id="nc-info-tab-manage" class="nc-info-tab" data-info-tab-button="manage" aria-controls="nc-info-pane-manage" aria-selected="{{ $tab === 'manage' ? 'true' : 'false' }}">Pengaturan</button>
        </div>

        {{-- ── Tab Anggota ── --}}
        <div class="nc-info-pane" id="nc-info-pane-members" role="tabpanel" aria-labelledby="nc-info-tab-members" data-info-pane="members">
            <div class="nc-info-section-head">
                <p class="min-w-0 flex-1 text-sm font-semibold text-ink-soft">{{ $memberCount }} anggota</p>
            </div>
            @if($members->count() > 8)
                <input type="search" class="nc-input mx-4 mb-2 w-[calc(100%-2rem)]" placeholder="Cari anggota" aria-label="Cari anggota" data-info-member-filter autocomplete="off">
            @endif
            <button type="button" class="nc-info-member w-full text-left" data-open-share-sheet>
                <span class="nc-info-avatar nc-info-avatar-add"><x-info-icon name="add-member" /></span>
                <span class="text-sm font-semibold">Tambah anggota</span>
            </button>
            <ul>
                @foreach($members as $member)
                    @php
                        $roleKey = $member->role->key ?? 'member';
                        $isOwnerMember = (int) $member->user_id === (int) $group->owner_id;
                        $isSelf = (int) $member->user_id === $authId;
                        $name = $member->user?->name ?? 'Anggota';
                        $canAct = $canManageMembers && ! $isOwnerMember && ! $isSelf;
                    @endphp
                    <li class="nc-info-member" data-info-member="{{ mb_strtolower($name) }}">
                        <span class="nc-info-avatar">
                            @if($member->user?->avatar_url)
                                <img src="{{ $member->user->avatar_url }}" alt="" class="nc-group-photo" loading="lazy" referrerpolicy="no-referrer" />
                            @else
                                {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($name, 0, 1)) }}
                            @endif
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold">{{ $isSelf ? 'Kamu' : $name }}</span>
                            @if($isSelf)<span class="block truncate text-xs text-ink-soft">{{ $name }}</span>@endif
                        </span>
                        @if($isOwnerMember)
                            <span class="nc-info-badge">Pemilik</span>
                        @elseif($roleKey === 'admin')
                            <span class="nc-info-badge">Admin</span>
                        @endif
                        @if($canAct)
                            <div class="relative">
                                <button type="button" class="nc-info-icon-btn" data-info-menu-toggle aria-haspopup="menu" aria-expanded="false" aria-label="Pilihan untuk {{ $name }}"><x-info-icon name="more-v" /></button>
                                <div class="nc-info-menu" role="menu" hidden data-info-menu>
                                    <form method="POST" action="{{ route('groups.members.promote', ['group' => $group, 'member' => $member]) }}">
                                        @csrf
                                        <input type="hidden" name="role" value="{{ $roleKey === 'admin' ? 'member' : 'admin' }}">
                                        <button type="submit" class="nc-info-menu-item" role="menuitem">{{ $roleKey === 'admin' ? 'Jadikan anggota biasa' : 'Jadikan admin' }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('groups.members.remove', ['group' => $group, 'member' => $member]) }}" data-confirm="Keluarkan {{ $name }} dari grup?" data-confirm-label="Keluarkan">
                                        @csrf
                                        <button type="submit" class="nc-info-menu-item is-danger" role="menuitem">Keluarkan dari grup</button>
                                    </form>
                                </div>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
            <p class="nc-list-empty hidden" data-info-member-empty>Anggota tidak ditemukan.</p>
        </div>

        {{-- ── Tab Media: baris ke tampilan lengkap + deretan gambar yang digeser ── --}}
        <div class="nc-info-pane" id="nc-info-pane-media" role="tabpanel" aria-labelledby="nc-info-tab-media" data-info-pane="media">
            <button type="button" class="nc-info-row w-full" data-info-go="archive">
                <span class="min-w-0 flex-1 text-left text-sm font-semibold text-ink-soft">Media, tautan, dan dok</span>
                <span class="text-sm text-ink-soft">{{ $archiveTotal }}</span>
                <x-info-icon name="chevron" class="h-4 w-4 text-ink-soft" />
            </button>
            @if($mediaMessages->isEmpty())
                <p class="px-4 pb-4 text-sm text-ink-soft">Belum ada gambar di grup ini.</p>
            @else
                <div class="nc-info-strip" data-info-media-strip aria-label="Gambar di grup ini">
                    @foreach($mediaMessages as $i => $m)
                        <a href="{{ $mediaUrl($m) }}" class="nc-info-strip-item" data-info-media-item="{{ $i }}" aria-label="Buka gambar {{ $i + 1 }} dari {{ $mediaMessages->count() }}">
                            <img src="{{ $mediaUrl($m) }}" alt="" loading="lazy" draggable="false" />
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ── Tab Pengaturan ── --}}
        <div class="nc-info-pane" id="nc-info-pane-manage" role="tabpanel" aria-labelledby="nc-info-tab-manage" data-info-pane="manage">
            <div class="nc-info-list">
                @if($viewerMember ?? null)
                    @php($isMuted = $viewerMember->isMuted())
                    <div data-info-expand-row data-group-mute="{{ route('groups.mute', $group) }}">
                        <button type="button" class="nc-info-row w-full" data-info-expand aria-expanded="false">
                            <x-info-icon :name="$isMuted ? 'bell-off' : 'bell'" class="h-5 w-5 text-ink-soft" />
                            <span class="min-w-0 flex-1 text-left text-sm">Notifikasi grup</span>
                            <span class="text-xs text-ink-soft" data-group-mute-label>{{ \App\Http\Controllers\PushController::muteLabel($viewerMember) }}</span>
                            <x-info-icon name="chevron" class="h-4 w-4 text-ink-soft" />
                        </button>
                        <div class="nc-mute-options" hidden data-info-expand-body role="group" aria-label="Notifikasi grup">
                            <button type="button" class="nc-mute-option" data-group-mute-option="off" aria-pressed="{{ $isMuted ? 'false' : 'true' }}">Nyala</button>
                            <button type="button" class="nc-mute-option" data-group-mute-option="8h" aria-pressed="false">Bisukan 8 jam</button>
                            <button type="button" class="nc-mute-option" data-group-mute-option="1w" aria-pressed="false">Bisukan 1 minggu</button>
                            <button type="button" class="nc-mute-option" data-group-mute-option="always" aria-pressed="{{ $isMuted && $viewerMember->muted_until->year >= 2100 ? 'true' : 'false' }}">Bisukan selalu</button>
                            <p class="nc-mute-note">Saat dibisukan, mention dan balasan untukmu tetap muncul.</p>
                        </div>
                    </div>
                @endif
                @if($isModerator ?? false)
                    @php($recapDays = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'])
                    {{-- Rekap mingguan otomatis (docs/rancangan-fitur-produktivitas-nov.md bagian 8); resources/js/ui/weekly-recap.js. --}}
                    <div data-info-expand-row>
                        <button type="button" class="nc-info-row w-full" data-info-expand aria-expanded="false">
                            <x-info-icon name="sparkle" class="h-5 w-5 text-ink-soft" />
                            <span class="min-w-0 flex-1 text-left text-sm">Rekap mingguan</span>
                            <span class="text-xs text-ink-soft" data-weekly-recap-label>{{ $group->weekly_recap_enabled ? $recapDays[$group->weekly_recap_day] . ', ' . sprintf('%02d.00', $group->weekly_recap_hour) : 'Mati' }}</span>
                            <x-info-icon name="chevron" class="h-4 w-4 text-ink-soft" />
                        </button>
                        <form class="nc-recap-form" hidden data-info-expand-body data-weekly-recap="{{ route('groups.tools.weekly-recap', $group) }}">
                            <p class="text-xs text-ink-soft">Normi merangkum obrolan seminggu terakhir (topik, keputusan, tugas, jadwal) dan mengirimnya ke grup. Tiap rekap memakai Normkredit grup. Grup yang sepi dalam seminggu dilewati.</p>
                            <label class="nc-recap-toggle">
                                <input type="checkbox" name="enabled" value="1" @checked($group->weekly_recap_enabled)>
                                <span>Kirim rekap tiap minggu</span>
                            </label>
                            <div class="flex gap-2">
                                <select name="day" class="nc-input min-w-0 flex-1" aria-label="Hari rekap">
                                    @foreach($recapDays as $number => $dayName)
                                        <option value="{{ $number }}" @selected((int) $group->weekly_recap_day === $number)>{{ $dayName }}</option>
                                    @endforeach
                                </select>
                                <select name="hour" class="nc-input w-28" aria-label="Jam rekap">
                                    @foreach(range(0, 23) as $hour)
                                        <option value="{{ $hour }}" @selected((int) $group->weekly_recap_hour === $hour)>{{ sprintf('%02d.00', $hour) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="flex items-center justify-end gap-2">
                                <span class="text-xs text-ink-soft" data-weekly-recap-status role="status"></span>
                                <button type="submit" class="btn-primary px-4 py-2">Simpan</button>
                            </div>
                        </form>
                    </div>
                @endif
                @if($canEditProfile)
                    <div data-info-expand-row>
                        <button type="button" class="nc-info-row w-full" data-info-expand aria-expanded="{{ $errors->has('password') ? 'true' : 'false' }}">
                            <x-info-icon name="lock" class="h-5 w-5 text-ink-soft" />
                            <span class="min-w-0 flex-1 text-left text-sm">Kata sandi grup</span>
                            <span class="text-xs text-ink-soft">{{ $group->needsPassword() ? 'Ada' : 'Tidak ada' }}</span>
                            <x-info-icon name="chevron" class="h-4 w-4 text-ink-soft" />
                        </button>
                        <form method="POST" action="{{ route('settings.profile.update', $group) }}" class="px-4 pb-3" @unless($errors->has('password')) hidden @endunless data-info-expand-body>
                            @csrf
                            <input type="hidden" name="info_view" value="manage">
                            <p class="text-xs text-ink-soft">{{ $group->needsPassword() ? 'Isi untuk mengganti kata sandi. Teman yang bergabung lewat tautan akan diminta sandi ini.' : 'Grup ini belum punya kata sandi, jadi teman bisa bergabung cukup dengan kode grup.' }}</p>
                            <div class="mt-2 flex items-start gap-2">
                                <div class="relative min-w-0 flex-1">
                                    <input id="info_group_password" type="password" name="password" required minlength="4" maxlength="100" class="nc-input w-full pr-10" placeholder="Kata sandi baru" autocomplete="new-password">
                                    <button type="button" data-password-toggle="info_group_password" class="absolute inset-y-0 right-3 inline-flex items-center text-ink-soft" aria-label="Lihat kata sandi">
                                        <svg data-eye-icon="off" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                                        <svg data-eye-icon="on" xmlns="http://www.w3.org/2000/svg" class="hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88"/></svg>
                                    </button>
                                </div>
                                <button type="submit" class="btn-primary shrink-0 px-4 py-2">Simpan</button>
                            </div>
                            @error('password')<p class="mt-1 text-xs font-semibold text-danger-ink">{{ $message }}</p>@enderror
                        </form>
                    </div>
                @endif
                @if($isOwner)
                    <div data-info-expand-row>
                        <button type="button" class="nc-info-row w-full" data-info-expand aria-expanded="false">
                            <x-info-icon name="timer" class="h-5 w-5 text-ink-soft" />
                            <span class="min-w-0 flex-1 text-left text-sm">Batas harian per anggota</span>
                            <span class="text-xs text-ink-soft">{{ $limit ? $fmtNk($limit).' NK' : 'Tanpa batas' }}</span>
                            <x-info-icon name="chevron" class="h-4 w-4 text-ink-soft" />
                        </button>
                        <form method="POST" action="{{ route('groups.credits.limit', $group) }}" class="flex items-end gap-2 px-4 pb-3" hidden data-info-expand-body>
                            @csrf
                            <label class="min-w-0 flex-1">
                                <span class="text-xs text-ink-soft">Normkredit per anggota per hari. Kosongkan untuk tanpa batas.</span>
                                <input name="member_daily_credit_limit" type="number" step="0.01" min="0" value="{{ $limit }}" placeholder="Tanpa batas" class="nc-input mt-1 w-full">
                            </label>
                            <button type="submit" class="btn-primary shrink-0 px-4 py-2">Simpan</button>
                        </form>
                    </div>
                @elseif($limit)
                    <div class="nc-info-row">
                        <x-info-icon name="timer" class="h-5 w-5 text-ink-soft" />
                        <span class="min-w-0 flex-1 text-sm">Batas harian per anggota</span>
                        <span class="text-xs text-ink-soft">{{ $fmtNk($limit) }} NK</span>
                    </div>
                @endif
                <a href="{{ route('settings.ai.persona', $group) }}" class="nc-info-row">
                    <x-info-icon name="ai" class="h-5 w-5 text-ink-soft" />
                    <span class="min-w-0 flex-1 text-sm">Normi AI</span>
                    <span class="truncate text-xs text-ink-soft">{{ $aiModelLabel ?? 'Belum dipilih' }}</span>
                    <x-info-icon name="chevron" class="h-4 w-4 text-ink-soft" />
                </a>
                <a href="{{ route('settings.history', $group) }}" class="nc-info-row">
                    <x-info-icon name="download" class="h-5 w-5 text-ink-soft" />
                    <span class="min-w-0 flex-1 text-sm">{{ $historyLabel }}</span>
                    <x-info-icon name="chevron" class="h-4 w-4 text-ink-soft" />
                </a>
                <a href="{{ route('settings.transactions', $group) }}" class="nc-info-row">
                    <x-info-icon name="receipt" class="h-5 w-5 text-ink-soft" />
                    <span class="min-w-0 flex-1 text-sm">Riwayat patungan</span>
                    <x-info-icon name="chevron" class="h-4 w-4 text-ink-soft" />
                </a>
            </div>

            <div class="nc-info-list mt-3">
                @if($isOwner)
                    <button type="button" class="nc-info-row is-danger w-full" data-open-delete-group="1">
                        <x-info-icon name="trash" class="h-5 w-5" />
                        <span class="text-sm font-semibold">Hapus grup</span>
                    </button>
                @else
                    <form method="POST" action="{{ route('groups.leave', $group) }}" data-confirm="Keluar dari grup {{ $group->name }}? Patunganmu tetap menjadi saldo grup." data-confirm-label="Keluar">
                        @csrf
                        <button type="submit" class="nc-info-row is-danger w-full">
                            <x-info-icon name="leave" class="h-5 w-5" />
                            <span class="text-sm font-semibold">Keluar grup</span>
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    {{-- ── Tampilan lengkap: Media | Tautan | Berkas ── --}}
    <div class="nc-info-archive" data-info-archive>
        <div class="nc-info-pane-head">
            <button type="button" class="nc-info-icon-btn" data-info-go="main" aria-label="Kembali"><x-info-icon name="back" /></button>
            <p class="text-base font-bold">Media, tautan, dan dok</p>
        </div>
        <div class="flex border-b border-[var(--nc-line)]" role="tablist" data-archive-tabs>
            <button type="button" role="tab" aria-selected="true" data-archive-tab="media" class="flex-1 px-3 py-2.5 text-xs font-semibold text-slate-700 border-b-2 border-primary bg-primary-soft">Media</button>
            <button type="button" role="tab" aria-selected="false" data-archive-tab="links" class="flex-1 px-3 py-2.5 text-xs font-semibold text-slate-500 border-b-2 border-transparent hover:bg-slate-50">Tautan</button>
            <button type="button" role="tab" aria-selected="false" data-archive-tab="files" class="flex-1 px-3 py-2.5 text-xs font-semibold text-slate-500 border-b-2 border-transparent hover:bg-slate-50">Berkas</button>
        </div>
        <div class="p-3" data-archive-panel="media">
            @if($mediaMessages->isEmpty())
                <p class="px-1 py-6 text-center text-xs text-ink-soft">Belum ada media.</p>
            @else
                <div class="grid grid-cols-3 gap-1.5">
                    @foreach($mediaMessages as $i => $m)
                        <a href="{{ $mediaUrl($m) }}" class="aspect-square overflow-hidden rounded-lg bg-[var(--nc-surface-2)]" data-info-media-item="{{ $i }}" aria-label="Buka gambar {{ $i + 1 }} dari {{ $mediaMessages->count() }}">
                            <img src="{{ $mediaUrl($m) }}" alt="" class="h-full w-full object-cover" loading="lazy" draggable="false" />
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
        <div class="hidden pb-3" data-archive-panel="links">
            {{-- Kartu pratinjau (ketuk: buka tautan) + baris URL dan potongan chat (ketuk: lompat ke pesannya), ala WhatsApp. --}}
            @if($linkGroups === [])
                <p class="px-4 py-6 text-center text-xs text-ink-soft">Belum ada tautan.</p>
            @else
                @foreach($linkGroups as $period => $entries)
                    <p class="nc-link-period">{{ \Illuminate\Support\Str::upper($period) }}</p>
                    <ul class="nc-link-list">
                        @foreach($entries as $entry)
                            <li class="nc-link-entry">
                                <a href="{{ $entry['url'] }}" target="_blank" rel="noopener noreferrer nofollow" class="nc-link-card">
                                    <span class="nc-link-thumb">
                                        @if($entry['image_url'])
                                            <img src="{{ $entry['image_url'] }}" alt="" loading="lazy" referrerpolicy="no-referrer" data-link-image>
                                        @endif
                                        <x-info-icon name="link" class="nc-link-thumb-icon h-7 w-7" />
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="nc-link-title">{{ $entry['title'] ?: $entry['url'] }}</span>
                                        <span class="nc-link-domain">{{ $entry['domain'] }}</span>
                                    </span>
                                </a>
                                <a href="{{ route('chat.show', ['group' => $group, 'message' => $entry['message_id']]) }}" class="nc-link-source" data-jump-message="{{ $entry['message_id'] }}" title="Lihat pesannya di chat">
                                    <span class="nc-link-source-url">{{ $entry['url'] }}</span>
                                    @if($entry['text'] !== '')<span class="nc-link-source-text">{{ $entry['text'] }}</span>@endif
                                    <x-info-icon name="chevron" class="h-4 w-4 shrink-0 text-ink-soft" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endforeach
            @endif
        </div>
        <div class="hidden p-3" data-archive-panel="files">
            @if($fileMessages->isEmpty())
                <p class="px-1 py-6 text-center text-xs text-ink-soft">Belum ada berkas.</p>
            @else
                <ul class="divide-y divide-[var(--nc-line)]">
                    @foreach($fileMessages as $m)
                        <li class="flex items-center gap-3 py-2.5">
                            <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[var(--nc-surface-2)] text-ink-soft">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M14 2v6h6"/></svg>
                            </span>
                            <div class="min-w-0 flex-1">
                                <a href="{{ $mediaUrl($m) }}" target="_blank" rel="noopener" class="block truncate text-sm font-medium hover:underline">{{ $m->attachment_original_name ?? basename($m->attachment_path) }}</a>
                                <p class="text-[11px] text-ink-soft">{{ \App\Support\UserTime::format($m->created_at) }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    {{-- Menu Lainnya (tombol Lainnya dan ⋮ di bilah atas HP) --}}
    <div class="nc-info-menu nc-info-more-menu" role="menu" hidden data-info-more-menu>
        <a href="{{ route('settings.ai.persona', $group) }}" class="nc-info-menu-item" role="menuitem"><x-info-icon name="ai" class="h-4 w-4" />Normi AI</a>
        <a href="{{ route('settings.history', $group) }}" class="nc-info-menu-item" role="menuitem"><x-info-icon name="download" class="h-4 w-4" />{{ $historyLabel }}</a>
        <a href="{{ route('settings.transactions', $group) }}" class="nc-info-menu-item" role="menuitem"><x-info-icon name="receipt" class="h-4 w-4" />Riwayat patungan</a>
        @unless($isPage)
            <button type="button" class="nc-info-menu-item" role="menuitem" data-info-clear-history><x-info-icon name="clear" class="h-4 w-4" />Bersihkan chat di perangkat ini</button>
        @endunless
        @if($isOwner)
            <button type="button" class="nc-info-menu-item is-danger" role="menuitem" data-open-delete-group="1"><x-info-icon name="trash" class="h-4 w-4" />Hapus grup</button>
        @else
            <form method="POST" action="{{ route('groups.leave', $group) }}" data-confirm="Keluar dari grup {{ $group->name }}? Patunganmu tetap menjadi saldo grup." data-confirm-label="Keluar">
                @csrf
                <button type="submit" class="nc-info-menu-item is-danger w-full" role="menuitem"><x-info-icon name="leave" class="h-4 w-4" />Keluar grup</button>
            </form>
        @endif
    </div>

    {{-- Penampil gambar layar penuh (resources/js/ui/media-viewer.js) --}}
    @if($mediaMessages->isNotEmpty())
        <div class="nc-media-viewer" hidden data-media-viewer role="dialog" aria-modal="true" aria-label="Penampil gambar">
            <div class="nc-media-viewer-bar">
                <span class="nc-media-viewer-count" data-media-viewer-count aria-live="polite"></span>
                <a class="nc-media-viewer-btn" data-media-viewer-open target="_blank" rel="noopener" aria-label="Buka gambar asli"><x-info-icon name="download" /></a>
                <button type="button" class="nc-media-viewer-btn" data-media-viewer-close aria-label="Tutup"><x-info-icon name="close" /></button>
            </div>
            <div class="nc-media-viewer-stage" data-media-viewer-stage>
                <img alt="" data-media-viewer-image draggable="false" />
            </div>
            <button type="button" class="nc-media-viewer-nav is-prev" data-media-viewer-prev aria-label="Gambar sebelumnya"><x-info-icon name="back" class="h-6 w-6" /></button>
            <button type="button" class="nc-media-viewer-nav is-next" data-media-viewer-next aria-label="Gambar berikutnya"><x-info-icon name="chevron" class="h-6 w-6" /></button>
        </div>
    @endif

    @if($isOwner)
        <div class="hidden fixed inset-0 z-[96] items-center justify-center bg-navy-950/45 px-5" data-delete-group-overlay="1" data-expected-name="{{ $group->name }}">
            <div class="w-full max-w-sm rounded-2xl bg-[var(--nc-surface)] p-5 shadow-2xl">
                <p class="text-sm font-bold">Hapus grup "{{ $group->name }}"?</p>
                <p class="mt-1.5 text-[13px] leading-relaxed text-ink-soft">Semua pesan dan anggota akan hilang permanen dan tidak bisa dikembalikan. Ketik nama grup untuk lanjut.</p>
                <input type="text" class="input-field mt-3" placeholder="Nama grup" data-delete-group-confirm-input="1" autocomplete="off" />
                <div class="mt-4 flex items-center justify-end gap-2">
                    <button type="button" class="btn-secondary px-3 py-2 text-xs" data-close-delete-group="1">Batal</button>
                    <form method="POST" action="{{ route('groups.destroy', $group) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded-xl bg-danger px-3 py-2 text-xs font-bold text-white hover:bg-danger-hover disabled:cursor-not-allowed disabled:opacity-50" data-delete-group-submit="1" disabled>Hapus permanen</button>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
