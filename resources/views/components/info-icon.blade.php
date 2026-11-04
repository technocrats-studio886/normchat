{{-- Ikon garis 24px untuk Info grup dan lembar Tautan grup (stroke mengikuti currentColor). --}}
@props(['name'])
@php
    $paths = [
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'share' => '<circle cx="18" cy="5" r="2.5"/><circle cx="6" cy="12" r="2.5"/><circle cx="18" cy="19" r="2.5"/><path d="m8.2 10.8 7.6-4.4M8.2 13.2l7.6 4.4"/>',
        'ai' => '<path d="M12 3.5 13.9 9l5.6 1.9-5.6 1.9L12 18.5l-1.9-5.7-5.6-1.9L10.1 9z"/><path d="M19 3v3M17.5 4.5h3"/>',
        'more' => '<path d="M5 12h.01M12 12h.01M19 12h.01" stroke-width="3"/>',
        'more-v' => '<path d="M12 5h.01M12 12h.01M12 19h.01" stroke-width="3"/>',
        'manage' => '<path d="M4 7h10M18 7h2M4 17h4M12 17h8"/><circle cx="16" cy="7" r="2"/><circle cx="10" cy="17" r="2"/>',
        'media' => '<rect x="3.5" y="4.5" width="17" height="15" rx="2.5"/><circle cx="9" cy="10" r="1.6"/><path d="m4 17 5-4.5 4 3.5 3-2.5 4 3.5"/>',
        'members' => '<circle cx="9" cy="8.5" r="3.2"/><path d="M3.5 19c.8-3.2 3-5 5.5-5s4.7 1.8 5.5 5"/><circle cx="16.5" cy="9.5" r="2.6"/><path d="M15.8 14.2c2.2 0 4 1.5 4.7 4.3"/>',
        'add-member' => '<circle cx="10" cy="8.5" r="3.4"/><path d="M3.5 19.5c.8-3.4 3.4-5.3 6.5-5.3 1.5 0 2.9.4 4 1.3"/><path d="M18.5 13v6M15.5 16h6"/>',
        'lock' => '<rect x="5" y="10.5" width="14" height="10" rx="2.5"/><path d="M8.5 10.5V8a3.5 3.5 0 0 1 7 0v2.5"/>',
        'timer' => '<circle cx="12" cy="13" r="7.5"/><path d="M12 9.5V13l2.5 1.8M9.5 2.5h5"/>',
        'download' => '<path d="M12 4v11m0 0-4-4m4 4 4-4M4.5 19.5h15"/>',
        'receipt' => '<path d="M6 3.5h12v17l-3-2-3 2-3-2-3 2z"/><path d="M9 8.5h6M9 12h6"/>',
        'clear' => '<path d="M4 7h16M9 7V4.5h6V7M6.5 7l1 13h9l1-13"/>',
        'leave' => '<path d="M14 4.5h4.5v15H14M10 8l-4 4 4 4M6 12h10"/>',
        'trash' => '<path d="M4 7h16M9 7V4.5h6V7M6.5 7l1 13h9l1-13M10 11v5M14 11v5"/>',
        'camera' => '<path d="M4 8.5A2 2 0 0 1 6 6.5h2l1.5-2h5l1.5 2h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2z"/><circle cx="12" cy="13" r="3.5"/>',
        'copy' => '<rect x="8" y="8" width="12" height="12" rx="2.5"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/>',
        'hash' => '<path d="M9 4 7 20M17 4l-2 16M4.5 9h16M3.5 15h16"/>',
        'qr' => '<rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><path d="M14 14h2v2h-2zM18 18h2v2h-2zM18 14h2M14 18v2"/>',
        'link' => '<path d="M10 14a4.5 4.5 0 0 0 6.4 0l3-3a4.5 4.5 0 0 0-6.4-6.4l-1 1"/><path d="M14 10a4.5 4.5 0 0 0-6.4 0l-3 3a4.5 4.5 0 0 0 6.4 6.4l1-1"/>',
        'pencil' => '<path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16z"/><path d="m13.5 6.5 4 4"/>',
        'chevron' => '<path d="m9 5 7 7-7 7"/>',
        'back' => '<path d="m15 5-7 7 7 7"/>',
        'close' => '<path d="M6 6l12 12M18 6 6 18"/>',
        'gift' => '<rect x="4" y="9" width="16" height="11" rx="2"/><path d="M12 9v11M4 13h16M12 9c-1.5-3.5-5.5-3.5-5.5-1S10 9 12 9c1.5-3.5 5.5-3.5 5.5-1S14 9 12 9"/>',
    ];
@endphp
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" {{ $attributes->merge(['class' => 'h-5 w-5 shrink-0']) }}>{!! $paths[$name] ?? '' !!}</svg>
