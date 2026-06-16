<?php

namespace App\Http\Requests\Contract;

use App\Models\Contract;
use App\Support\Enums\ContractPaymentStatus;
use App\Support\Enums\ContractStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Nhập hợp đồng từ Excel — mirror validate phía client (max 200 dòng).
 */
class ImportContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('import', Contract::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rows' => ['required', 'array', 'min:1', 'max:200'],
            'rows.*.name' => ['required', 'string', 'max:255'],
            'rows.*.vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            'rows.*.category_id' => ['nullable', 'integer', 'exists:contract_categories,id'],
            'rows.*.using_unit' => ['nullable', 'string', 'max:255'],
            'rows.*.owner_id' => ['nullable', 'integer', 'exists:employees,id'],
            'rows.*.manager_id' => ['nullable', 'integer', 'exists:employees,id'],
            'rows.*.currency' => ['nullable', 'string', 'max:8'],
            'rows.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'rows.*.monthly_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'rows.*.annual_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'rows.*.lifecycle_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'rows.*.payment_status' => ['nullable', Rule::in(ContractPaymentStatus::values())],
            'rows.*.signed_date' => ['nullable', 'date'],
            'rows.*.effective_date' => ['nullable', 'date'],
            'rows.*.expiry_date' => ['nullable', 'date'],
            'rows.*.status' => ['nullable', Rule::in(ContractStatus::values())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rows.max' => 'Chỉ nhập tối đa 200 hợp đồng mỗi lần.',
            'rows.*.name.required' => 'Thiếu tên hợp đồng ở một số dòng.',
        ];
    }
}
