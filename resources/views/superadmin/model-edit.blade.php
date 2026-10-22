@extends('layouts.app', ['title' => 'Edit Model - Superadmin'])

@section('content')
    <section class="page-shell pt-5">
        <a href="{{ route('superadmin.index') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-slate-500 hover:text-slate-700">
            <span aria-hidden="true">&larr;</span> Superadmin
        </a>
        <h1 class="mt-3 font-display text-xl font-extrabold text-slate-900">Edit {{ $aiModel->label }}</h1>

        <form method="POST" action="{{ route('superadmin.models.update', $aiModel) }}" class="card-soft mt-4 space-y-4">
            @csrf
            @method('PUT')
            @include('superadmin._model-form', ['aiModel' => $aiModel])
            <button type="submit" class="btn-cta">Simpan Model</button>
        </form>
    </section>
@endsection
