<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ExchangeRate;
use App\Models\Group;
use App\Models\GroupContextSummary;
use App\Models\GroupContextTopic;
use App\Models\Message;
use App\Models\User;
use App\Services\Ai\SpendGuard;
use App\Services\NormkreditPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * llm-rules bagian 3 dan 4.3 dari sisi superadmin: halaman Biaya OpenAI, kurs harian manual (+ selisih
 * kartu), anggaran bulanan, saldo OpenAI + cadangan. Ingatan Normi berisi teks chat, jadi tidak tampil.
 */
class LlmAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $boss;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('normchat.superadmin_emails', ['boss@normchat.test']);
        config()->set('normchat.usd_to_idr', 17950);
        $this->boss = User::factory()->create(['email' => 'boss@normchat.test', 'name' => 'Boss']);
    }

    public function test_page_opens_for_superadmin_only_and_never_shows_chat_memory(): void
    {
        $owner = User::factory()->create(['name' => 'Noel']);
        $group = Group::create(['name' => 'Kelas BD', 'owner_id' => $owner->id, 'password_hash' => Hash::make('1234'), 'status' => 'active']);
        $message = Message::create(['group_id' => $group->id, 'sender_type' => 'user', 'sender_id' => $owner->id, 'content' => 'rahasia grup']);
        GroupContextSummary::forGroup($group->id)->update(['summary' => 'ringkasan rahasia grup', 'covered_until_message_id' => $message->id]);
        GroupContextTopic::create(['group_id' => $group->id, 'title' => 'topik rahasia', 'summary' => 'isi topik rahasia',
            'first_message_id' => $message->id, 'last_message_id' => $message->id, 'last_activity_at' => now()]);

        $this->actingAs($owner)->get(route('superadmin.openai'))->assertForbidden();
        foreach ([route('superadmin.openai'), route('superadmin.finance'), route('superadmin.groups.show', $group)] as $url) {
            $this->actingAs($this->boss)->get($url)->assertOk()->assertDontSee('rahasia');
        }
    }

    public function test_daily_rate_is_saved_with_card_margin_and_audited(): void
    {
        $this->actingAs($this->boss)->post(route('superadmin.openai.rate'), ['market_rate' => 18158])
            ->assertRedirect(route('superadmin.openai'));

        $rate = ExchangeRate::latest('id')->firstOrFail();
        $this->assertSame(18158.0, (float) $rate->market_rate);
        $this->assertSame(round(18158 * 1.02, 2), (float) $rate->applied_rate);
        $this->assertSame($this->boss->id, $rate->set_by);
        $this->assertSame(round(18158 * 1.02, 2), app(NormkreditPricing::class)->usdToIdr());
        $this->assertTrue(AuditLog::where('action', 'admin.set_exchange_rate')->exists());
    }

    public function test_rate_outside_sane_range_is_rejected(): void
    {
        foreach ([1815, 181580, 'abc', ''] as $typo) {
            $this->actingAs($this->boss)->post(route('superadmin.openai.rate'), ['market_rate' => $typo])->assertSessionHasErrors('market_rate');
        }
        $this->assertSame(0, ExchangeRate::count());
    }

    public function test_rate_jump_above_ten_percent_needs_confirmation(): void
    {
        app(NormkreditPricing::class)->setExchangeRate(18000, $this->boss->id);

        $this->actingAs($this->boss)->post(route('superadmin.openai.rate'), ['market_rate' => 20500])->assertSessionHasErrors('market_rate');
        $this->assertSame(1, ExchangeRate::count());

        $this->actingAs($this->boss)->post(route('superadmin.openai.rate'), ['market_rate' => 20500, 'confirm_jump' => 1])->assertSessionHasNoErrors();
        $this->assertSame(20500.0, (float) ExchangeRate::latest('id')->value('market_rate'));

        // Perubahan kecil tidak perlu konfirmasi.
        $this->actingAs($this->boss)->post(route('superadmin.openai.rate'), ['market_rate' => 20600])->assertSessionHasNoErrors();
    }

    public function test_budget_and_balance_are_saved_and_reserve_must_be_below_balance(): void
    {
        $this->actingAs($this->boss)->post(route('superadmin.openai.budget'), ['monthly_budget_usd' => 50])->assertSessionHasNoErrors();
        $this->actingAs($this->boss)->post(route('superadmin.openai.balance'), ['balance_usd' => 20, 'reserve_usd' => 25])->assertSessionHasErrors('reserve_usd');
        $this->actingAs($this->boss)->post(route('superadmin.openai.balance'), ['balance_usd' => 20, 'reserve_usd' => 5])->assertSessionHasNoErrors();

        $status = app(SpendGuard::class)->status();
        $this->assertEquals([50, 20, 5], [$status['budget'], $status['balance'], $status['reserve']]);
        $this->assertTrue(AuditLog::where('action', 'admin.set_openai_budget')->exists());
    }

    public function test_finance_shows_cost_per_answer_and_weekly_recall_failures(): void
    {
        $owner = User::factory()->create();
        $group = Group::create(['name' => 'Kelas BD', 'owner_id' => $owner->id, 'password_hash' => Hash::make('1234'), 'status' => 'active']);
        foreach ([true, false, false, false] as $failed) {
            \App\Models\AiUsageLog::create([
                'group_id' => $group->id, 'kind' => 'chat', 'model' => 'gpt-6-luna', 'provider_cost_usd' => 0.001,
                'usd_to_idr' => 18000, 'provider_cost_idr' => 18, 'multiplier' => 1.5, 'charged_credits' => 0.25, 'balance_after' => 100,
                'breakdown_json' => ['context' => ['searched' => $failed, 'recall_failed' => $failed]],
            ]);
        }
        // Log lama tanpa penanda tidak dihitung gagal.
        \App\Models\AiUsageLog::create([
            'group_id' => $group->id, 'kind' => 'chat', 'model' => 'gpt-6-luna', 'provider_cost_usd' => 0.001,
            'usd_to_idr' => 18000, 'provider_cost_idr' => 18, 'multiplier' => 1.5, 'charged_credits' => 0.25, 'balance_after' => 100,
            'breakdown_json' => ['context' => ['retrieved' => 2]],
        ]);

        $this->actingAs($this->boss)->get(route('superadmin.finance'))
            ->assertOk()
            ->assertSee('Biaya per jawaban')
            ->assertSee('Rp18,00')
            ->assertSee('Gagal mengingat')
            ->assertSee('20,0%')
            ->assertSee('Pesan atau lampiran terlalu panjang');
    }

    public function test_non_superadmin_cannot_change_rate_budget_or_balance(): void
    {
        $member = User::factory()->create();
        $this->actingAs($member)->post(route('superadmin.openai.rate'), ['market_rate' => 18000])->assertForbidden();
        $this->actingAs($member)->post(route('superadmin.openai.budget'), ['monthly_budget_usd' => 5])->assertForbidden();
        $this->actingAs($member)->post(route('superadmin.openai.balance'), ['balance_usd' => 5, 'reserve_usd' => 1])->assertForbidden();
        $this->assertSame(0, ExchangeRate::count());
    }
}
