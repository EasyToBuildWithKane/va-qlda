<?php

namespace App\Http\Requests\Feedback;

use App\Support\Enums\FeedbackCategory;
use App\Support\Enums\FeedbackStatus;
use App\Support\Enums\TaskPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('feedback'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'category' => ['sometimes', 'required', Rule::in(FeedbackCategory::values())],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string', 'max:10000'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'priority' => ['sometimes', 'required', Rule::in(TaskPriority::values())],
            'status' => ['sometimes', 'required', Rule::in(FeedbackStatus::values())],
            'assignee_id' => ['nullable', 'integer', 'exists:employees,id'],
        ];
    }
}
