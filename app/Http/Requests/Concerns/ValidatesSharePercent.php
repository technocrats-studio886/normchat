<?php

namespace App\Http\Requests\Concerns;

use App\Services\Credits\Funding;

/**
 * Field `share_percent`: berapa persen Normkredit yang dibeli masuk ke kredit bersama
 * (sisanya menjadi kredit terlindungi milik pembeli). Default 50%.
 */
trait ValidatesSharePercent
{
    protected function sharePercentRules(): array
    {
        return ['nullable', 'integer', 'min:0', 'max:100'];
    }

    public function sharePercent(): float
    {
        $value = $this->validated('share_percent');

        return $value === null ? Funding::DEFAULT_SHARE_PERCENT : (float) $value;
    }
}
