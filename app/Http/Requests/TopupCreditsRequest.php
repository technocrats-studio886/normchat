<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesPackageCount;
use Illuminate\Foundation\Http\FormRequest;

class TopupCreditsRequest extends FormRequest
{
    use ValidatesPackageCount;

    public function rules(): array
    {
        return [
            'group_id' => ['required', 'exists:groups,id'],
            'packages' => $this->packageRules(),
        ];
    }
}
