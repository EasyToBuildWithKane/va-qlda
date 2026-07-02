<?php

namespace App\Http\Requests\AiAccount;

use App\Models\AiProposalScan;
use Illuminate\Foundation\Http\FormRequest;

class StoreProposalScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', AiProposalScan::class);
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Vui lòng chọn file Phiếu Đề Xuất (PDF, JPG hoặc PNG).',
            'file.mimes' => 'Chỉ hỗ trợ file PDF, JPG hoặc PNG.',
            'file.max' => 'File không được vượt quá 10MB.',
        ];
    }
}
