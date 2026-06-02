<?php

namespace App\Http\Requests\Feedback;

use App\Models\Feedback;
use App\Support\Enums\FeedbackCategory;
use App\Support\Enums\FeedbackStatus;
use App\Support\Enums\TaskPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Feedback::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'category' => ['required', Rule::in(FeedbackCategory::values())],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'priority' => ['required', Rule::in(TaskPriority::values())],
            'status' => ['nullable', Rule::in(FeedbackStatus::values())],
            'reporter_employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'reporter_name' => ['nullable', 'required_without:reporter_employee_id', 'string', 'max:120'],
            'reporter_email' => ['nullable', 'email', 'max:160'],
            'assignee_id' => ['nullable', 'integer', 'exists:employees,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'reporter_name.required_without' => 'Nhập tên người gửi phản hồi (hoặc chọn nhân sự nội bộ).',
        ];
    }
}
