<?php

namespace App\Http\Controllers;

use App\Models\AiModel;
use App\Models\AuditLog;
use App\Services\NormkreditPricing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SuperadminController extends Controller
{
    /** Superadmin › Pengaturan: katalog model AI dan harga Normkredit. */
    public function index(NormkreditPricing $pricing): View
    {
        $aiModels = AiModel::query()->orderBy('sort_order')->orderBy('id')->get();

        return view('superadmin.settings', [
            'settings' => $pricing->settings(),
            'usdToIdr' => $pricing->usdToIdr(),
            'aiModels' => $aiModels,
            'sampleCredits' => $aiModels->mapWithKeys(fn (AiModel $aiModel) => [
                $aiModel->id => $pricing->creditsForCost($aiModel->costUsd(3000, 0, 500), $aiModel->multiplier),
            ]),
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
        $this->audit('admin.update_pricing', $validated);

        return back()->with('success', 'Pengaturan harga disimpan.');
    }

    public function storeModel(Request $request): RedirectResponse
    {
        $validated = $this->validateModel($request);

        DB::transaction(function () use ($validated) {
            $aiModel = AiModel::create($validated);
            $this->syncDefault($aiModel);
        });

        $this->audit('admin.create_ai_model', ['model_id' => $validated['model_id']]);

        return redirect()->route('superadmin.settings')->with('success', 'Model ' . $validated['label'] . ' ditambahkan.');
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

        $this->audit('admin.update_ai_model', ['model_id' => $aiModel->model_id] + $validated);

        return redirect()->route('superadmin.settings')->with('success', 'Model ' . $aiModel->label . ' diperbarui.');
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
            // Kunci saldo (App\Services\Credits\ModelAccess), dalam kelipatan modal grup. Kosong = tanpa kunci.
            'unlock_multiple' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'lock_multiple' => ['nullable', 'numeric', 'min:0', 'max:20'],
        ]);

        if ((float) ($validated['unlock_multiple'] ?? 0) > 0) {
            if ((float) ($validated['lock_multiple'] ?? 0) > (float) $validated['unlock_multiple']) {
                throw ValidationException::withMessages(['lock_multiple' => 'Ambang terkunci tidak boleh lebih besar dari ambang terbuka.']);
            }
        } else {
            $validated['unlock_multiple'] = null;
            $validated['lock_multiple'] = null;
        }

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
