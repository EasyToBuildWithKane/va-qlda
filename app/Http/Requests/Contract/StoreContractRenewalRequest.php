<?php

namespace App\Http\Requests\Contract;

use Illuminate\Foundation\Http\FormRequest;

class StoreContractRenewalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('contract')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'effective_date' => ['nullable', 'date'],
            'new_expiry' => ['required', 'date'],
            'new_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'note' => ['nullable', 'string', 'max:2000'],
            'links' => ['nullable', 'array', 'max:20'],
            'links.*' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'new_expiry.required' => 'Vui lòng chọn ngày hết hạn mới.',
        ];
    }
}
