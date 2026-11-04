@extends('layouts.admin')

@section('heading', 'Edit ' . $aiModel->label)
@section('back', route('superadmin.settings'))

@section('content')
    <form method="POST" action="{{ route('superadmin.models.update', $aiModel) }}" class="nc-box max-w-2xl space-y-4 p-4">
        @csrf
        @method('PUT')
        @include('superadmin._model-form', ['aiModel' => $aiModel])
        <button type="submit" class="btn-primary px-4 py-2">Simpan model</button>
    </form>
@endsection
