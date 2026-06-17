<?php

namespace App\Http\Requests\Contract;

use App\Models\Contract;
use App\Support\Enums\ContractBillingCycle;
use App\Support\Enums\ContractPaymentStatus;
use App\Support\Enums\ContractReviewRecommendation;
use App\Support\Enums\ContractStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Nhập hợp đồng từ Excel — mirror validate phía client (max 200 dòng mỗi sheet).
 * Payload đa-sheet: `rows` (hợp đồng), `finances` (chi phí), `reviews` (đánh giá)
 * — finances/reviews liên kết hợp đồng theo `code` (Mã HĐ) trong cùng transaction.
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
            'rows.*.code' => ['nullable', 'string', 'max:40'],
            'rows.*.name' => ['required', 'string', 'max:255'],
            'rows.*.description' => ['nullable', 'string', 'max:5000'],
            'rows.*.vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            'rows.*.vendor_name' => ['nullable', 'string', 'max:255'],
            'rows.*.category_id' => ['nullable', 'integer', 'exists:contract_categories,id'],
            'rows.*.category_name' => ['nullable', 'string', 'max:255'],
            'rows.*.department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'rows.*.using_unit' => ['nullable', 'string', 'max:255'],
            'rows.*.owner_id' => ['nullable', 'integer', 'exists:employees,id'],
            'rows.*.manager_id' => ['nullable', 'integer', 'exists:employees,id'],
            'rows.*.annual_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'rows.*.lifecycle_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'rows.*.payment_status' => ['nullable', Rule::in(ContractPaymentStatus::values())],
            'rows.*.billing_cycle' => ['nullable', Rule::in(ContractBillingCycle::values())],
            'rows.*.effective_date' => ['nullable', 'date'],
            'rows.*.expiry_date' => ['nullable', 'date'],
            'rows.*.status' => ['nullable', Rule::in(ContractStatus::values())],
            'rows.*.links' => ['nullable', 'array', 'max:20'],
            'rows.*.links.*' => ['nullable', 'string', 'max:1000'],

            'finances' => ['nullable', 'array', 'max:400'],
            'finances.*.code' => ['required', 'string', 'max:40'],
            'finances.*.used_date' => ['nullable', 'date'],
            'finances.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'finances.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'finances.*.init_fee' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'finances.*.maintenance_fee' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'finances.*.term_months' => ['nullable', 'integer', 'min:0', 'max:600'],
            'finances.*.total' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'finances.*.renewal_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],

            'reviews' => ['nullable', 'array', 'max:400'],
            'reviews.*.code' => ['required', 'string', 'max:40'],
            'reviews.*.reviewer_email' => ['nullable', 'string', 'max:255'],
            'reviews.*.reviewed_at' => ['nullable', 'date'],
            'reviews.*.service_quality' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'reviews.*.sla' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'reviews.*.speed' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'reviews.*.price_satisfaction' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'reviews.*.stability' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'reviews.*.attitude' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'reviews.*.total_score' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'reviews.*.recommendation' => ['nullable', Rule::in(ContractReviewRecommendation::values())],
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
            'finances.*.code.required' => 'Dòng chi phí thiếu Mã HĐ để liên kết.',
            'reviews.*.code.required' => 'Dòng đánh giá thiếu Mã HĐ để liên kết.',
        ];
    }
}
