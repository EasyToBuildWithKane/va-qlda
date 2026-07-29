<?php

namespace App\Http\Requests\Profile;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Self-service update of QLDA-only profile data (skill matrix).
 * HR identity fields are SSOT on VA-HRM — rejected if the client still sends them.
 */
class UpdateProfileRequest extends FormRequest
{
    /** @var list<string> */
    private const FORBIDDEN_HR_FIELDS = [
        'phone',
        'role_title',
        'bio',
        'location',
        'github',
        'linkedin',
        'portfolio',
        'website',
        'avatar',
        'full_name',
        'email',
        'join_date',
        'code',
    ];

    public function authorize(): bool
    {
        return $this->user()?->employee_id !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // present — cho phép mảng rỗng (xoá hết kỹ năng); required sẽ fail [].
            'skills' => ['present', 'array', 'max:40'],
            'skills.*.name' => ['required', 'string', 'max:50'],
            'skills.*.level' => ['nullable', 'integer', 'min:1', 'max:5'],
            'skills.*.category' => ['nullable', 'string', 'max:80'],
            'skills.*.years' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'skills.*.note' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (self::FORBIDDEN_HR_FIELDS as $key) {
                if ($this->exists($key) || $this->hasFile($key)) {
                    $validator->errors()->add(
                        $key,
                        'Thông tin nhân sự chỉ cập nhật trên VA-HRM.'
                    );
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'skills.present' => 'Danh sách kỹ năng là bắt buộc.',
            'skills.max' => 'Tối đa 40 kỹ năng.',
            'skills.*.name.required' => 'Tên kỹ năng không được để trống.',
        ];
    }
}
