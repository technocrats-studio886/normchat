@extends('layouts.app', ['title' => 'Log audit - Admin Normi Chat'])

@section('content')
    <section class="page-shell">
        @include('superadmin._nav')
        <p class="mb-3 text-sm text-ink-soft">Semua tindakan superadmin. Catatan ini tidak bisa diubah atau dihapus.</p>
        <div class="nc-list">
            @forelse($logs as $log)
                <div class="nc-list-row">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm">{{ \App\Support\AdminActions::label($log) }}</p>
                        <p class="text-xs text-ink-soft">{{ \App\Support\UserTime::format($log->created_at) }} · {{ $log->actor?->name ?? 'Sistem' }}</p>
                    </div>
                </div>
            @empty
                <p class="nc-list-empty">Belum ada tindakan admin.</p>
            @endforelse
        </div>
        <div class="mt-4">{{ $logs->links() }}</div>
    </section>
@endsection
