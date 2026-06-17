<?php

namespace App\Http\Requests\Contract;

use App\Models\Contract;
use App\Support\Enums\ContractReviewRecommendation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContractReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $contract = $this->route('contract');

        return $contract instanceof Contract
            && ($this->user()?->can('update', $contract) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $criterion = ['nullable', 'numeric', 'min:0', 'max:10'];

        return [
            'reviewed_at' => ['nullable', 'date'],
            'reviewer_id' => ['required', 'integer', 'exists:employees,id'],
            'service_quality' => $criterion,
            'sla' => $criterion,
            'speed' => $criterion,
            'price_satisfaction' => $criterion,
            'stability' => $criterion,
            'attitude' => $criterion,
            'recommendation' => ['nullable', Rule::in(ContractReviewRecommendation::values())],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reviewer_id.required' => 'Vui lòng chọn người đánh giá.',
            'reviewer_id.exists' => 'Nhân sự đánh giá không hợp lệ.',
            'service_quality.max' => 'Điểm tối đa là 10.',
            'sla.max' => 'Điểm tối đa là 10.',
            'speed.max' => 'Điểm tối đa là 10.',
            'price_satisfaction.max' => 'Điểm tối đa là 10.',
            'stability.max' => 'Điểm tối đa là 10.',
            'attitude.max' => 'Điểm tối đa là 10.',
        ];
    }
}
