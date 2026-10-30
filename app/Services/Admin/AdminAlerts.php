<?php

namespace App\Services\Admin;

use App\Jobs\SendAdminAlertMail;
use App\Models\AdminAlert;
use App\Models\AuditLog;

/**
 * Peringatan untuk superadmin: banner di semua halaman admin, catatan di log audit, dan email.
 * Setiap kejadian punya key unik, jadi peringatan yang sama tidak dikirim berulang.
 */
class AdminAlerts
{
    public const WARNING = 'warning';
    public const DANGER = 'danger';

    /** @return AdminAlert|null peringatan baru, atau null bila kejadian ini sudah pernah diperingatkan */
    public function raise(string $key, string $level, string $title, string $body): ?AdminAlert
    {
        $alert = AdminAlert::query()->firstOrCreate(['key' => $key], [
            'level' => $level,
            'title' => $title,
            'body' => $body,
        ]);
        if (! $alert->wasRecentlyCreated) {
            return null;
        }

        AuditLog::record('admin.alert', null, null, ['key' => $key, 'title' => $title]);
        SendAdminAlertMail::dispatch($title, $body);

        return $alert;
    }

    /** Tutup peringatan yang masih terbuka dengan awalan key ini (masalahnya sudah ditangani). */
    public function resolve(string $keyPrefix): void
    {
        AdminAlert::query()
            ->whereNull('resolved_at')
            ->where('key', 'like', addcslashes($keyPrefix, '%_\\') . '%')
            ->update(['resolved_at' => now()]);
    }
}
