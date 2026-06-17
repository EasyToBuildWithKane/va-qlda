<?php

namespace App\Http\Requests\Contract;

use App\Models\Contract;
use App\Support\Enums\ContractBillingCycle;
use App\Support\Enums\ContractPaymentStatus;
use App\Support\Enums\ContractStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Contract::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'links' => ['nullable', 'array', 'max:20'],
            'links.*' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string', 'max:5000'],
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            'category_id' => ['nullable', 'integer', 'exists:contract_categories,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'root_contract_id' => ['nullable', 'integer', 'exists:contracts,id'],
            'using_unit' => ['nullable', 'string', 'max:255'],
            'owner_id' => ['nullable', 'integer', 'exists:employees,id'],
            'manager_id' => ['nullable', 'integer', 'exists:employees,id'],

            'currency' => ['nullable', 'string', 'max:8'],
            'unit_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'monthly_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'annual_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'lifecycle_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'payment_status' => ['nullable', Rule::in(ContractPaymentStatus::values())],
            'billing_cycle' => ['nullable', Rule::in(ContractBillingCycle::values())],

            'signed_date' => ['nullable', 'date'],
            'effective_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:effective_date'],
            'auto_renew' => ['boolean'],
            'renewal_term_months' => ['nullable', 'integer', 'min:0', 'max:600'],
            'notice_period_days' => ['nullable', 'integer', 'min:0', 'max:3650'],

            'status' => ['nullable', Rule::in(ContractStatus::values())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tên dịch vụ.',
            'expiry_date.after_or_equal' => 'Ngày hết hạn phải sau hoặc bằng ngày hiệu lực.',
        ];
    }
}
