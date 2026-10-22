<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Group;
use App\Models\GroupTokenContribution;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfilePagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_storage_page_sums_attachments_per_group(): void
    {
        $user = User::factory()->create();
        $group = Group::create(['name' => 'Kelas BD', 'owner_id' => $user->id, 'password_hash' => Hash::make('1'), 'status' => 'active']);
        foreach ([['image/png', 1024], ['image/png', 512], ['application/pdf', 2048]] as [$mime, $size]) {
            Message::create(['group_id' => $group->id, 'sender_type' => 'user', 'sender_id' => $user->id, 'attachment_mime' => $mime, 'attachment_size' => $size]);
        }

        $this->actingAs($user)->get('/profile/storage')
            ->assertOk()
            ->assertSee('Kelas BD')
            ->assertSee('3,5 KB');

        $this->actingAs($user)->get('/profile')->assertOk()->assertSee('3,5 KB');
    }

    public function test_account_name_can_be_updated(): void
    {
        $user = User::factory()->create(['email' => 'noel@student.itk.ac.id', 'username' => null, 'provider_user_id' => null]);

        $this->actingAs($user)->get('/profile/account')->assertOk()->assertSee('noel');
        $this->actingAs($user)->post('/profile/account', ['name' => '  Noel Baru  '])->assertRedirect('/profile/account');

        $this->assertSame('Noel Baru', $user->fresh()->name);
    }

    public function test_history_filters_by_range_and_source(): void
    {
        $user = User::factory()->create();
        $group = Group::create(['name' => 'G', 'owner_id' => $user->id, 'password_hash' => Hash::make('1'), 'status' => 'active']);
        AuditLog::record('auth.connect', $user->id, $user, ['ip' => '1.1.1.1']);
        AuditLog::create(['actor_id' => $user->id, 'action' => 'auth.connect', 'metadata_json' => ['ip' => '9.9.9.9'], 'created_at' => now()->subMonths(2)]);
        foreach (['topup_midtrans' => 15105, 'patungan_midtrans' => 15105] as $source => $paid) {
            GroupTokenContribution::create(['group_id' => $group->id, 'user_id' => $user->id, 'source' => $source, 'credit_amount' => 125, 'price_paid' => $paid]);
        }

        $this->actingAs($user)->get('/profile/login-history?range=week')->assertOk()->assertSee('1.1.1.1')->assertDontSee('9.9.9.9');
        $this->actingAs($user)->get('/profile/login-history?range=all')->assertOk()->assertSee('9.9.9.9');

        $this->actingAs($user)->get('/profile/transactions?source=topup')->assertOk()->assertViewHas('totalCount', 1)->assertViewHas('totalSpentIdr', 30210);
    }

    public function test_legacy_profile_urls_redirect(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/profile/security')->assertRedirect('/profile');
    }
}
