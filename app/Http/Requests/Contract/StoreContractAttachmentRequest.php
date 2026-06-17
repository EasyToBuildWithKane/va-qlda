<?php

namespace App\Http\Requests\Contract;

use App\Support\Enums\ContractAttachmentCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContractAttachmentRequest extends FormRequest
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
            'category' => ['required', Rule::in(ContractAttachmentCategory::values())],
            'title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'external_url' => ['nullable', 'string', 'max:2048'],
            'files' => ['nullable', 'array', 'max:10'],
            'files.*' => [
                'file',
                'max:20480', // 20MB
                'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,zip,txt',
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $hasFiles = ! empty($this->file('files'));
            $hasLink = filled($this->input('external_url'));
            if (! $hasFiles && ! $hasLink) {
                $validator->errors()->add('files', 'Chọn ít nhất một file hoặc nhập link.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category.required' => 'Vui lòng chọn loại hồ sơ.',
            'external_url.url' => 'Link không hợp lệ.',
            'files.*.mimes' => 'Định dạng file không được hỗ trợ.',
            'files.*.max' => 'File tối đa 20MB.',
        ];
    }
}
