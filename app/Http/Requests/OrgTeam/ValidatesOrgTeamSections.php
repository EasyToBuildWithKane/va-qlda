<?php

namespace App\Http\Requests\OrgTeam;

use Illuminate\Validation\Validator;

trait ValidatesOrgTeamSections
{
    /**
     * @return array<string, mixed>
     */
    protected function sectionRules(): array
    {
        return [
            'sections' => ['nullable', 'array', 'max:20'],
            'sections.*.title' => ['required', 'string', 'max:120'],
            'sections.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'members.*.section_index' => ['nullable', 'integer', 'min:0', 'max:19'],
        ];
    }

    protected function validateSectionIndexes(Validator $validator): void
    {
        $sections = $this->input('sections', []);
        if (! is_array($sections)) {
            return;
        }

        $maxIndex = count($sections) - 1;
        foreach ($this->input('members', []) as $index => $row) {
            $sectionIndexRaw = $row['section_index'] ?? null;
            if ($sectionIndexRaw === null || $sectionIndexRaw === '') {
                continue;
            }
            $sectionIndex = (int) $sectionIndexRaw;
            if ($sectionIndex > $maxIndex) {
                $validator->errors()->add(
                    "members.{$index}.section_index",
                    'Nhánh đã chọn không tồn tại trong danh sách tiêu đề.',
                );
            }
        }
    }
}
