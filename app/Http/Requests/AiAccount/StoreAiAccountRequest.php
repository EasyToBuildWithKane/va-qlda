<?php

namespace App\Http\Requests\AiAccount;

use App\Models\AiAccount;
use App\Support\Enums\AiAccountCostUnit;
use App\Support\Enums\AiAccountGroupFunction;
use App\Support\Enums\AiAccountLoginMethod;
use App\Support\Enums\AiAccountPurchaseType;
use App\Support\Enums\AiAccountStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAiAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', AiAccount::class);
    }

    public function rules(): array
    {
        return [
            'tool_name' => ['required', 'string', 'max:255'],
            'group_function' => ['required', Rule::in(AiAccountGroupFunction::values())],
            'email_registered' => ['required', 'email', 'max:255'],
            'login_method' => ['required', Rule::in(AiAccountLoginMethod::values())],
            'password' => ['nullable', 'string', 'max:255'],
            'purchase_date' => ['required', 'date'],
            'expiry_date' => ['required', 'date', 'after_or_equal:purchase_date'],
            'cost_amount' => ['required', 'integer', 'min:0'],
            'cost_unit' => ['required', Rule::in(AiAccountCostUnit::values())],
            'notify_before_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'proposal_sent_at' => ['nullable', 'date'],
            'proposal_approved_at' => ['nullable', 'date', 'after_or_equal:proposal_sent_at'],
            'payment_request_sent_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'purchase_url' => ['nullable', 'string', 'max:2048', 'url'],
            'purchase_type' => ['required', Rule::in(AiAccountPurchaseType::values())],
            'status' => ['nullable', Rule::in(AiAccountStatus::values())],
            'proposal_documents' => ['nullable', 'array', 'max:1'],
            'proposal_documents.*' => ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
            'payment_request_documents' => ['nullable', 'array', 'max:1'],
            'payment_request_documents.*' => ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $method = $this->input('login_method', AiAccountLoginMethod::Password->value);

            if ($method === AiAccountLoginMethod::Password->value
                && $this->filled('password')
                && ! $this->user()->isAdminTier()
                && ! $this->user()->allows('ai_account.view_password')
            ) {
                $validator->errors()->add('password', 'Bạn không có quyền lưu mật khẩu đăng nhập.');
            }

            if ($method === AiAccountLoginMethod::Google->value && $this->filled('password')) {
                $validator->errors()->add('password', 'Đăng nhập Google không dùng mật khẩu lưu trên hệ thống.');
            }

            if ($this->filled('proposal_sent_at') && ! $this->hasFile('proposal_documents')) {
                $validator->errors()->add(
                    'proposal_documents',
                    'Ngày gửi đề xuất cần kèm đúng 1 file phiếu đề xuất.',
                );
            }

            if ($this->filled('payment_request_sent_at') && ! $this->hasFile('payment_request_documents')) {
                $validator->errors()->add(
                    'payment_request_documents',
                    'Ngày gửi đề nghị thanh toán cần kèm đúng 1 file.',
                );
            }

            if ($this->filled('proposal_approved_at') && ! $this->filled('proposal_sent_at')) {
                $validator->errors()->add(
                    'proposal_approved_at',
                    'Cần có ngày gửi đề xuất trước khi ghi nhận duyệt.',
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'tool_name.required' => 'Vui lòng nhập tên công cụ AI.',
            'group_function.required' => 'Vui lòng chọn nhóm chức năng.',
            'email_registered.required' => 'Vui lòng nhập email đăng ký.',
            'email_registered.email' => 'Email đăng ký không hợp lệ.',
            'login_method.required' => 'Vui lòng chọn cách đăng nhập.',
            'purchase_date.required' => 'Vui lòng nhập ngày mua.',
            'expiry_date.required' => 'Vui lòng nhập ngày hết hạn.',
            'expiry_date.after_or_equal' => 'Ngày hết hạn phải sau hoặc bằng ngày mua.',
            'cost_amount.required' => 'Vui lòng nhập chi phí.',
            'cost_unit.required' => 'Vui lòng chọn đơn vị chi phí.',
            'purchase_url.url' => 'Link chỗ mua không hợp lệ (cần dạng https://…).',
            'purchase_type.required' => 'Vui lòng chọn loại mua (mua mới hoặc gia hạn).',
            'purchase_type.in' => 'Loại mua không hợp lệ.',
            'proposal_approved_at.after_or_equal' => 'Ngày duyệt phải sau hoặc bằng ngày gửi đề xuất.',
            'proposal_documents.max' => 'Chỉ được đính kèm 1 file phiếu đề xuất.',
            'payment_request_documents.max' => 'Chỉ được đính kèm 1 file đề nghị thanh toán.',
        ];
    }
}
