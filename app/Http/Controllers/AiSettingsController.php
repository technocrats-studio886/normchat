<?php

namespace App\Http\Controllers;

use App\Models\AiModel;
use App\Models\AuditLog;
use App\Models\Group;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Kategori AI dan persona Normi AI untuk satu grup. */
class AiSettingsController extends Controller
{
    public function edit(Request $request, Group $group): View
    {
        $this->authorize('view', $group);

        return view('settings.ai-persona', [
            'group' => $group,
            'canManageAiPersona' => $request->user()->can('manageAiPersona', $group),
            'aiModels' => AiModel::active()->get(),
            'currentAiModel' => $group->resolvedAiModel(),
        ]);
    }

    public function updateModel(Request $request, Group $group): RedirectResponse
    {
        $this->authorize('manageAiPersona', $group);

        $modelId = $request->validate([
            'ai_model' => ['required', 'string', Rule::exists('ai_models', 'model_id')->where('is_active', true)],
        ])['ai_model'];

        $group->update(['ai_model' => $modelId]);
        AuditLog::record('settings.update_ai_model', $request->user()->id, $group, ['ai_model' => $modelId]);

        return back()->with('success', 'Model berhasil disimpan.');
    }

    public function updatePersona(Request $request, Group $group): RedirectResponse
    {
        $this->authorize('manageAiPersona', $group);

        $validated = $request->validate([
            'ai_persona_style' => ['nullable', 'string', 'max:1000'],
            'ai_persona_guardrails' => ['nullable', 'string', 'max:1000'],
        ]);

        $group->update($validated);
        AuditLog::record('settings.update_ai_persona', $request->user()->id, $group, ['updated_fields' => array_keys($validated)]);

        return back()->with('success', 'Kepribadian Normi AI disimpan.');
    }
}
