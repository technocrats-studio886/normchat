@extends('layouts.app', ['title' => 'Edit ' . $aiModel->label . ' - Admin Normi Chat'])

@section('content')
    <section class="page-shell">
        @include('superadmin._nav')
        <x-page-header :back="route('superadmin.settings')" :title="'Edit ' . $aiModel->label" />

        <form method="POST" action="{{ route('superadmin.models.update', $aiModel) }}" class="card-soft space-y-4">
            @csrf
            @method('PUT')
            @include('superadmin._model-form', ['aiModel' => $aiModel])
            <button type="submit" class="btn-cta">Simpan model</button>
        </form>
    </section>
@endsection
