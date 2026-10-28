<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupToken;
use App\Models\GroupTokenContribution;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\SocialLogin;
use App\Exceptions\AccountUnavailableException;
use App\Services\Credits\CreditWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Superadmin mengelola aplikasi: tidak memakai grup, mengelola grup/user/keuangan, semua tercatat. */
class SuperadminManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $boss;

    private User $owner;

    private User $member;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('normchat.superadmin_emails', ['boss@normchat.test']);
        $this->boss = User::factory()->create(['email' => 'boss@normchat.test', 'name' => 'Boss']);
        $this->owner = User::factory()->create(['name' => 'Budi', 'email' => 'budi@contoh.test']);
        $this->member = User::factory()->create(['name' => 'Sari', 'email' => 'sari@contoh.test']);
        $this->group = Group::create(['name' => 'Kelas BD', 'owner_id' => $this->owner->id, 'password_hash' => Hash::make('1234'), 'status' => 'active']);
        foreach ([[$this->owner, Role::OWNER], [$this->member, Role::MEMBER]] as [$user, $role]) {
            GroupMember::create(['group_id' => $this->group->id, 'user_id' => $user->id, 'role_id' => Role::forKey($role)->id, 'status' => 'active', 'joined_at' => now()]);
        }
        app(CreditWallet::class)->deposit($this->group, 100);
        Message::create(['group_id' => $this->group->id, 'sender_type' => 'user', 'sender_id' => $this->owner->id, 'content' => 'rahasia grup']);
    }

    // ── G1: superadmin tidak memakai grup ──────────────────

    public function test_superadmin_cannot_create_join_or_read_groups(): void
    {
        $this->actingAs($this->boss)->get(route('groups.index'))->assertRedirect(route('superadmin.index'));
        $this->actingAs($this->boss)->get(route('groups.create'))->assertRedirect(route('superadmin.index'));
        $this->actingAs($this->boss)->post(route('groups.store'), ['name' => 'X'])->assertRedirect(route('superadmin.index'));
        $this->actingAs($this->boss)->get(route('groups.join', $this->group->share_id ?? 'abc'))->assertRedirect(route('superadmin.index'));
        $this->actingAs($this->boss)->get(route('chat.show', $this->group))->assertForbidden();
        $this->actingAs($this->boss)->postJson(route('chat.store', $this->group), ['content' => 'halo'])->assertForbidden();
        $this->actingAs($this->boss)->get(route('settings.show', $this->group))->assertForbidden();
    }

    public function test_admin_pages_never_show_message_content(): void
    {
        foreach ([route('superadmin.index'), route('superadmin.groups.index'), route('superadmin.groups.show', $this->group), route('superadmin.users.show', $this->owner), route('superadmin.finance'), route('superadmin.audit'), route('superadmin.settings')] as $url) {
            $this->actingAs($this->boss)->get($url)->assertOk()->assertDontSee('rahasia grup');
        }
    }

    public function test_only_superadmin_opens_admin_pages(): void
    {
        $this->actingAs($this->owner)->get(route('superadmin.groups.index'))->assertForbidden();
        $this->actingAs($this->owner)->get(route('superadmin.csv', 'user'))->assertForbidden();
    }

    // ── G3: grup ──────────────────────────────────────────

    public function test_adjusting_credits_requires_a_reason_and_is_recorded(): void
    {
        $url = route('superadmin.groups.credits', $this->group);
        $this->actingAs($this->boss)->post($url, ['amount' => 50])->assertSessionHasErrors('reason');
        $this->actingAs($this->boss)->post($url, ['amount' => 50, 'reason' => 'Kompensasi gangguan'])->assertSessionHas('success');
        $this->actingAs($this->boss)->post($url, ['amount' => -20, 'reason' => 'Koreksi salah input'])->assertSessionHas('success');
        $this->actingAs($this->boss)->post($url, ['amount' => -1000, 'reason' => 'Terlalu banyak'])->assertSessionHasErrors('amount');

        $this->assertSame(130.0, GroupToken::forGroup($this->group->id)->credits);
        $this->assertSame(['Kompensasi gangguan', 'Koreksi salah input'], GroupTokenContribution::where('source', 'admin_adjustment')->orderBy('id')->pluck('note')->all());
        $this->assertSame(2, AuditLog::where('action', 'admin.adjust_credits')->count());
        $this->actingAs($this->owner)->get(route('settings.transactions', $this->group))->assertOk();
    }

    public function test_frozen_group_is_read_only_for_members(): void
    {
        $this->actingAs($this->boss)->post(route('superadmin.groups.freeze', $this->group))->assertSessionHas('success');
        $this->assertTrue($this->group->fresh()->isFrozen());

        $this->actingAs($this->member)->get(route('chat.show', $this->group))->assertOk()->assertSee('Grup ini dibekukan')->assertDontSee('data-chat-form', false);
        $this->actingAs($this->member)->postJson(route('chat.store', $this->group), ['content' => 'halo'])->assertStatus(423);

        $this->actingAs($this->boss)->post(route('superadmin.groups.freeze', $this->group));
        $this->assertFalse($this->group->fresh()->isFrozen());
        $this->assertSame(['admin.freeze_group', 'admin.unfreeze_group'], AuditLog::where('action', 'like', 'admin.%')->orderBy('id')->pluck('action')->all());
    }

    public function test_deleting_a_group_requires_typing_its_name(): void
    {
        $this->actingAs($this->boss)->delete(route('superadmin.groups.destroy', $this->group), ['confirm_name' => 'kelas bd'])->assertSessionHasErrors('confirm_name');
        $this->assertNotSoftDeleted($this->group);

        $this->actingAs($this->boss)->delete(route('superadmin.groups.destroy', $this->group), ['confirm_name' => 'Kelas BD'])->assertRedirect(route('superadmin.groups.index'));
        $this->assertSoftDeleted($this->group);
    }

    // ── G4: user ──────────────────────────────────────────

    public function test_blocking_ends_sessions_and_prevents_login(): void
    {
        $this->actingAs($this->boss)->post(route('superadmin.users.block', $this->member))->assertSessionHas('success');

        $this->actingAs($this->member->fresh())->get(route('groups.index'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->expectException(AccountUnavailableException::class);
        app(SocialLogin::class)->findOrCreate('google', 'g-1', 'sari@contoh.test', 'Sari');
    }

    public function test_superadmin_cannot_be_blocked_or_deleted(): void
    {
        $this->actingAs($this->boss)->post(route('superadmin.users.block', $this->boss))->assertForbidden();
        $this->actingAs($this->boss)->delete(route('superadmin.users.destroy', $this->boss), ['confirm_email' => 'boss@normchat.test'])->assertForbidden();
    }

    public function test_deleted_account_is_kept_in_database_and_can_be_restored(): void
    {
        $this->actingAs($this->boss)->delete(route('superadmin.users.destroy', $this->owner), ['confirm_email' => 'salah@x.test'])->assertSessionHasErrors('confirm_email');
        $this->actingAs($this->boss)->delete(route('superadmin.users.destroy', $this->owner), ['confirm_email' => 'budi@contoh.test'])->assertRedirect(route('superadmin.users.index'));

        $this->assertSoftDeleted($this->owner);
        $this->assertSoftDeleted($this->group, [], 'deleted_at');
        $this->assertSame('budi@contoh.test', User::withTrashed()->find($this->owner->id)->email, 'data tetap di database');
        $this->assertNull(User::find($this->owner->id), 'hilang dari aplikasi');
        $this->actingAs($this->boss)->get(route('superadmin.users.index'))->assertSee('Dihapus');

        $this->actingAs($this->boss)->post(route('superadmin.users.restore', $this->owner->id))->assertSessionHas('success');
        $this->assertNotSoftDeleted($this->owner);
        $this->assertNotSoftDeleted($this->group);
        $this->assertSame(['admin.delete_user', 'admin.restore_user'], AuditLog::where('action', 'like', 'admin.%_user')->orderBy('id')->pluck('action')->all());
    }

    public function test_deleted_account_cannot_sign_in_again(): void
    {
        $this->actingAs($this->boss)->delete(route('superadmin.users.destroy', $this->member), ['confirm_email' => 'sari@contoh.test']);
        $this->assertSame('account_deleted', GroupMember::where('user_id', $this->member->id)->value('status'));

        $this->expectException(AccountUnavailableException::class);
        app(SocialLogin::class)->findOrCreate('google', 'g-2', 'sari@contoh.test', 'Sari');
    }

    // ── G5–G7: keuangan, CSV, audit ───────────────────────

    public function test_finance_page_and_csv_exports(): void
    {
        GroupTokenContribution::create(['group_id' => $this->group->id, 'user_id' => $this->owner->id, 'source' => 'topup_midtrans', 'credit_amount' => 50, 'price_paid' => 25000, 'payment_reference' => 'INV-1']);

        $this->actingAs($this->boss)->get(route('superadmin.finance'))->assertOk()->assertSee('Rp25.000');
        foreach (['grup' => 'Kelas BD', 'user' => 'budi@contoh.test', 'transaksi' => 'INV-1', 'ai' => 'Model'] as $type => $expected) {
            $csv = $this->actingAs($this->boss)->get(route('superadmin.csv', $type))->assertOk()->streamedContent();
            $this->assertStringContainsString($expected, $csv);
        }
        $this->assertSame(4, AuditLog::where('action', 'admin.export_csv')->count());
    }

    public function test_audit_log_lists_admin_actions_and_has_no_delete_route(): void
    {
        $this->actingAs($this->boss)->post(route('superadmin.groups.freeze', $this->group));

        $this->actingAs($this->boss)->get(route('superadmin.audit'))->assertOk()->assertSee('Membekukan grup Kelas BD');
        $this->assertEmpty(collect(app('router')->getRoutes()->getRoutes())->filter(fn ($route) => str_contains($route->uri(), 'audit') && in_array('DELETE', $route->methods(), true)));
    }

    // ── G8 ────────────────────────────────────────────────

    public function test_members_can_report_a_problem(): void
    {
        $this->actingAs($this->member)->get(route('profile.show'))->assertSee('https://normi.canny.io/bug-report', false)->assertSee('Laporkan masalah');
    }
}
