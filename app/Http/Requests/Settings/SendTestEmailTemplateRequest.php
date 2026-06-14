<?php

namespace App\Http\Requests\Settings;

use App\Models\SystemSetting;
use Illuminate\Foundation\Http\FormRequest;

class SendTestEmailTemplateRequest extends FormRequest
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
            'email' => ['required', 'email', 'max:190'],
            'subject' => ['required', 'string', 'max:255'],
            'body_html' => ['required', 'string', 'max:65000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Vui lòng nhập email nhận thử.',
            'email.email' => 'Email nhận thử không hợp lệ.',
            'subject.required' => 'Tiêu đề email không được để trống.',
            'body_html.required' => 'Nội dung email không được để trống.',
        ];
    }
}
