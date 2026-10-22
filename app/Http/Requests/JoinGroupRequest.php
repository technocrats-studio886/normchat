<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesPackageCount;
use App\Http\Requests\Concerns\ValidatesSharePercent;
use Illuminate\Foundation\Http\FormRequest;

class JoinGroupRequest extends FormRequest
{
    use ValidatesPackageCount;
    use ValidatesSharePercent;

    public function rules(): array
    {
        return [
            'password' => ['required', 'string'],
            'packages' => $this->packageRules(),
            'share_percent' => $this->sharePercentRules(),
        ];
    }
}
