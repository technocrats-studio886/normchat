<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesPackageCount;
use Illuminate\Foundation\Http\FormRequest;

class JoinGroupRequest extends FormRequest
{
    use ValidatesPackageCount;

    public function rules(): array
    {
        return [
            'password' => ['required', 'string'],
            'packages' => $this->packageRules(),
        ];
    }
}
