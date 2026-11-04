{{-- Isi lingkaran avatar grup: foto grup bila ada (S3 lewat groups.avatar), selain itu inisial nama.
     Wadahnya yang menentukan ukuran, warna, dan bentuk; wadah harus position: relative. --}}
@props(['group', 'join' => false])
@php($photoUrl = $join ? $group->joinAvatarUrl() : $group->avatarUrl())
@if($photoUrl)<img src="{{ $photoUrl }}" alt="" class="nc-group-photo" loading="lazy" draggable="false" />@else{{ $group->initial() }}@endif
