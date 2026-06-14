<?php

namespace App\Http\Requests\Settings;

use App\Models\SystemSetting;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEmailTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', SystemSetting::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'body_html' => ['required', 'string', 'max:65000'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subject.required' => 'Vui lòng nhập tiêu đề email.',
            'body_html.required' => 'Vui lòng nhập nội dung mẫu.',
        ];
    }
}
