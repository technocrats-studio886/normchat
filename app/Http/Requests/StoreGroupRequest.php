<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGroupRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:' . \App\Models\Group::NAME_MAX],
            'description' => ['nullable', 'string', 'max:' . \App\Models\Group::DESCRIPTION_MAX],
            'password' => ['required', 'string', 'min:4', 'max:100'],
        ];
    }
}
