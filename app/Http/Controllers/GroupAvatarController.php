<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Services\Groups\GroupAvatars;
use Illuminate\Http\Response;

/** Menyajikan foto grup: untuk anggota, dan untuk calon anggota yang memegang kode grup. */
class GroupAvatarController extends Controller
{
    public function show(Group $group, GroupAvatars $avatars): Response
    {
        $this->authorize('view', $group);

        return $this->respond($avatars->body($group));
    }

    public function showForJoin(string $shareId, GroupAvatars $avatars): Response
    {
        $group = Group::where('share_id', strtoupper($shareId))->firstOrFail();

        return $this->respond($avatars->body($group));
    }

    private function respond(?string $body): Response
    {
        abort_if($body === null, 404);

        return response($body, 200, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'private, max-age=2592000',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => 'sandbox',
        ]);
    }
}
