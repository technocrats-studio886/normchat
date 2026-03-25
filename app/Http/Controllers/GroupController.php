<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupToken;
use App\Models\GroupTokenContribution;
use App\Models\PendingPayment;
use App\Models\Role;
use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class GroupController extends Controller
{
    private const INCLUDED_CREDITS = 10; // 10 normkredit included in subscription
    private const TOKENS_PER_CREDIT = 1_000;
    private const PLAN_PRICE = 25000;

    public function index(): View|RedirectResponse
    {
        $user = Auth::user();

        $groups = Group::query()
            ->where('owner_id', $user->id)
            ->orWhereHas('members', fn ($q) => $q->where('user_id', $user->id)->where('status', 'active'))
            ->with(['members', 'groupToken'])
            ->withCount('members')
            ->latest()
            ->get();

        return view('groups.index', ['groups' => $groups]);
    }

    public function create(): View|RedirectResponse
    {
        $user = Auth::user();

        // Must have paid subscription to create group
        $hasActive = Subscription::query()
            ->whereHas('group', fn ($q) => $q->where('owner_id', $user->id))
            ->where('status', 'active')
            ->exists();

        $hasPaidPending = PendingPayment::where('user_id', $user->id)
            ->where('payment_type', 'subscription')
            ->where('status', 'paid')
            ->exists();

        if (! $hasActive && ! session('subscription_paid') && ! $hasPaidPending) {
            return redirect()->route('subscription.pricing')
                ->with('info', 'Aktifkan paket dulu sebelum membuat grup.');
        }

        return view('groups.create');
    }

    public function store(Request $request): RedirectResponse
    {
        // Build valid model list for validation
        $validModels = [];
        foreach (config('ai_models.providers', []) as $providerKey => $provider) {
            foreach (array_keys($provider['models'] ?? []) as $modelKey) {
                $validModels[] = $modelKey;
            }
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'password' => ['required', 'string', 'min:4', 'max:100'],
            'approval_enabled' => ['nullable'],
            'ai_provider' => ['required', 'string', 'in:' . implode(',', array_keys(config('ai_models.providers', [])))],
            'ai_model' => ['required', 'string', 'in:' . implode(',', $validModels)],
        ]);

        $user = Auth::user();

        // Verify the selected model belongs to the selected provider
        $providerModels = config("ai_models.providers.{$validated['ai_provider']}.models", []);
        if (! array_key_exists($validated['ai_model'], $providerModels)) {
            return back()->withErrors(['ai_model' => 'Model tidak valid untuk provider yang dipilih.'])->withInput();
        }

        $group = Group::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'owner_id' => $user->id,
            'password_hash' => Hash::make($validated['password']),
            'approval_enabled' => (bool) ($validated['approval_enabled'] ?? false),
            'ai_provider' => $validated['ai_provider'],
            'ai_model' => $validated['ai_model'],
        ]);

        $ownerRole = Role::firstWhere('key', 'owner');

        if ($ownerRole) {
            GroupMember::updateOrCreate(
                ['group_id' => $group->id, 'user_id' => $user->id],
                ['role_id' => $ownerRole->id, 'status' => 'active', 'joined_at' => now()]
            );
        }

        // Create subscription for the new group
        Subscription::create([
            'group_id' => $group->id,
            'plan_name' => 'normchat-pro',
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'main_price' => self::PLAN_PRICE,
            'included_seats' => 2,
        ]);

        // Allocate subscription credits to the group
        $includedTokens = self::INCLUDED_CREDITS * self::TOKENS_PER_CREDIT;
        GroupToken::create([
            'group_id' => $group->id,
            'total_tokens' => $includedTokens,
            'used_tokens' => 0,
            'remaining_tokens' => $includedTokens,
        ]);

        GroupTokenContribution::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'source' => 'subscription',
            'token_amount' => $includedTokens,
            'price_paid' => self::PLAN_PRICE,
        ]);

        session()->forget(['subscription_paid', 'subscription_tokens']);

        AuditLog::create([
            'group_id' => $group->id,
            'actor_id' => $user->id,
            'action' => 'group.create',
            'target_type' => Group::class,
            'target_id' => $group->id,
            'metadata_json' => [
                'ai_provider' => $validated['ai_provider'],
                'ai_model' => $validated['ai_model'],
            ],
            'created_at' => now(),
        ]);

        return redirect()->route('chat.show', $group)->with('success', 'Group "' . $group->name . '" berhasil dibuat!');
    }

    private const SEAT_PRICE = 4000;
    private const MIN_PATUNGAN = 10000;
    private const PRICE_PER_NORMKREDIT = 1000;

    // ── Join via share ID: show patungan form ───────────────

    public function showJoin(string $shareId): View|RedirectResponse
    {
        $group = Group::where('share_id', $shareId)->with('groupToken')->firstOrFail();

        $existing = GroupMember::where('group_id', $group->id)
            ->where('user_id', Auth::id())
            ->where('status', 'active')
            ->first();

        if ($existing || (int) $group->owner_id === (int) Auth::id()) {
            return view('groups.join', [
                'group' => $group,
                'alreadyMember' => true,
            ]);
        }

        return view('groups.join', [
            'group' => $group,
            'alreadyMember' => false,
            'seatPrice' => self::SEAT_PRICE,
            'minPatungan' => self::MIN_PATUNGAN,
            'pricePerNormkredit' => self::PRICE_PER_NORMKREDIT,
        ]);
    }

    // ── Join via share ID: pay + join ──────────────────────

    public function joinViaShareId(Request $request, string $shareId): RedirectResponse
    {
        $group = Group::where('share_id', $shareId)->firstOrFail();

        if ((int) $group->owner_id === (int) Auth::id()) {
            return redirect()->route('chat.show', $group);
        }

        $existing = GroupMember::where('group_id', $group->id)
            ->where('user_id', Auth::id())
            ->where('status', 'active')
            ->first();

        if ($existing) {
            return redirect()->route('chat.show', $group)
                ->with('info', 'Anda sudah menjadi member group ini.');
        }

        $request->validate([
            'password' => 'required|string',
            'patungan_amount' => ['required', 'integer', 'min:' . self::MIN_PATUNGAN],
        ]);

        if (! Hash::check($request->input('password'), $group->password_hash)) {
            return back()->withErrors(['password' => 'Password grup salah.'])->withInput();
        }

        $user = Auth::user();
        $patunganAmount = (int) $request->input('patungan_amount');
        $totalPay = $patunganAmount + self::SEAT_PRICE;

        // Calculate normkredit from patungan
        $normkredit = $patunganAmount / self::PRICE_PER_NORMKREDIT;
        $tokenAmount = (int) ($normkredit * self::TOKENS_PER_CREDIT);

        // Add tokens to group
        $groupToken = GroupToken::firstOrCreate(
            ['group_id' => $group->id],
            ['total_tokens' => 0, 'used_tokens' => 0, 'remaining_tokens' => 0]
        );
        $groupToken->addTokens($tokenAmount);

        // Record contribution
        GroupTokenContribution::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'source' => 'patungan',
            'token_amount' => $tokenAmount,
            'price_paid' => $totalPay,
            'payment_reference' => 'JOIN-' . strtoupper(\Illuminate\Support\Str::random(10)),
        ]);

        // Add seat to subscription
        $subscription = $group->subscription;
        if ($subscription) {
            $subscription->included_seats = (int) $subscription->included_seats + 1;
            $subscription->save();
        }

        // Join as member
        $memberRole = Role::firstWhere('key', 'member')
            ?: Role::firstWhere('key', 'admin')
            ?: Role::firstWhere('key', 'owner');

        GroupMember::updateOrCreate(
            ['group_id' => $group->id, 'user_id' => $user->id],
            ['role_id' => $memberRole?->id, 'status' => 'active', 'joined_at' => now()]
        );

        AuditLog::create([
            'group_id' => $group->id,
            'actor_id' => $user->id,
            'action' => 'group.member_joined',
            'target_type' => Group::class,
            'target_id' => $group->id,
            'metadata_json' => [
                'patungan' => $patunganAmount,
                'seat_fee' => self::SEAT_PRICE,
                'normkredit' => $normkredit,
            ],
            'created_at' => now(),
        ]);

        return redirect()->route('chat.show', $group)
            ->with('success', 'Berhasil bergabung ke group "' . $group->name . '"! ' . number_format($normkredit, 0) . ' normkredit ditambahkan ke grup.');
    }

    public function promoteMember(Request $request, Group $group, GroupMember $member): RedirectResponse
    {
        $this->authorize('promoteMember', $group);

        if ((int) $member->group_id !== (int) $group->id) {
            abort(404);
        }

        if ((int) $member->user_id === (int) $group->owner_id) {
            return back()->withErrors(['member' => 'Owner tidak dapat diubah rolenya dari menu ini.']);
        }

        $validated = $request->validate([
            'role' => ['required', 'in:admin,member'],
        ]);

        $targetRole = Role::firstWhere('key', $validated['role']);
        if (! $targetRole) {
            return back()->withErrors(['member' => 'Role tujuan tidak ditemukan.']);
        }

        $member->role_id = $targetRole->id;
        $member->save();

        AuditLog::create([
            'group_id' => $group->id,
            'actor_id' => Auth::id(),
            'action' => 'group.member_role_changed',
            'target_type' => GroupMember::class,
            'target_id' => $member->id,
            'metadata_json' => ['role' => $validated['role']],
            'created_at' => now(),
        ]);

        return back()->with('success', 'Role member berhasil diperbarui.');
    }

    public function removeMember(Group $group, GroupMember $member): RedirectResponse
    {
        $this->authorize('manageMembers', $group);

        if ((int) $member->group_id !== (int) $group->id) {
            abort(404);
        }

        if ((int) $member->user_id === (int) $group->owner_id) {
            return back()->withErrors(['member' => 'Owner tidak dapat dihapus dari group.']);
        }

        $member->delete();

        AuditLog::create([
            'group_id' => $group->id,
            'actor_id' => Auth::id(),
            'action' => 'group.member_removed',
            'target_type' => GroupMember::class,
            'target_id' => $member->id,
            'created_at' => now(),
        ]);

        return back()->with('success', 'Member berhasil dihapus dari group.');
    }
}
