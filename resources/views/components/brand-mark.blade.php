{{-- Logo Normi Chat yang ikut tema: figur navy di mode terang, putih di mode gelap (titik coral tetap). --}}
@props(['alt' => ''])
<img src="{{ asset('brand/logo-mark.svg') }}" alt="{{ $alt }}" draggable="false" {{ $attributes->merge(['class' => 'nc-logo-on-light']) }} />
<img src="{{ asset('brand/logo-mark-white.svg') }}" alt="{{ $alt }}" draggable="false" {{ $attributes->merge(['class' => 'nc-logo-on-dark']) }} />
