<?php

namespace App\Http\Middleware;

use App\Models\Group;
use App\Models\GroupMember;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureGroupPermission
{
    public function handle(Request $request, Closure $next, ?string $permissionKey = null): Response
    {
        $user = $request->user();
        $group = $request->route('group');

        if (! $user || ! $group instanceof Group) {
            abort(403);
        }

        if ((int) $group->owner_id === (int) $user->id) {
            return $next($request);
        }

        $membership = GroupMember::query()
            ->with('role.permissions')
            ->where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (! $membership || ! $membership->role) {
            abort(403);
        }

        if ($permissionKey === null) {
            return $next($request);
        }

        $allowed = $membership->role->permissions->contains('key', $permissionKey);

        abort_unless($allowed, 403);

        return $next($request);
    }
}
