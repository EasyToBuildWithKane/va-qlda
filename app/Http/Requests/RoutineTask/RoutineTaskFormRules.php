<?php

namespace App\Http\Requests\RoutineTask;

use App\Domain\RoutineTask\Models\RoutineTask;

trait RoutineTaskFormRules
{
    /**
     * @return array<string, mixed>
     */
    protected function fieldRules(bool $titleSometimes = false): array
    {
        return [
            'title' => [$titleSometimes ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', 'string', \Illuminate\Validation\Rule::in(RoutineTask::allowedStatusValues())],
            'work_date' => ['nullable', 'date'],
            'start_time' => ['nullable', 'string', 'regex:/^\d{1,2}:\d{2}(:\d{2})?$/'],
            'end_time' => ['nullable', 'string', 'regex:/^\d{1,2}:\d{2}(:\d{2})?$/'],
            'estimate_hours' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'actual_hours' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'progress_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'blockers' => ['nullable', 'string', 'max:5000'],
            'risks' => ['nullable', 'string', 'max:5000'],
            'files' => ['nullable', 'array', 'max:10'],
            'files.*' => [
                'file',
                'max:10240',
                'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,zip,txt',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function fieldMessages(): array
    {
        return [
            'title.required' => 'Nhập tiêu đề công việc.',
            'title.max' => 'Tiêu đề không được vượt quá 255 ký tự.',
            'status.in' => 'Trạng thái không hợp lệ (cần làm / đang làm / hoàn thành).',
            'work_date.date' => 'Ngày làm việc không hợp lệ.',
            'start_time.regex' => 'Giờ bắt đầu không hợp lệ.',
            'end_time.regex' => 'Giờ kết thúc không hợp lệ.',
            'estimate_hours.numeric' => 'Giờ ước tính phải là số.',
            'actual_hours.numeric' => 'Giờ thực tế phải là số.',
            'progress_percent.integer' => 'Tiến độ phải là số nguyên 0–100.',
            'files.max' => 'Tối đa 10 tệp mỗi lần lưu.',
            'files.*.max' => 'Mỗi tệp không vượt quá 10MB.',
            'files.*.mimes' => 'Định dạng tệp không được hỗ trợ.',
        ];
    }

    protected function emptyToNull(): void
    {
        $nullable = [
            'description',
            'work_date',
            'start_time',
            'end_time',
            'estimate_hours',
            'actual_hours',
            'progress_percent',
            'blockers',
            'risks',
            'status',
        ];

        $merge = [];
        foreach ($nullable as $key) {
            if ($this->exists($key) && $this->input($key) === '') {
                $merge[$key] = null;
            }
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }
}
