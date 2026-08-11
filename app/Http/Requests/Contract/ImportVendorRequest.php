<?php

namespace App\Http\Requests\Contract;

use App\Models\Vendor;
use Illuminate\Foundation\Http\FormRequest;

class ImportVendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Vendor::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'overwrite' => ['sometimes', 'boolean'],
            'rows' => ['required', 'array', 'max:200'],
            'rows.*.name' => ['required', 'string', 'max:255'],
            'rows.*.code' => ['nullable', 'string', 'max:40'],
            'rows.*.tax_code' => ['nullable', 'string', 'max:40'],
            'rows.*.contact_name' => ['nullable', 'string', 'max:255'],
            'rows.*.email' => ['nullable', 'email', 'max:255'],
            'rows.*.phone' => ['nullable', 'string', 'max:40'],
            'rows.*.website' => ['nullable', 'string', 'max:255'],
            'rows.*.address' => ['nullable', 'string', 'max:255'],
            'rows.*.rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'rows.*.notes' => ['nullable', 'string', 'max:2000'],
            'rows.*.is_active' => ['sometimes', 'boolean'],
            'rows.*.category_ids' => ['nullable', 'array', 'max:50'],
            'rows.*.category_ids.*' => ['integer', 'distinct', 'exists:contract_categories,id'],
            'rows.*.service_categories' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rows.max' => 'Mỗi lần nhập tối đa 200 dòng.',
            'rows.*.name.required' => 'Mỗi dòng phải có tên nhà cung cấp.',
            'rows.*.email.email' => 'Email không hợp lệ.',
            'rows.*.rating.min' => 'Đánh giá sao từ 1 đến 5.',
            'rows.*.rating.max' => 'Đánh giá sao từ 1 đến 5.',
        ];
    }
}
