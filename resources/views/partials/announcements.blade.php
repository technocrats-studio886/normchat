{{--
    Pengumuman superadmin yang sedang tayang (layouts.app, user yang masuk, bukan superadmin).
    Satu banner dan satu popup per halaman; yang ditutup disimpan di sesi, jadi muncul lagi setelah masuk ulang.
--}}
@php
    $dismissed = array_map('intval', (array) session('announcements.dismissed', []));
    $showing = \App\Models\Announcement::query()->showing()->whereNotIn('id', $dismissed)->latest('id')->get();
    $banner = $showing->firstWhere('display', \App\Models\Announcement::BANNER);
    $popup = $showing->firstWhere('display', \App\Models\Announcement::POPUP);
@endphp
@if($banner || $popup)
    <div data-announcements data-dismiss-url="{{ url('/pengumuman') }}">
        @if($banner)
            <div class="nc-announce-banner-wrap">@include('partials.announcement', ['announcement' => $banner])</div>
        @endif
        @if($popup)
            <dialog class="nc-announce-dialog" data-announcement-dialog aria-label="{{ $popup->title }}" tabindex="-1">
                @include('partials.announcement', ['announcement' => $popup])
            </dialog>
        @endif
    </div>
@endif
