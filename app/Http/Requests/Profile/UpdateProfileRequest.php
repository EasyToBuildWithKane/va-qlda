<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Self-service edit of one's own profile. The authenticated account always
 * targets its own linked employee, so authorization is implicit.
 */
class UpdateProfileRequest extends FormRequest
{
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
            'phone' => ['nullable', 'string', 'max:30'],
            'role_title' => ['nullable', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:500'],
            'location' => ['nullable', 'string', 'max:120'],
            'github' => ['nullable', 'url', 'max:255'],
            'linkedin' => ['nullable', 'url', 'max:255'],
            'portfolio' => ['nullable', 'url', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'skills' => ['nullable', 'array', 'max:40'],
            'skills.*' => ['string', 'max:50'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bio.max' => 'Giới thiệu không vượt quá 500 ký tự.',
            'github.url' => 'Liên kết GitHub không hợp lệ.',
            'linkedin.url' => 'Liên kết LinkedIn không hợp lệ.',
            'portfolio.url' => 'Liên kết Portfolio không hợp lệ.',
            'website.url' => 'Liên kết Website không hợp lệ.',
            'skills.max' => 'Tối đa 40 kỹ năng.',
            'avatar.image' => 'Ảnh đại diện phải là tệp hình ảnh.',
            'avatar.mimes' => 'Ảnh đại diện chỉ chấp nhận JPG, PNG hoặc WEBP.',
            'avatar.max' => 'Ảnh đại diện không vượt quá 2MB.',
        ];
    }
}
