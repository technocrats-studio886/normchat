<?php

namespace App\Http\Controllers;

use App\Models\AiModel;
use App\Models\AiUsageLog;
use App\Models\AuditLog;
use App\Models\GroupTokenContribution;
use App\Services\NormkreditPricing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SuperadminController extends Controller
{
    public function index(NormkreditPricing $pricing): View
    {
        $since = now()->subDays(30);
        $aiModels = AiModel::query()->orderBy('sort_order')->orderBy('id')->get();

        $usage = AiUsageLog::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('count(*) as requests, coalesce(sum(charged_credits), 0) as charged, coalesce(sum(provider_cost_idr), 0) as cost_idr')
            ->first();

        $usageByModel = AiUsageLog::query()
            ->where('created_at', '>=', $since)
            ->groupBy('model')
            ->selectRaw('model, count(*) as requests, sum(charged_credits) as charged, sum(provider_cost_idr) as cost_idr')
            ->orderByDesc('requests')
            ->get();

        $sales = GroupTokenContribution::query()
            ->where('created_at', '>=', $since)
            ->where('price_paid', '>', 0)
            ->selectRaw('count(*) as payments, coalesce(sum(price_paid), 0) as revenue, coalesce(sum(credit_amount), 0) as credits')
            ->first();

        return view('superadmin.index', [
            'settings' => $pricing->settings(),
            'usdToIdr' => $pricing->usdToIdr(),
            'aiModels' => $aiModels,
            'sampleCredits' => $aiModels->mapWithKeys(fn (AiModel $aiModel) => [
                $aiModel->id => $pricing->creditsForCost($aiModel->costUsd(3000, 0, 500), $aiModel->multiplier),
            ]),
            'usage' => $usage,
            'usageByModel' => $usageByModel,
            'sales' => $sales,
            'rupiahPerCredit' => $pricing->rupiahPerCredit(),
        ]);
    }

    public function updateSettings(Request $request, NormkreditPricing $pricing): RedirectResponse
    {
        $validated = $request->validate([
            'rupiah_per_credit' => ['required', 'integer', 'min:1', 'max:100000'],
            'group_creation_credits' => ['required', 'integer', 'min:1'],
            'package_credits' => ['required', 'integer', 'min:1'],
            'max_packages_per_payment' => ['required', 'integer', 'min:1', 'max:100'],
            'qris_fee_percent' => ['required', 'numeric', 'min:0', 'max:10'],
            // < 1 berarti menjual pemakaian AI di bawah biaya OpenAI.
            'media_multiplier' => ['required', 'numeric', 'min:1', 'max:20'],
            'max_credits_per_request' => ['required', 'numeric', 'min:0'],
            'max_output_tokens' => ['required', 'integer', 'min:256', 'max:128000'],
            'daily_group_credit_limit' => ['required', 'numeric', 'min:0'],
        ]);

        $pricing->update($validated);
        $this->audit('superadmin.update_pricing', $validated);

        return back()->with('success', 'Pengaturan harga disimpan.');
    }

    public function storeModel(Request $request): RedirectResponse
    {
        $validated = $this->validateModel($request);

        DB::transaction(function () use ($validated) {
            $aiModel = AiModel::create($validated);
            $this->syncDefault($aiModel);
        });

        $this->audit('superadmin.create_ai_model', ['model_id' => $validated['model_id']]);

        return redirect()->route('superadmin.index')->with('success', 'Model ' . $validated['label'] . ' ditambahkan.');
    }

    public function editModel(AiModel $aiModel): View
    {
        return view('superadmin.model-edit', ['aiModel' => $aiModel]);
    }

    public function updateModel(Request $request, AiModel $aiModel): RedirectResponse
    {
        $validated = $this->validateModel($request, $aiModel);

        DB::transaction(function () use ($aiModel, $validated) {
            $aiModel->update($validated);
            $this->syncDefault($aiModel);
        });

        $this->audit('superadmin.update_ai_model', ['model_id' => $aiModel->model_id] + $validated);

        return redirect()->route('superadmin.index')->with('success', 'Model ' . $aiModel->label . ' diperbarui.');
    }

    private function validateModel(Request $request, ?AiModel $aiModel = null): array
    {
        $validated = $request->validate([
            'model_id' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:-]+$/', Rule::unique('ai_models', 'model_id')->ignore($aiModel?->id)],
            'label' => ['required', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:255'],
            'input_usd_per_mtok' => ['required', 'numeric', 'min:0'],
            'cached_input_usd_per_mtok' => ['nullable', 'numeric', 'min:0'],
            'output_usd_per_mtok' => ['required', 'numeric', 'min:0'],
            // < 1 berarti menjual pemakaian AI di bawah biaya OpenAI.
            'multiplier' => ['required', 'numeric', 'min:1', 'max:20'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'image_model' => ['required', Rule::in(array_keys((array) config('normchat.image_models')))],
            'image_model_precise' => ['nullable', Rule::in(array_keys((array) config('normchat.image_models')))],
        ]);

        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);
        $validated['is_active'] = $request->boolean('is_active');
        // Model default harus aktif.
        $validated['is_default'] = $validated['is_active'] && $request->boolean('is_default');

        return $validated;
    }

    /** Hanya satu model default. */
    private function syncDefault(AiModel $aiModel): void
    {
        if ($aiModel->is_default) {
            AiModel::query()->whereKeyNot($aiModel->id)->update(['is_default' => false]);
        }
    }

    private function audit(string $action, array $metadata): void
    {
        AuditLog::record($action, Auth::id(), null, $metadata);
    }
}
