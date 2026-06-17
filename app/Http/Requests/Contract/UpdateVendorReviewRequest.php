<?php

namespace App\Http\Requests\Contract;

class UpdateVendorReviewRequest extends StoreVendorReviewRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('vendor')) ?? false;
    }
}
