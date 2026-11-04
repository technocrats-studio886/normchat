<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\UserSession;
use App\Support\UserTime;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Akun > Pengaturan: zona waktu, notifikasi, perangkat & sesi. */
class ProfileSettingsController extends Controller
{
    /** Zona waktu Indonesia, tampil di atas daftar. */
    public const INDONESIA = [
        'Asia/Jakarta' => 'WIB · Jakarta',
        'Asia/Makassar' => 'WITA · Makassar',
        'Asia/Jayapura' => 'WIT · Jayapura',
    ];

    public static function zoneLabel(string $zone): string
    {
        return self::INDONESIA[$zone] ?? str_replace('_', ' ', $zone);
    }

    public function timezone(Request $request): View
    {
        return view('profile.timezone', [
            'user' => $request->user(),
            'zone' => UserTime::zone($request->user()),
            'others' => array_values(array_diff(DateTimeZone::listIdentifiers(), array_keys(self::INDONESIA))),
        ]);
    }

    public function saveTimezone(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'timezone_mode' => ['required', Rule::in(['auto', 'manual'])],
            'timezone' => ['required_if:timezone_mode,manual', 'nullable', Rule::in(DateTimeZone::listIdentifiers())],
        ]);

        $user = $request->user();
        $user->forceFill(['timezone_mode' => $data['timezone_mode']]);
        if ($data['timezone_mode'] === 'manual') {
            $user->forceFill(['timezone' => $data['timezone']]);
        }
        $user->save();

        return redirect()->route('profile.show')->with('success', $data['timezone_mode'] === 'manual'
            ? 'Zona waktu diatur ke ' . self::zoneLabel($data['timezone']) . '.'
            : 'Zona waktu mengikuti perangkat.');
    }

    public function notifications(Request $request): View
    {
        abort_if($request->user()->isSuperadmin(), 404);

        return view('profile.notifications', ['user' => $request->user()]);
    }

    public function saveNotifications(Request $request): RedirectResponse
    {
        abort_if($request->user()->isSuperadmin(), 404);
        $request->user()->update([
            'notify_mentions' => $request->boolean('notify_mentions'),
            'notify_sound' => $request->boolean('notify_sound'),
        ]);

        return redirect()->route('profile.show')->with('success', 'Pengaturan notifikasi disimpan.');
    }

    public function sessions(Request $request): View
    {
        return view('profile.sessions', [
            'sessions' => UserSession::query()->live()->where('user_id', $request->user()->id)->latest('last_active_at')->get(),
            'currentHash' => UserSession::hashFor($request->session()->getId()),
        ]);
    }

    /** Keluar dari semua perangkat lain: sesi lain berakhir pada permintaan berikutnya. */
    public function logoutOthers(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->increment('session_version');
        $request->session()->put('session_version', (int) $user->session_version);
        UserSession::query()->where('user_id', $user->id)
            ->where('session_hash', '!=', UserSession::hashFor($request->session()->getId()))
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
        AuditLog::record('auth.logout_other_devices', $user->id);

        return redirect()->route('profile.sessions')->with('success', 'Semua perangkat lain sudah dikeluarkan.');
    }

    public function revoke(Request $request, UserSession $session): RedirectResponse
    {
        abort_unless((int) $session->user_id === (int) $request->user()->id, 404);
        if ($session->session_hash === UserSession::hashFor($request->session()->getId())) {
            return back()->withErrors(['session' => 'Untuk perangkat ini, gunakan Keluar di halaman Akun.']);
        }

        $session->update(['revoked_at' => now()]);
        AuditLog::record('auth.logout_device', $request->user()->id, null, ['device' => $session->device]);

        return back()->with('success', 'Perangkat dikeluarkan.');
    }
}
