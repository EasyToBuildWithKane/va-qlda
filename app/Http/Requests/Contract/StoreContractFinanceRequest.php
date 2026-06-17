<?php

namespace App\Http\Requests\Contract;

use App\Models\Contract;
use Illuminate\Foundation\Http\FormRequest;

class StoreContractFinanceRequest extends FormRequest
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
        return [
            'used_date' => ['nullable', 'date'],
            'term_months' => ['nullable', 'integer', 'min:0', 'max:600'],
            'quantity' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'unit_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'maintenance_fee' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'init_fee' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'renewal_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'total' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'unit_price.min' => 'Đơn giá không được âm.',
            'maintenance_fee.min' => 'Phí duy trì không được âm.',
            'init_fee.min' => 'Phí khởi tạo không được âm.',
            'total.min' => 'Tổng tiền không được âm.',
            'term_months.max' => 'Thời hạn tối đa 600 tháng (50 năm).',
        ];
    }
}
