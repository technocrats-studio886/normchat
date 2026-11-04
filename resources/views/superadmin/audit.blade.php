@extends('layouts.admin')

@section('heading', 'Log audit')

@section('content')
    <p class="mb-3 text-sm text-ink-soft">Semua tindakan superadmin. Catatan ini tidak bisa diubah atau dihapus.</p>
    <div class="nc-table-wrap">
        <table class="nc-table">
            <thead><tr><th>Tindakan</th><th class="max-sm:hidden">Oleh</th><th>Waktu</th></tr></thead>
            <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td class="whitespace-normal">{{ \App\Support\AdminActions::label($log) }}<span class="block text-xs text-ink-soft sm:hidden">{{ $log->actor?->name ?? 'Sistem' }}</span></td>
                        <td class="max-sm:hidden">{{ $log->actor?->name ?? 'Sistem' }}</td>
                        <td>{{ \App\Support\UserTime::format($log->created_at, 'j M Y, H.i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-ink-soft">Belum ada tindakan admin.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $logs->links() }}</div>
@endsection
