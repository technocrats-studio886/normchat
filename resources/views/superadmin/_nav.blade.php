{{-- Navigasi superadmin; dipakai semua halaman di resources/views/superadmin. --}}
@php
    $tabs = [
        'superadmin.index' => 'Ringkasan',
        'superadmin.groups.index' => 'Grup',
        'superadmin.users.index' => 'User',
        'superadmin.finance' => 'Keuangan & AI',
        'superadmin.openai' => 'Biaya OpenAI',
        'superadmin.audit' => 'Log audit',
        'superadmin.settings' => 'Pengaturan',
    ];
    $active = request()->route()?->getName();
    $openAlerts = \App\Models\AdminAlert::query()->open()->limit(5)->get();
@endphp
<div class="mb-5">
    <h1 class="text-xl font-bold text-ink">Admin Normi Chat</h1>
    <nav class="nc-admin-tabs" aria-label="Menu admin">
        @foreach($tabs as $route => $label)
            @php($current = $active === $route || str_starts_with((string) $active, str_replace('.index', '.', $route)) && $route !== 'superadmin.index')
            <a href="{{ route($route) }}" @class(['is-active' => $current]) @if($current) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    @foreach($openAlerts as $alert)
        <div @class(['nc-flash flex items-start gap-3', 'is-error' => $alert->level === 'danger']) role="alert">
            <div class="min-w-0 flex-1">
                <p>{{ $alert->title }}</p>
                <p class="mt-0.5 text-xs font-normal">{{ $alert->body }}</p>
            </div>
            <form method="POST" action="{{ route('superadmin.alerts.resolve', $alert) }}" class="shrink-0">
                @csrf
                <button type="submit" class="text-xs font-semibold underline underline-offset-2">Tutup</button>
            </form>
        </div>
    @endforeach
    @if(session('success'))
        <p class="nc-flash" role="status">{{ session('success') }}</p>
    @endif
    @if($errors->any())
        <p class="nc-flash is-error" role="alert">{{ $errors->first() }}</p>
    @endif
</div>
