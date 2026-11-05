<?php

namespace App\Http\Controllers;

use App\Enums\PaymentPurpose;
use App\Events\GroupMembershipChanged;
use App\Exceptions\CheckoutFailedException;
use App\Http\Requests\JoinGroupRequest;
use App\Http\Requests\StoreGroupRequest;
use App\Models\AuditLog;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Role;
use App\Services\Chat\SystemMessages;
use App\Services\FreeGroups;
use App\Services\GroupMembership;
use App\Services\NormkreditPricing;
use App\Services\Payments\NormkreditCheckout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class GroupController extends Controller
{
    public function __construct(
        private readonly NormkreditPricing $pricing,
        private readonly GroupMembership $membership,
        private readonly NormkreditCheckout $checkout,
        private readonly FreeGroups $freeGroups,
        private readonly SystemMessages $systemMessages,
    ) {
    }

    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        // Grup gratis pertama juga untuk user yang sesinya sudah ada sebelum fitur ini (listener Login tidak jalan).
        if ($first = $this->freeGroups->ensureFirstGroup($user)) {
            $request->session()->put('welcome_group_id', $first->id);
        }
        // Setelah grup pertama dibuat, langsung ke chat-nya (sekali).
        if ($welcome = $request->session()->pull('welcome_group_id')) {
            $group = Group::query()->visibleTo($user)->find($welcome);
            if ($group) {
                return redirect()->route('chat.show', $group);
            }
        }

        // Urutan sama dengan sidebar desktop: berdraf dulu, lalu pesan terbaru (App\Services\Groups\GroupListRows).
        return view('groups.index', ['rows' => app(\App\Services\Groups\GroupListRows::class)->for($user)]);
    }

    public function create(Request $request): View
    {
        return view('groups.create', [
            'quote' => $this->pricing->quoteGroupCreation(),
            'freeCredits' => $this->freeGroups->nextCredits($request->user()),
            'freeLeft' => max(0, FreeGroups::QUOTA - $this->freeGroups->used($request->user())),
            'freeSlots' => $this->freeGroups->memberSlots(),
        ]);
    }

    public function store(StoreGroupRequest $request): RedirectResponse
    {
        $user = $request->user();

        // Jatah grup gratis masih ada: grup langsung aktif tanpa checkout.
        $freeCredits = $this->freeGroups->nextCredits($user);
        $free = $freeCredits === null ? null
            : $this->freeGroups->create($user, $request->validated('name'), $request->validated('password'), $request->validated('description'));
        if ($free) {
            return redirect()->route('chat.show', $free)
                ->with('success', 'Grup gratis dibuat dengan saldo ' . number_format($freeCredits, 0, ',', '.') . ' Normkredit.');
        }

        $quote = $this->pricing->quoteGroupCreation();
        $group = Group::create([
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'owner_id' => $user->id,
            'password_hash' => Hash::make($request->validated('password')),
            'ai_provider' => 'openai',
            'status' => 'pending_payment',
        ]);

        try {
            return redirect()->away($this->checkout->start($user, $group, PaymentPurpose::GroupCreate, $quote));
        } catch (CheckoutFailedException $e) {
            $group->forceDelete();

            return back()->withErrors(['payment' => $e->getMessage()])->withInput();
        }
    }

    // ── Join via share ID (gratis atau patungan) ─────────────

    /** Alur gabung (docs/rancangan-info-grup.md): profil grup → Bergabung → kata sandi → tagihan patungan. */
    private const JOIN_VERIFIED_MINUTES = 30;

    /** Langkah 1: profil grup dengan tombol Bergabung. */
    public function showJoin(Request $request, string $shareId): View|RedirectResponse
    {
        return $this->joinPage($request, $shareId, 'profile');
    }

    /** Langkah 2–3: kata sandi (bila grup bersandi dan belum diverifikasi), lalu tagihan patungan atau masuk gratis. */
    public function continueJoin(Request $request, string $shareId): View|RedirectResponse
    {
        return $this->joinPage($request, $shareId, 'continue');
    }

    public function verifyJoinPassword(Request $request, string $shareId): RedirectResponse
    {
        $group = Group::where('share_id', $shareId)->firstOrFail();
        if ($group->isOwnedBy($request->user()) || $group->hasActiveMember($request->user())) {
            return redirect()->route('chat.show', $group);
        }
        abort_unless($group->status === 'active', 404);

        $password = (string) $request->validate(['password' => ['required', 'string', 'max:100']], ['password.required' => 'Masukkan kata sandi grup.'])['password'];
        if ($group->needsPassword() && ! Hash::check($password, $group->password_hash)) {
            return back()->withErrors(['password' => 'Kata sandi salah.']);
        }
        $request->session()->put($this->joinKey($group), ['at' => now()->timestamp, 'pw' => $this->passwordFingerprint($group)]);

        return redirect()->route('groups.join.continue', $shareId);
    }

    private function joinPage(Request $request, string $shareId, string $step): View|RedirectResponse
    {
        $group = Group::where('share_id', $shareId)->with('groupToken')->withCount(['members as active_members_count' => fn ($query) => $query->where('status', 'active')])->firstOrFail();
        $user = $request->user();

        if ($group->isOwnedBy($user) || $group->hasActiveMember($user)) {
            return view('groups.join', ['group' => $group, 'step' => 'member', 'memberCount' => $this->joinMemberCount($group)]);
        }
        // Grup yang belum dibayar atau dibekukan tidak menerima anggota baru.
        abort_unless($group->status === 'active', 404);

        if ($step === 'continue') {
            $step = $this->joinVerified($request, $group) ? ($group->freeSlotsLeft() > 0 ? 'free' : 'pay') : 'password';
        }

        return view('groups.join', [
            'group' => $group,
            'step' => $step,
            'memberCount' => $this->joinMemberCount($group),
        ]);
    }

    private function joinMemberCount(Group $group): int
    {
        $active = (int) $group->active_members_count;

        return $active + ($group->members()->where('status', 'active')->where('user_id', $group->owner_id)->exists() ? 0 : 1);
    }

    private function joinKey(Group $group): string
    {
        return 'join_verified.'.$group->id;
    }

    /** Sandi diganti setelah diverifikasi → verifikasi lama tidak berlaku. */
    private function passwordFingerprint(Group $group): string
    {
        return substr(hash('sha256', (string) $group->password_hash), 0, 16);
    }

    /** Grup tanpa sandi selalu lolos; grup bersandi butuh verifikasi di sesi ini dalam 30 menit terakhir. */
    private function joinVerified(Request $request, Group $group): bool
    {
        if (! $group->needsPassword()) {
            return true;
        }
        $mark = $request->session()->get($this->joinKey($group));

        return is_array($mark)
            && ($mark['pw'] ?? null) === $this->passwordFingerprint($group)
            && now()->timestamp - (int) ($mark['at'] ?? 0) <= self::JOIN_VERIFIED_MINUTES * 60;
    }

    /** Langkah terakhir: masuk gratis (slot gratis) atau mulai pembayaran patungan. Anggota aktif baru setelah bayar. */
    public function joinViaShareId(JoinGroupRequest $request, string $shareId): RedirectResponse
    {
        $group = Group::where('share_id', $shareId)->firstOrFail();
        $user = $request->user();

        if ($group->isOwnedBy($user)) {
            return redirect()->route('chat.show', $group);
        }

        if ($group->hasActiveMember($user)) {
            return redirect()->route('chat.show', $group)->with('info', 'Kamu sudah bergabung di grup ini.');
        }
        abort_unless($group->status === 'active', 404);

        // Langkah kata sandi tidak bisa dilompati dengan mengirim form ini langsung.
        if (! $this->joinVerified($request, $group)) {
            return redirect()->route('groups.join.continue', $shareId)->withErrors(['password' => 'Masukkan kata sandi grup dulu.']);
        }

        if ($group->freeSlotsLeft() > 0) {
            // Slot dicek ulang di dalam kunci: dua orang yang bergabung bersamaan tidak memakai slot terakhir yang sama.
            $joined = DB::transaction(function () use ($group, $user) {
                $locked = Group::query()->lockForUpdate()->findOrFail($group->id);
                if ($locked->freeSlotsLeft() < 1) {
                    return false;
                }
                $locked->increment('free_joins_used');
                $this->membership->joinFree($locked, $user);

                return true;
            });
            if ($joined) {
                return redirect()->route('chat.show', $group)->with('success', 'Kamu bergabung gratis di grup ' . $group->name . '.');
            }

            return redirect()->route('groups.join.continue', $shareId)->with('info', 'Slot gratis baru saja habis. Ikut patungan untuk bergabung.');
        }

        $quote = $this->pricing->quotePackages($request->packages());

        try {
            return redirect()->away($this->checkout->start($user, $group, PaymentPurpose::GroupJoin, $quote, [
                'share_id' => $shareId,
            ]));
        } catch (CheckoutFailedException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }
    }

    // ── Member management ────────────────────────────────────

    public function promoteMember(Request $request, Group $group, GroupMember $member): RedirectResponse
    {
        $this->authorize('promoteMember', $group);
        abort_unless((int) $member->group_id === (int) $group->id, 404);

        if ((int) $member->user_id === (int) $group->owner_id) {
            return back()->withErrors(['member' => 'Peran pemilik grup tidak bisa diubah.']);
        }

        if ((int) $member->user_id === (int) $request->user()->id) {
            return back()->withErrors(['member' => 'Kamu tidak bisa mengubah peranmu sendiri.']);
        }

        $roleKey = $request->validate(['role' => ['required', 'in:' . Role::ADMIN . ',' . Role::MEMBER]])['role'];
        $member->update(['role_id' => Role::forKey($roleKey)->id]);

        AuditLog::record('group.member_role_changed', $request->user()->id, $member, ['role' => $roleKey], $group->id);
        event(new GroupMembershipChanged((int) $group->id, (int) $member->user_id, 'role_changed', $roleKey));

        return back()->with('success', 'Peran anggota diperbarui.')->with('group_info', 'members');
    }

    public function removeMember(Request $request, Group $group, GroupMember $member): RedirectResponse
    {
        $this->authorize('manageMembers', $group);
        abort_unless((int) $member->group_id === (int) $group->id, 404);

        if ((int) $member->user_id === (int) $group->owner_id) {
            return back()->withErrors(['member' => 'Pemilik grup tidak bisa dikeluarkan.']);
        }

        if ((int) $member->user_id === (int) $request->user()->id) {
            return back()->withErrors(['member' => 'Kamu tidak bisa mengeluarkan dirimu sendiri.']);
        }

        $member->delete();
        $this->systemMessages->record($group, SystemMessages::MEMBER_REMOVED, $request->user(), $member->user);

        AuditLog::record('group.member_removed', $request->user()->id, $member, [], $group->id);
        event(new GroupMembershipChanged((int) $group->id, (int) $member->user_id, 'removed', null));

        return back()->with('success', 'Anggota dikeluarkan dari grup.')->with('group_info', 'members');
    }

    /** Pemilik mengirim riwayat pesan ke anggota yang bergabung belakangan (tautan di pemberitahuan bergabung). */
    public function shareHistory(Request $request, Group $group, int $user): JsonResponse|RedirectResponse
    {
        abort_unless($group->isOwnedBy($request->user()), 403);
        $member = GroupMember::query()->where('group_id', $group->id)->where('user_id', $user)->where('status', 'active')->firstOrFail();

        $shared = $this->membership->shareHistory($group, $member->load('user'), $request->user());
        if ($shared) {
            event(new GroupMembershipChanged((int) $group->id, (int) $member->user_id, 'history_shared', null));
        }

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'shared' => $shared])
            : back()->with('success', $shared ? 'Riwayat pesan dikirim ke ' . $member->user?->name . '.' : 'Riwayat pesan sudah dikirim sebelumnya.');
    }

    /** Anggota/admin keluar sendiri. Patungannya tetap jadi saldo grup; pesannya tetap ada. */
    public function leave(Request $request, Group $group): RedirectResponse
    {
        $user = $request->user();
        if ($group->isOwnedBy($user)) {
            return back()->withErrors(['member' => 'Pemilik grup tidak bisa keluar. Hapus grup bila sudah tidak dipakai.']);
        }

        $member = GroupMember::query()->where('group_id', $group->id)->where('user_id', $user->id)->first();
        abort_unless($member !== null, 404);

        $member->delete();
        $this->systemMessages->record($group, SystemMessages::MEMBER_LEFT, $user);

        AuditLog::record('group.member_left', $user->id, $member, [], $group->id);
        event(new GroupMembershipChanged((int) $group->id, (int) $user->id, 'left', null));

        return redirect()->route('groups.index')->with('success', 'Kamu keluar dari grup ' . $group->name . '.');
    }

    public function destroy(Request $request, Group $group): RedirectResponse
    {
        abort_unless($group->isOwnedBy($request->user()), 403);

        AuditLog::record('group.deleted', $request->user()->id, $group, ['name' => (string) $group->name]);
        $group->delete();

        return redirect()->route('groups.index')->with('success', 'Grup berhasil dihapus.');
    }
}
