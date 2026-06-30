<?php

namespace App\Http\Requests\Project;

use App\Support\ProjectAttachmentExternalUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateProjectAttachmentRequest extends FormRequest
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
            'notes' => ['nullable', 'string', 'max:2000'],
            'title' => ['nullable', 'string', 'max:255'],
            'external_url' => ['nullable', 'string', 'url', 'max:2048'],
            'file' => [
                'nullable',
                'file',
                'max:20480',
                'mimes:jpg,jpeg,png,gif,webp,svg,pdf,doc,docx,xls,xlsx,ppt,pptx,zip,rar,txt,csv,json',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $externalUrl = trim((string) $this->input('external_url', ''));
            if ($externalUrl === '') {
                return;
            }

            if (! ProjectAttachmentExternalUrl::isSupported($externalUrl)) {
                $v->errors()->add('external_url', 'Chỉ hỗ trợ link Google Docs, Google Sheets hoặc PDF (https://…/file.pdf hoặc Google Drive).');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'external_url.url' => 'Link phải là URL hợp lệ (https://…).',
        ];
    }
}
