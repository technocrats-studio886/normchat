<?php

use App\Models\Group;
use Illuminate\Support\Facades\Broadcast;

Broadcast::routes(['middleware' => ['web', 'auth']]);

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('group.{groupId}', function ($user, $groupId) {
    $group = Group::query()->find($groupId);

    if (! $group) {
        return false;
    }

    if ((int) $group->owner_id === (int) $user->id) {
        return true;
    }

    return $group->members()
        ->where('user_id', $user->id)
        ->where('status', 'active')
        ->exists();
});
