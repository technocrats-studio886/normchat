<?php

namespace App\Services\Push;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * User sedang membuka Normi Chat (tab terlihat) di salah satu perangkatnya. Selama itu notifikasi push tidak
 * dikirim ke perangkat mana pun, seperti Telegram: pesannya sudah terlihat di aplikasi yang sedang dibuka.
 * Sinyal dikirim resources/js/push.js tiap ±45 detik selama tab terlihat, dan dicabut saat tab disembunyikan.
 * Dicatat per perangkat (id sesi), jadi menyembunyikan tab di laptop tidak mencabut status HP yang masih terbuka.
 */
final class PushPresence
{
    public static function touch(int $userId, string $device, bool $active): void
    {
        $window = (int) config('normchat.push.active_window_seconds', 70);
        try {
            Cache::lock(self::key($userId) . ':lock', 3)->block(2, function () use ($userId, $device, $active, $window) {
                $now = now()->timestamp;
                $devices = array_filter((array) Cache::get(self::key($userId), []), fn ($at) => $now - (int) $at < $window);
                if ($active) {
                    $devices[$device] = $now;
                } else {
                    unset($devices[$device]);
                }
                $devices === [] ? Cache::forget(self::key($userId)) : Cache::put(self::key($userId), $devices, $window);
            });
        } catch (Throwable) {
            // Cache tidak tersedia: notifikasi tetap dikirim sesuai status baca.
        }
    }

    public static function isActive(int $userId): bool
    {
        $window = (int) config('normchat.push.active_window_seconds', 70);
        try {
            $now = now()->timestamp;

            return collect((array) Cache::get(self::key($userId), []))->contains(fn ($at) => $now - (int) $at < $window);
        } catch (Throwable) {
            return false;
        }
    }

    private static function key(int $userId): string
    {
        return 'push-active:' . $userId;
    }
}
