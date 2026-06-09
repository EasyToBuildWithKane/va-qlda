<?php

namespace App\Http\Requests\Project;

use App\Support\Enums\ProjectAttachmentCategory;
use App\Support\GoogleWorkspaceUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProjectAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('contribute', $this->route('project'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category' => ['required', Rule::in(ProjectAttachmentCategory::values())],
            'files' => ['nullable', 'array', 'max:15'],
            'files.*' => [
                'file',
                'max:20480',
                'mimes:jpg,jpeg,png,gif,webp,svg,pdf,doc,docx,xls,xlsx,ppt,pptx,zip,rar,txt,csv,json',
            ],
            'external_url' => ['nullable', 'string', 'url', 'max:2048'],
            'title' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $hasFiles = $this->hasFile('files') && count($this->file('files', [])) > 0;
            $externalUrl = trim((string) $this->input('external_url', ''));

            if (! $hasFiles && $externalUrl === '') {
                $v->errors()->add('files', 'Chọn file hoặc dán link Google Docs / Google Sheets.');

                return;
            }

            if ($hasFiles && $externalUrl !== '') {
                $v->errors()->add('external_url', 'Chỉ tải file hoặc thêm link — không gửi cả hai cùng lúc.');

                return;
            }

            if ($externalUrl !== '' && ! GoogleWorkspaceUrl::isSupported($externalUrl)) {
                $v->errors()->add('external_url', 'Chỉ hỗ trợ link Google Docs hoặc Google Sheets (https://docs.google.com/…).');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'files.*.max' => 'Mỗi file tối đa 20MB.',
            'files.*.mimes' => 'Định dạng file không được phép.',
            'external_url.url' => 'Link phải là URL hợp lệ (https://…).',
        ];
    }
}
