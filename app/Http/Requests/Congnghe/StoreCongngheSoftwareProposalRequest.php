<?php

namespace App\Http\Requests\Congnghe;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCongngheSoftwareProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'department_id' => [
                'required',
                'integer',
                Rule::exists('departments', 'id')->where('is_active', true),
            ],
            'title' => ['required', 'string', 'max:200'],
            'content' => ['required', 'string', 'max:10000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => [
                'file',
                'max:5120',
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,jpg,jpeg,png,gif,webp',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập họ tên.',
            'email.required' => 'Vui lòng nhập email.',
            'email.email' => 'Email không hợp lệ.',
            'department_id.required' => 'Vui lòng chọn phòng ban.',
            'department_id.exists' => 'Phòng ban không hợp lệ hoặc đã ngừng hoạt động.',
            'title.required' => 'Vui lòng nhập tiêu đề đề xuất.',
            'content.required' => 'Vui lòng mô tả nội dung đề xuất.',
            'content.max' => 'Nội dung không vượt quá 10.000 ký tự.',
            'attachments.max' => 'Tối đa 5 tệp đính kèm.',
            'attachments.*.max' => 'Mỗi tệp không vượt quá 5MB.',
            'attachments.*.mimes' => 'Tệp không hợp lệ. Chấp nhận Word, Excel, PDF, hình ảnh hoặc tài liệu văn bản.',
        ];
    }
}
