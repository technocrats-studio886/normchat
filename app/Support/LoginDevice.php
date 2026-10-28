<?php

namespace App\Support;

/** Ringkasan perangkat dari catatan masuk/keluar (user agent & lokasi yang tersimpan di log audit). */
final class LoginDevice
{
    /** @return array{device: ?string, detail: ?string, mobile: bool} */
    public static function describe(array $meta): array
    {
        $ua = strtolower((string) ($meta['user_agent'] ?? ''));
        $device = match (true) {
            $ua === '' => null,
            str_contains($ua, 'iphone') || str_contains($ua, 'ipad') => 'iPhone / iPad',
            str_contains($ua, 'android') => 'Android',
            str_contains($ua, 'windows') => 'Windows',
            str_contains($ua, 'mac os') || str_contains($ua, 'macintosh') => 'macOS',
            str_contains($ua, 'linux') => 'Linux',
            default => 'Perangkat lain',
        };
        $browser = match (true) {
            $ua === '' => null,
            str_contains($ua, 'edg/') => 'Edge',
            str_contains($ua, 'opr/') || str_contains($ua, 'opera') => 'Opera',
            str_contains($ua, 'samsungbrowser') => 'Samsung Internet',
            str_contains($ua, 'firefox') || str_contains($ua, 'fxios') => 'Firefox',
            str_contains($ua, 'chrome') || str_contains($ua, 'crios') => 'Chrome',
            str_contains($ua, 'safari') => 'Safari',
            default => null,
        };
        // Lokasi hanya bila tercatat; alamat IP sengaja tidak ditampilkan.
        $place = implode(', ', array_filter([trim((string) ($meta['city'] ?? '')), trim((string) ($meta['country'] ?? ''))]))
            ?: trim((string) ($meta['location'] ?? ''));
        $detail = implode(' · ', array_filter([$browser, $place ?: null]));

        return [
            'device' => $device,
            'detail' => $detail !== '' ? $detail : null,
            'mobile' => in_array($device, ['iPhone / iPad', 'Android'], true),
        ];
    }
}
