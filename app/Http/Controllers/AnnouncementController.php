<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** User menutup pengumuman: tidak tampil lagi selama sesi ini (muncul lagi setelah masuk ulang). */
class AnnouncementController extends Controller
{
    public function dismiss(Request $request, Announcement $announcement): Response
    {
        $dismissed = array_map('intval', (array) $request->session()->get('announcements.dismissed', []));
        if (! in_array($announcement->id, $dismissed, true)) {
            $dismissed[] = $announcement->id;
            $request->session()->put('announcements.dismissed', $dismissed);
        }

        return response()->noContent();
    }
}
