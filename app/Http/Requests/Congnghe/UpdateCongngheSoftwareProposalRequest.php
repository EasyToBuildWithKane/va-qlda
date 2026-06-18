<?php

namespace App\Http\Requests\Congnghe;

use App\Models\CongngheSoftwareProposal;
use App\Support\Enums\CongngheSoftwareProposalStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCongngheSoftwareProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $proposal = $this->route('proposal');

        return $proposal instanceof CongngheSoftwareProposal
            && $this->user()?->can('update', $proposal);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(CongngheSoftwareProposalStatus::values())],
            'rejection_reason' => ['nullable', 'string', 'min:10', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $status = (string) $this->input('status', '');
            if ($status === CongngheSoftwareProposalStatus::Rejected->value) {
                $reason = trim((string) $this->input('rejection_reason', ''));
                if ($reason === '') {
                    $validator->errors()->add('rejection_reason', 'Vui lòng nhập lý do từ chối.');
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
            'status.required' => 'Vui lòng chọn trạng thái.',
            'status.in' => 'Trạng thái không hợp lệ.',
            'rejection_reason.min' => 'Lý do từ chối cần ít nhất :min ký tự.',
            'rejection_reason.max' => 'Lý do từ chối không được vượt quá :max ký tự.',
        ];
    }
}
