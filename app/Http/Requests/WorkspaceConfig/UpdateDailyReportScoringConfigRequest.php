<?php

namespace App\Http\Requests\WorkspaceConfig;

use App\Models\DailyReport\DailyReportScoringConfig;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDailyReportScoringConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', DailyReportScoringConfig::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'department_code' => ['required', 'string', 'max:64'],
            'department_name' => ['nullable', 'string', 'max:255'],
            'local_department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'weights' => ['required', 'array'],
            'weights.task_completion' => ['required', 'numeric', 'gt:0', 'max:100'],
            'weights.skill_score' => ['required', 'numeric', 'gt:0', 'max:100'],
            'weights.attitude_score' => ['required', 'numeric', 'gt:0', 'max:100'],
            'weights.expertise_score' => ['required', 'numeric', 'gt:0', 'max:100'],
            'kaizen_bonus_max' => ['required', 'numeric', 'min:0', 'max:5'],
            'status' => ['nullable', 'string', Rule::in([
                DailyReportScoringConfig::STATUS_ACTIVE,
                DailyReportScoringConfig::STATUS_DRAFT,
            ])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'department_code.required' => 'Vui lòng chọn phòng ban.',
            'weights.required' => 'Vui lòng nhập trọng số các tiêu chí.',
            'weights.task_completion.gt' => 'Trọng số Hoàn thành phải lớn hơn 0.',
            'weights.skill_score.gt' => 'Trọng số Kỹ năng phải lớn hơn 0.',
            'weights.attitude_score.gt' => 'Trọng số Thái độ phải lớn hơn 0.',
            'weights.expertise_score.gt' => 'Trọng số Chuyên môn phải lớn hơn 0.',
            'kaizen_bonus_max.min' => 'Điểm Kaizen tối đa không được âm.',
            'kaizen_bonus_max.max' => 'Điểm Kaizen tối đa không vượt quá 5.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'weights.task_completion' => 'Hoàn thành',
            'weights.skill_score' => 'Kỹ năng',
            'weights.attitude_score' => 'Thái độ',
            'weights.expertise_score' => 'Chuyên môn',
            'kaizen_bonus_max' => 'Điểm Kaizen tối đa',
        ];
    }
}
