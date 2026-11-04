@extends('layouts.app', ['title' => 'Info Grup - '.$group->name, 'group' => $group])

{{-- Info grup sebagai halaman penuh (HP ala WhatsApp). Di desktop, ruang chat membuka isi yang sama
     sebagai jendela atau panel kanan (docs/rancangan-info-grup.md). --}}
@section('content')
    <section class="nc-info-page-shell" data-membership-watch="{{ (int) $group->id }}" data-groups-url="{{ route('groups.index') }}">
        @include('groups.info.panel', ['mode' => 'page'])
    </section>
    @include('groups.info.share-sheet', ['group' => $group])
@endsection
