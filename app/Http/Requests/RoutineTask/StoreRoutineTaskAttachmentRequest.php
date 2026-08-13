<?php

namespace App\Http\Requests\RoutineTask;

use App\Domain\RoutineTask\Models\RoutineTask;
use Illuminate\Foundation\Http\FormRequest;

class StoreRoutineTaskAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var RoutineTask $task */
        $task = $this->route('routineTask');

        return $this->user()->can('update', $task);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1', 'max:10'],
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
    public function messages(): array
    {
        return [
            'files.required' => 'Vui lòng chọn ít nhất một tệp.',
            'files.max' => 'Tối đa 10 tệp mỗi lần tải lên.',
            'files.*.max' => 'Mỗi tệp không vượt quá 10MB.',
            'files.*.mimes' => 'Định dạng tệp không được hỗ trợ.',
        ];
    }
}
