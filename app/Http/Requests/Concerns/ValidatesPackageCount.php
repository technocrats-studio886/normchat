<?php

namespace App\Http\Requests\Concerns;

use App\Services\NormkreditPricing;

/** Field `packages`: jumlah paket Normkredit (patungan / top-up) yang dibeli. */
trait ValidatesPackageCount
{
    protected function packageRules(): array
    {
        return ['required', 'integer', 'min:1', 'max:' . app(NormkreditPricing::class)->maxPackagesPerPayment()];
    }

    public function packages(): int
    {
        return (int) $this->validated('packages');
    }
}
