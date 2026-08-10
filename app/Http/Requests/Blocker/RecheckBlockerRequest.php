<?php

namespace App\Http\Requests\Blocker;

use App\Models\Blocker;
use App\Support\BlockerRecheck;
use App\Support\Enums\BlockerRecheckResult;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RecheckBlockerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recheck', $this->route('blocker'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'result' => ['required', Rule::in([BlockerRecheckResult::Passed->value, BlockerRecheckResult::Failed->value])],
            'note' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $blocker = $this->route('blocker');
            if (! $blocker instanceof Blocker) {
                return;
            }

            if (! BlockerRecheck::needsRecheck($blocker)) {
                $validator->errors()->add(
                    'result',
                    'Chỉ kiểm tra lại được khi test case đã giải quyết và đang chờ xác nhận.',
                );
            }

            if ($this->input('result') === BlockerRecheckResult::Failed->value && ! filled(trim((string) $this->input('note')))) {
                $validator->errors()->add(
                    'note',
                    'Vui lòng ghi rõ lý do không đạt để người xử lý biết cần sửa gì.',
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'result.required' => 'Chọn kết quả kiểm tra (đạt hoặc không đạt).',
            'result.in' => 'Kết quả kiểm tra không hợp lệ.',
        ];
    }
}
