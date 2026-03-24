@extends('layouts.app', ['title' => 'Seat Management - '.$group->name, 'group' => $group])

@section('content')
    <section class="page-shell">
        <div class="mb-4 flex items-center gap-3">
            <a href="{{ route('settings.show', $group) }}" class="text-slate-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <h1 class="font-display text-xl font-extrabold text-[#0F172A]">Seat Management</h1>
        </div>

        <p class="mb-4 text-sm text-[#64748B]">Kelola kapasitas member aktif agar biaya tetap efisien dan onboarding tim tetap lancar.</p>

        <div class="panel-card mb-4 p-4">
            <div class="grid grid-cols-2 gap-3 text-sm">
                <div class="rounded-lg bg-slate-50 p-3">
                    <p class="text-xs text-[#64748B]">Included Seats</p>
                    <p class="mt-1 text-lg font-bold text-[#0F172A]">{{ $includedSeats }}</p>
                </div>
                <div class="rounded-lg bg-slate-50 p-3">
                    <p class="text-xs text-[#64748B]">Active Members</p>
                    <p class="mt-1 text-lg font-bold text-[#0F172A]">{{ $activeMemberCount }}</p>
                </div>
                <div class="rounded-lg bg-slate-50 p-3">
                    <p class="text-xs text-[#64748B]">Extra Seats Used</p>
                    <p class="mt-1 text-lg font-bold text-[#0F172A]">{{ $extraSeats }}</p>
                </div>
                <div class="rounded-lg bg-slate-50 p-3">
                    <p class="text-xs text-[#64748B]">Seat Capacity</p>
                    <p class="mt-1 text-lg font-bold text-[#0F172A]">{{ $activeSeatCount }}</p>
                </div>
            </div>
        </div>

        <div class="panel-card mb-4 p-4">
            <h2 class="text-sm font-bold text-[#0F172A]">Invite Role Policy</h2>
            <p class="mt-1 text-xs text-[#64748B]">Owner dapat mengundang sebagai Admin atau Member. Owner transfer dilakukan terpisah demi keamanan.</p>
            <div class="mt-3 flex gap-2">
                <span class="rounded-full bg-[#EEF2FF] px-3 py-1 text-xs font-semibold text-[#2563EB]">Admin Invite</span>
                <span class="rounded-full bg-[#F0FDFA] px-3 py-1 text-xs font-semibold text-[#0F766E]">Member Invite</span>
            </div>
        </div>

        <div class="panel-card mb-4 p-4">
            <h2 class="text-sm font-bold text-[#0F172A]">Member Management</h2>
            <p class="mt-1 text-xs text-[#64748B]">Promote member ke admin atau hapus member dari group.</p>

            <div class="mt-3 space-y-3">
                @foreach($group->members as $member)
                    <div class="rounded-lg border border-slate-200 bg-white p-3">
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <p class="text-sm font-semibold text-slate-800">{{ $member->user->name ?? 'Unknown User' }}</p>
                                <p class="text-xs text-slate-500">Role: {{ strtoupper($member->role->key ?? '-') }} • Status: {{ $member->status }}</p>
                            </div>
                            @if((int) $member->user_id !== (int) $group->owner_id)
                                <form method="POST" action="{{ route('groups.members.remove', [$group, $member]) }}">
                                    @csrf
                                    <button type="submit" class="rounded-md border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-600">Remove</button>
                                </form>
                            @endif
                        </div>

                        @if((int) $member->user_id !== (int) $group->owner_id)
                            <div class="mt-2 grid grid-cols-2 gap-2">
                                <form method="POST" action="{{ route('groups.members.promote', [$group, $member]) }}">
                                    @csrf
                                    <input type="hidden" name="role" value="admin" />
                                    <button type="submit" class="w-full rounded-md border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700">Set Admin</button>
                                </form>
                                <form method="POST" action="{{ route('groups.members.promote', [$group, $member]) }}">
                                    @csrf
                                    <input type="hidden" name="role" value="member" />
                                    <button type="submit" class="w-full rounded-md border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700">Set Member</button>
                                </form>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        <a href="{{ route('subscription.add-seat', $group) }}" class="btn-cta py-3 normal-case tracking-normal">
            Tambah Seat
        </a>

        <a href="{{ route('subscription.add-seat.payments', $group) }}" class="mt-3 block w-full rounded-xl border border-[#CBD5E1] bg-white py-3 text-center text-sm font-semibold text-[#0F172A] transition hover:bg-slate-50">
            Lihat Riwayat Payment Seat
        </a>
    </section>
@endsection
