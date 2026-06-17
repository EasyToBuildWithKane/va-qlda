<?php

namespace App\Http\Requests\Contract\Concerns;

trait NormalizesContractInput
{
    protected function prepareForValidation(): void
    {
        $nullableInt = [
            'vendor_id',
            'category_id',
            'department_id',
            'root_contract_id',
            'owner_id',
            'manager_id',
        ];

        foreach ($nullableInt as $key) {
            $value = $this->input($key);
            if ($value === '' || $value === 'null') {
                $this->merge([$key => null]);
            }
        }

        foreach (['signed_date', 'effective_date', 'expiry_date'] as $key) {
            if ($this->input($key) === '') {
                $this->merge([$key => null]);
            }
        }

        if ($this->input('billing_cycle') === '') {
            $this->merge(['billing_cycle' => null]);
        }
    }
}
