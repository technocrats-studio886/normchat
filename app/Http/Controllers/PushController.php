<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\PushSubscription;
use App\Services\Push\PushPresence;
use App\Services\Push\WebPush;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Web Push per perangkat (resources/js/push.js): daftar/cabut langganan, sinyal "sedang membuka aplikasi",
 * notifikasi uji, dan bisu notifikasi per grup.
 */
class PushController extends Controller
{
    /**
     * Endpoint hanya boleh menuju layanan push browser; server mengirim POST ke alamat ini, jadi alamat lain
     * (jaringan internal, situs sembarang) ditolak.
     */
    private const PUSH_HOSTS = ['fcm.googleapis.com', 'android.googleapis.com', 'push.services.mozilla.com', 'push.apple.com', 'notify.windows.com'];

    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:1000', 'url:https'],
            'keys.p256dh' => ['required', 'string', 'max:200'],
            'keys.auth' => ['required', 'string', 'max:100'],
            'device' => ['nullable', 'string', 'max:120'],
        ]);
        abort_unless(self::isPushService($data['endpoint']), 422, 'Layanan push tidak dikenal.');
        abort_unless(strlen(WebPush::base64UrlDecode($data['keys']['p256dh'])) === 65 && strlen(WebPush::base64UrlDecode($data['keys']['auth'])) === 16, 422, 'Kunci langganan tidak valid.');

        // Browser yang sama bisa berganti akun: langganannya pindah ke akun yang sedang masuk.
        PushSubscription::updateOrCreate(['endpoint_hash' => PushSubscription::hashEndpoint($data['endpoint'])], [
            'user_id' => $request->user()->id,
            'endpoint' => $data['endpoint'],
            'public_key' => $data['keys']['p256dh'],
            'auth_token' => $data['keys']['auth'],
            'device' => $data['device'] ?? null,
            'failures' => 0,
        ]);

        return response()->json(['ok' => true]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $endpoint = (string) $request->validate(['endpoint' => ['required', 'string', 'max:1000']])['endpoint'];
        PushSubscription::query()
            ->where('endpoint_hash', PushSubscription::hashEndpoint($endpoint))
            ->where('user_id', $request->user()->id)
            ->delete();

        return response()->json(['ok' => true]);
    }

    /** Tab Normi Chat terlihat (true) atau disembunyikan (false) di perangkat ini. */
    public function presence(Request $request): JsonResponse
    {
        $active = $request->boolean('active');
        PushPresence::touch((int) $request->user()->id, $request->session()->getId(), $active);

        return response()->json(['ok' => true]);
    }

    /** Notifikasi uji ke perangkat ini saja, dari halaman Akun > Notifikasi. */
    public function test(Request $request): JsonResponse
    {
        $endpoint = (string) $request->validate(['endpoint' => ['required', 'string', 'max:1000']])['endpoint'];
        $subscription = PushSubscription::query()
            ->where('endpoint_hash', PushSubscription::hashEndpoint($endpoint))
            ->where('user_id', $request->user()->id)
            ->first();
        $push = WebPush::fromConfig();
        abort_unless($subscription && $push, 404);

        $result = $push->send($subscription, [
            'type' => 'test',
            'title' => 'Normi Chat',
            'body' => 'Notifikasi di perangkat ini sudah menyala.',
            'url' => route('profile.notifications', [], false),
            'tag' => 'normi-test',
        ], 300, null, 'normal');
        if ($result === WebPush::GONE) {
            $subscription->delete();
        }

        return response()->json(['ok' => $result === WebPush::SENT, 'result' => $result]);
    }

    /** Bisukan notifikasi grup: 8 jam, 1 minggu, selalu, atau nyalakan lagi. */
    public function mute(Request $request, Group $group): JsonResponse
    {
        $this->authorize('chat', $group);
        $duration = (string) $request->validate(['duration' => ['required', Rule::in(['8h', '1w', 'always', 'off'])]])['duration'];
        $member = GroupMember::query()->where('group_id', $group->id)->where('user_id', $request->user()->id)->active()->first();
        abort_unless($member, 404);

        $member->update(['muted_until' => match ($duration) {
            '8h' => now()->addHours(8),
            '1w' => now()->addWeek(),
            'always' => now()->setDate(2100, 1, 1)->startOfDay(),
            default => null,
        }]);

        return response()->json(['ok' => true, 'muted' => $member->isMuted(), 'label' => self::muteLabel($member)]);
    }

    /** Keterangan status bisu untuk panel info grup. */
    public static function muteLabel(?GroupMember $member): string
    {
        if (! $member?->isMuted()) {
            return 'Nyala';
        }
        if ($member->muted_until->year >= 2100) {
            return 'Dibisukan';
        }

        return 'Dibisukan sampai ' . \App\Support\UserTime::format($member->muted_until, 'j M H.i');
    }

    private static function isPushService(string $endpoint): bool
    {
        $host = strtolower((string) parse_url($endpoint, PHP_URL_HOST));
        if (parse_url($endpoint, PHP_URL_SCHEME) !== 'https' || $host === '') {
            return false;
        }

        foreach (self::PUSH_HOSTS as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.' . $allowed)) {
                return true;
            }
        }

        return false;
    }
}
