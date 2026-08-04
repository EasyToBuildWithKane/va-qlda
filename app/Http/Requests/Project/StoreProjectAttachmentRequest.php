<?php

namespace App\Http\Requests\Project;

use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Support\Enums\ProjectAttachmentCategory;
use App\Support\ProjectAttachmentExternalUrl;
use App\Support\ProjectAttachmentNewFile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProjectAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('contribute', $this->route('project'));
    }

    protected function prepareForValidation(): void
    {
        $parentId = $this->input('parent_id');
        if ($parentId === '' || $parentId === 'null') {
            $this->merge(['parent_id' => null]);
        }

        if ($this->has('is_folder')) {
            $raw = $this->input('is_folder');
            $bool = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $this->merge([
                'is_folder' => $bool ?? in_array($raw, [1, '1', true, 'true'], true),
            ]);
        }

        if ($this->has('is_new_file')) {
            $raw = $this->input('is_new_file');
            $bool = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $this->merge([
                'is_new_file' => $bool ?? in_array($raw, [1, '1', true, 'true'], true),
            ]);
        }
    }

    public function isFolderRequest(): bool
    {
        if ($this->boolean('is_new_file')) {
            return false;
        }

        if ($this->boolean('is_folder')) {
            return true;
        }

        $folderName = trim((string) $this->input('folder_name', ''));

        return $folderName !== ''
            && ! $this->hasFile('files')
            && trim((string) $this->input('external_url', '')) === ''
            && trim((string) $this->input('file_name', '')) === '';
    }

    public function isNewFileRequest(): bool
    {
        if ($this->boolean('is_folder')) {
            return false;
        }

        if ($this->boolean('is_new_file')) {
            return true;
        }

        $fileName = trim((string) $this->input('file_name', ''));

        return $fileName !== ''
            && ! $this->hasFile('files')
            && trim((string) $this->input('external_url', '')) === ''
            && trim((string) $this->input('folder_name', '')) === '';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category' => ['required', Rule::in(ProjectAttachmentCategory::values())],
            'parent_id' => ['nullable', 'integer'],
            'is_folder' => ['sometimes', 'boolean'],
            'folder_name' => ['nullable', 'string', 'max:255'],
            'is_new_file' => ['sometimes', 'boolean'],
            'file_name' => ['nullable', 'string', 'max:200'],
            'file_type' => ['nullable', 'string', Rule::in(ProjectAttachmentNewFile::TYPES)],
            'files' => ['nullable', 'array', 'max:15'],
            'files.*' => [
                'file',
                'max:20480',
                'mimes:jpg,jpeg,png,gif,webp,svg,pdf,doc,docx,xls,xlsx,ppt,pptx,zip,rar,txt,csv,json,md',
            ],
            'external_url' => ['nullable', 'string', 'url', 'max:2048'],
            'title' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            /** @var Project $project */
            $project = $this->route('project');
            $isFolder = $this->isFolderRequest();
            $isNewFile = $this->isNewFileRequest();
            $hasFiles = $this->hasFile('files') && count($this->file('files', [])) > 0;
            $externalUrl = trim((string) $this->input('external_url', ''));
            $folderName = trim((string) ($this->input('folder_name') ?: $this->input('title', '')));
            $fileName = trim((string) $this->input('file_name', ''));
            $fileType = strtolower(trim((string) $this->input('file_type', 'txt')));

            if ($isFolder) {
                if ($folderName === '') {
                    $v->errors()->add('folder_name', 'Nhập tên thư mục.');

                    return;
                }
                if ($hasFiles || $externalUrl !== '' || $isNewFile) {
                    $v->errors()->add('is_folder', 'Không thể tạo thư mục cùng lúc với file hoặc link.');

                    return;
                }
            } elseif ($isNewFile) {
                if ($fileName === '') {
                    $v->errors()->add('file_name', 'Nhập tên file.');

                    return;
                }
                if (ProjectAttachmentNewFile::definition($fileType) === null) {
                    $v->errors()->add('file_type', 'Loại file không được hỗ trợ.');

                    return;
                }
                if ($hasFiles || $externalUrl !== '') {
                    $v->errors()->add('is_new_file', 'Không thể tạo file cùng lúc với tải lên hoặc link.');

                    return;
                }
            } elseif (! $hasFiles && $externalUrl === '') {
                $v->errors()->add('files', 'Chọn file, thêm link, tạo thư mục hoặc tạo file mới.');

                return;
            }

            if (! $isFolder && ! $isNewFile && $hasFiles && $externalUrl !== '') {
                $v->errors()->add('external_url', 'Chỉ tải file hoặc thêm link — không gửi cả hai cùng lúc.');

                return;
            }

            if ($externalUrl !== '' && ! ProjectAttachmentExternalUrl::isSupported($externalUrl)) {
                $v->errors()->add('external_url', 'Chỉ hỗ trợ link Google Docs, Google Sheets hoặc PDF (https://…/file.pdf hoặc Google Drive).');
            }

            $parentId = $this->input('parent_id');
            if ($parentId === null || $parentId === '') {
                return;
            }

            $parent = ProjectAttachment::query()
                ->where('project_id', $project->id)
                ->whereKey($parentId)
                ->first();

            if ($parent === null) {
                $v->errors()->add('parent_id', 'Thư mục cha không tồn tại.');

                return;
            }

            if (! $parent->isFolder()) {
                $v->errors()->add('parent_id', 'Chỉ có thể đặt file hoặc thư mục con vào trong thư mục.');

                return;
            }

            $category = (string) $this->input('category');
            if ($parent->category->value !== $category) {
                $v->errors()->add('parent_id', 'Thư mục cha phải cùng danh mục tài liệu.');

                return;
            }

            if ($isFolder) {
                $parentDepth = 0;
                $current = $parent;
                while ($current) {
                    $parentDepth++;
                    if ($current->parent_id === null) {
                        break;
                    }
                    $current = ProjectAttachment::query()
                        ->where('project_id', $project->id)
                        ->whereKey($current->parent_id)
                        ->first();
                    if ($current === null) {
                        break;
                    }
                }
                if ($parentDepth >= 12) {
                    $v->errors()->add('parent_id', 'Chỉ hỗ trợ tối đa 12 cấp thư mục lồng nhau.');
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
            'files.*.max' => 'Mỗi file tối đa 20MB.',
            'files.*.mimes' => 'Định dạng file không được phép.',
            'external_url.url' => 'Link phải là URL hợp lệ (https://…).',
        ];
    }
}
