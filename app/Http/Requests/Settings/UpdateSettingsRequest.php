<?php

namespace App\Http\Requests\Settings;

use App\Models\SystemSetting;
use App\Support\Settings\SettingsSchema;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a settings update for the group named in the route ({group}).
 * Rules are sourced from {@see SettingsSchema} so the field list stays
 * single-sourced with the controller payload and the Vue form.
 */
class UpdateSettingsRequest extends FormRequest
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
        return SettingsSchema::rules((string) $this->route('group'));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => 'Vui lòng nhập :attribute.',
            'email' => ':attribute không hợp lệ.',
            'max' => ':attribute quá dài.',
            'array' => ':attribute không hợp lệ.',
            'grants.*.*.in' => 'Quyền được chọn không hợp lệ.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'app_name' => 'Tên hệ thống',
            'app_short_name' => 'Tên viết tắt',
            'support_email' => 'Email hỗ trợ',
            'app_version' => 'Phiên bản',
            'google_allowed_domains' => 'Domain email Google',
            'bot_token' => 'Bot token',
            'chat_id' => 'Chat ID',
            'blocker_chat_id' => 'Chat ID test case',
            'enabled' => 'Bật gửi email',
            'from_name' => 'Tên người gửi',
            'notify_on_assign' => 'Email khi giao việc',
            'notify_daily_at' => 'Giờ gửi tổng hợp',
        ];
    }
}
