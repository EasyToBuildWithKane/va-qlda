<?php

namespace App\Http\Requests\Onboarding;

use App\Services\OnboardingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OnboardingTourRequest extends FormRequest
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
            'tour_key' => ['required', 'string', Rule::in(OnboardingService::TOURS)],
            'current_step' => ['sometimes', 'integer', 'min:0', 'max:200'],
            'total_steps' => ['sometimes', 'integer', 'min:0', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tour_key.required' => 'Thiếu mã hướng dẫn.',
            'tour_key.in' => 'Mã hướng dẫn không hợp lệ.',
        ];
    }
}
