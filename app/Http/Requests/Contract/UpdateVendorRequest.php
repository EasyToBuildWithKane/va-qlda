<?php

namespace App\Http\Requests\Contract;

use App\Support\Enums\VendorCooperationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('vendor')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'tax_code' => ['nullable', 'string', 'max:40'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            'cooperation_status' => ['required', Rule::in(VendorCooperationStatus::values())],
            'category_ids' => ['nullable', 'array', 'max:50'],
            'category_ids.*' => ['integer', 'distinct', 'exists:contract_categories,id'],
            'category_names' => ['nullable', 'array', 'max:50'],
            'category_names.*' => ['string', 'max:255', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tên nhà cung cấp.',
            'email.email' => 'Email không hợp lệ.',
            'cooperation_status.required' => 'Vui lòng chọn trạng thái hợp tác.',
            'cooperation_status.in' => 'Trạng thái hợp tác không hợp lệ.',
            'category_ids.*.exists' => 'Nhóm dịch vụ không hợp lệ.',
            'category_ids.max' => 'Mỗi nhà cung cấp tối đa 50 loại dịch vụ.',
            'category_names.max' => 'Mỗi lần tối đa 50 loại dịch vụ mới.',
            'category_names.*.max' => 'Tên loại dịch vụ tối đa 255 ký tự.',
        ];
    }
}
