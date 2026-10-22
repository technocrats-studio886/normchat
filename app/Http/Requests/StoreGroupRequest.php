<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesSharePercent;
use Illuminate\Foundation\Http\FormRequest;

class StoreGroupRequest extends FormRequest
{
    use ValidatesSharePercent;

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'password' => ['required', 'string', 'min:4', 'max:100'],
            'approval_enabled' => ['nullable'],
            'share_percent' => $this->sharePercentRules(),
        ];
    }
}
