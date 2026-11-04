<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesPackageCount;
use App\Models\Group;
use Illuminate\Foundation\Http\FormRequest;

class JoinGroupRequest extends FormRequest
{
    use ValidatesPackageCount;

    private ?Group $group = null;

    public function rules(): array
    {
        $group = $this->group();

        return [
            // Kata sandi diverifikasi di langkah sebelumnya (GroupController::verifyJoinPassword).
            // Masih ada slot anggota gratis: tanpa patungan.
            'packages' => $group && $group->freeSlotsLeft() > 0 ? ['nullable', 'integer', 'min:1'] : $this->packageRules(),
        ];
    }

    private function group(): ?Group
    {
        return $this->group ??= Group::where('share_id', (string) $this->route('shareId'))->first();
    }
}
