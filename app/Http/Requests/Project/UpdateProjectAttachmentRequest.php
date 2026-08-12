<?php

namespace App\Http\Requests\Project;

use App\Models\ProjectAttachment;
use App\Support\ProjectAttachmentExternalUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateProjectAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('contribute', $this->route('project'));
    }

    protected function prepareForValidation(): void
    {
        if (! $this->exists('parent_id')) {
            return;
        }

        $parentId = $this->input('parent_id');
        if ($parentId === '' || $parentId === 'null') {
            $this->merge(['parent_id' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:2000'],
            'title' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string', 'max:1048576'],
            'external_url' => ['nullable', 'string', 'url', 'max:2048'],
            'parent_id' => ['sometimes', 'nullable', 'integer'],
            'file' => [
                'nullable',
                'file',
                'max:20480',
                'mimes:jpg,jpeg,png,gif,webp,svg,pdf,doc,docx,xls,xlsx,ppt,pptx,zip,rar,txt,csv,json,md',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $externalUrl = trim((string) $this->input('external_url', ''));
            if ($externalUrl !== '' && ! ProjectAttachmentExternalUrl::isSupported($externalUrl)) {
                $v->errors()->add('external_url', 'Chỉ hỗ trợ link Google Docs, Google Sheets hoặc PDF (https://…/file.pdf hoặc Google Drive).');
            }

            if (! $this->exists('parent_id')) {
                return;
            }

            $this->validateParentMove($v);
        });
    }

    private function validateParentMove(Validator $v): void
    {
        /** @var \App\Models\Project $project */
        $project = $this->route('project');
        /** @var ProjectAttachment $attachment */
        $attachment = $this->route('attachment');

        if ($attachment->project_id !== $project->id) {
            $v->errors()->add('parent_id', 'Tài liệu không thuộc dự án này.');

            return;
        }

        $newParentId = $this->input('parent_id');
        $oldParentId = $attachment->parent_id;

        if ($newParentId === null && $oldParentId === null) {
            return;
        }

        if ($newParentId !== null && (int) $newParentId === (int) $oldParentId) {
            return;
        }

        if ($newParentId !== null && (int) $newParentId === (int) $attachment->id) {
            $v->errors()->add('parent_id', 'Không thể đặt thư mục vào chính nó.');

            return;
        }

        $parent = null;
        if ($newParentId !== null) {
            $parent = ProjectAttachment::query()
                ->where('project_id', $project->id)
                ->whereKey($newParentId)
                ->first();

            if ($parent === null) {
                $v->errors()->add('parent_id', 'Thư mục đích không tồn tại.');

                return;
            }

            if (! $parent->isFolder()) {
                $v->errors()->add('parent_id', 'Chỉ có thể chuyển vào trong thư mục.');

                return;
            }

            if ($parent->category->value !== $attachment->category->value) {
                $v->errors()->add('parent_id', 'Thư mục đích phải cùng danh mục tài liệu.');

                return;
            }
        }

        if ($attachment->isFolder()) {
            $descendantIds = $this->collectDescendantIds($attachment);
            if ($newParentId !== null && in_array((int) $newParentId, $descendantIds, true)) {
                $v->errors()->add('parent_id', 'Không thể chuyển thư mục vào thư mục con của nó.');

                return;
            }

            $parentDepth = 0;
            if ($parent !== null) {
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
            }

            $subtreeHeight = $this->folderSubtreeHeight($attachment);
            $deepest = $parentDepth + $subtreeHeight;
            if ($deepest > 12) {
                $v->errors()->add('parent_id', 'Chỉ hỗ trợ tối đa 12 cấp thư mục lồng nhau.');
            }
        }
    }

    /**
     * @return list<int>
     */
    private function collectDescendantIds(ProjectAttachment $folder): array
    {
        $ids = [];
        $queue = [$folder->id];

        while ($queue !== []) {
            $parentId = array_shift($queue);
            $children = ProjectAttachment::query()
                ->where('project_id', $folder->project_id)
                ->where('parent_id', $parentId)
                ->get(['id', 'is_folder']);

            foreach ($children as $child) {
                $ids[] = (int) $child->id;
                if ($child->is_folder) {
                    $queue[] = $child->id;
                }
            }
        }

        return $ids;
    }

    /**
     * Chiều cao cây thư mục (lá = 1).
     */
    private function folderSubtreeHeight(ProjectAttachment $folder): int
    {
        $maxChild = 0;
        $children = ProjectAttachment::query()
            ->where('project_id', $folder->project_id)
            ->where('parent_id', $folder->id)
            ->where('is_folder', true)
            ->get();

        foreach ($children as $child) {
            $maxChild = max($maxChild, $this->folderSubtreeHeight($child));
        }

        return 1 + $maxChild;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'external_url.url' => 'Link phải là URL hợp lệ (https://…).',
            'content.max' => 'Nội dung file không được vượt quá 1 MB.',
            'title.max' => 'Tên không được vượt quá 255 ký tự.',
            'parent_id.integer' => 'Thư mục đích không hợp lệ.',
        ];
    }
}
