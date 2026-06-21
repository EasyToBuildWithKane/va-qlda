<?php

namespace App\Http\Requests\Project;

use App\Support\Enums\SprintStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSprintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', $this->route('project'));
    }

    protected function prepareForValidation(): void
    {
        $status = $this->input('status');
        if (is_array($status) && isset($status['value'])) {
            $this->merge(['status' => $status['value']]);
        }

        foreach (['goal', 'start_date', 'end_date'] as $key) {
            if ($this->input($key) === '') {
                $this->merge([$key => null]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'goal' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(SprintStatus::values())],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tên sprint.',
            'status.required' => 'Vui lòng chọn trạng thái sprint.',
            'status.in' => 'Trạng thái sprint không hợp lệ.',
            'start_date.date' => 'Ngày bắt đầu không hợp lệ.',
            'end_date.date' => 'Ngày kết thúc không hợp lệ.',
            'end_date.after_or_equal' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.',
        ];
    }
}
