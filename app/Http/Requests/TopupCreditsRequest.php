<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesPackageCount;
use App\Http\Requests\Concerns\ValidatesSharePercent;
use Illuminate\Foundation\Http\FormRequest;

class TopupCreditsRequest extends FormRequest
{
    use ValidatesPackageCount;
    use ValidatesSharePercent;

    public function rules(): array
    {
        return [
            'group_id' => ['required', 'exists:groups,id'],
            'packages' => $this->packageRules(),
            'share_percent' => $this->sharePercentRules(),
        ];
    }
}
