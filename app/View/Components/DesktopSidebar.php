<?php

namespace App\View\Components;

use App\Models\Group;
use App\Services\Groups\GroupListRows;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Kolom kiri tampilan desktop (ala Telegram): daftar grup dengan pesan terakhir dan
 * jumlah belum dibaca, profil serta pengaturan menempel di bawah. Disembunyikan di layar
 * kecil; di sana navigasi tetap lewat beranda dan bottom nav.
 */
class DesktopSidebar extends Component
{
    public function render(): View
    {
        $routeGroup = request()->route('group');

        return view('components.desktop-sidebar', [
            'user' => auth()->user(),
            'activeGroupId' => $routeGroup instanceof Group ? (int) $routeGroup->id : 0,
            'rows' => app(GroupListRows::class)->for(auth()->user()),
        ]);
    }
}
