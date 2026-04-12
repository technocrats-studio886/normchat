<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupToken;
use App\Models\GroupTokenContribution;
use App\Models\Role;
use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class GroupController extends Controller
{
    private const INCLUDED_CREDITS = 12; // 12 normkredit included per group creation
    private const TOKENS_PER_CREDIT = 2_500;
    private const PLAN_PRICE = 30_000;
    private const DEFAULT_AI_PROVIDER = 'openai';
    private const DEFAULT_AI_MODEL = 'gpt-5';

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
        return view('groups.create', [
            'planPrice' => self::PLAN_PRICE,
            'includedCredits' => self::INCLUDED_CREDITS,
            'tokensPerCredit' => self::TOKENS_PER_CREDIT,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'password' => ['required', 'string', 'min:4', 'max:100'],
            'approval_enabled' => ['nullable'],
        ]);

        $user = Auth::user();

        $group = Group::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'owner_id' => $user->id,
            'password_hash' => Hash::make($validated['password']),
            'approval_enabled' => (bool) ($validated['approval_enabled'] ?? false),
            'ai_provider' => self::DEFAULT_AI_PROVIDER,
            'ai_model' => self::DEFAULT_AI_MODEL,
        ]);

        $ownerRoleId = $this->ensureRoleId('owner', 'Owner', 'Group owner');
        GroupMember::updateOrCreate(
            ['group_id' => $group->id, 'user_id' => $user->id],
            ['role_id' => $ownerRoleId, 'status' => 'active', 'joined_at' => now()]
        );

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
                'ai_provider' => self::DEFAULT_AI_PROVIDER,
                'ai_model' => self::DEFAULT_AI_MODEL,
            ],
            'created_at' => now(),
        ]);

        return redirect()->route('chat.show', $group)->with('success', 'Group "' . $group->name . '" berhasil dibuat!');
    }

    private const SEAT_PRICE = 4000;
    private const MIN_PATUNGAN = 30_000;
    private const PRICE_PER_NORMKREDIT = 2_500;

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

    // ── Join via share ID: instant join + simulated contribution ───────

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
            'patungan_amount' => ['nullable', 'integer', 'min:0'],
        ]);

        if (! Hash::check($request->input('password'), $group->password_hash)) {
            return back()->withErrors(['password' => 'Password grup salah.'])->withInput();
        }

        $user = Auth::user();
        $patunganAmount = max((int) $request->input('patungan_amount', self::MIN_PATUNGAN), 0);

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
            'price_paid' => 0,
            'payment_reference' => 'SIM-JOIN-' . strtoupper(\Illuminate\Support\Str::random(8)),
        ]);

        // Add seat to subscription
        $subscription = $group->subscription;
        if ($subscription) {
            $subscription->included_seats = (int) $subscription->included_seats + 1;
            $subscription->save();
        }

        // Join as member
        $memberRoleId = $this->ensureRoleId('member', 'Member', 'Group member');

        GroupMember::updateOrCreate(
            ['group_id' => $group->id, 'user_id' => $user->id],
            ['role_id' => $memberRoleId, 'status' => 'active', 'joined_at' => now()]
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
                'simulated' => true,
            ],
            'created_at' => now(),
        ]);

        return redirect()->route('chat.show', $group)
            ->with('success', 'Berhasil bergabung ke group "' . $group->name . '"! ' . number_format($normkredit, 0) . ' normkredit langsung ditambahkan ke grup.');
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

        $targetRole = $validated['role'] === 'admin'
            ? Role::find($this->ensureRoleId('admin', 'Admin', 'Group administrator'))
            : Role::find($this->ensureRoleId('member', 'Member', 'Group member'));
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

    private function ensureRoleId(string $key, string $name, string $description): int
    {
        return (int) Role::firstOrCreate(
            ['key' => $key],
            ['name' => $name, 'description' => $description]
        )->id;
    }
}
