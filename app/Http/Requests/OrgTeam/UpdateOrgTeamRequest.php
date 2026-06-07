<?php

namespace App\Http\Requests\OrgTeam;

use App\Models\OrgTeam;
use App\Support\Enums\OrgTeamMemberBranch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateOrgTeamRequest extends FormRequest
{
    use ValidatesOrgTeamSections;

    public function authorize(): bool
    {
        /** @var OrgTeam $team */
        $team = $this->route('orgTeam');

        return $this->user()->can('update', $team);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:org_teams,id'],
            'leader_id' => ['nullable', 'integer', 'exists:employees,id'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['sometimes', 'boolean'],
            'members' => ['nullable', 'array', 'max:50'],
            'members.*.employee_id' => ['required', 'integer', 'distinct', 'exists:employees,id'],
            'members.*.branch' => ['nullable', 'string', Rule::in(OrgTeamMemberBranch::values())],
            'members.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], $this->sectionRules());
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            /** @var OrgTeam $team */
            $team = $this->route('orgTeam');
            $parentId = $this->has('parent_id') ? $this->input('parent_id') : $team->parent_id;

            if ($parentId !== null && (int) $parentId === $team->id) {
                $v->errors()->add('parent_id', 'Nhóm không thể là cha của chính nó.');

                return;
            }

            if ($parentId !== null) {
                $descendants = $team->descendantIds();
                if (in_array((int) $parentId, $descendants, true)) {
                    $v->errors()->add('parent_id', 'Không thể chọn nhóm con làm nhóm cha.');

                    return;
                }

                $parent = OrgTeam::query()->find($parentId);
                if ($parent && $parent->level >= OrgTeam::MAX_LEVEL) {
                    $v->errors()->add('parent_id', 'Nhóm cha đã ở cấp tối đa (3).');
                }
            }

            $level = $team->level;
            if ($this->has('parent_id')) {
                if ($parentId === null) {
                    $level = 1;
                } else {
                    $parent = OrgTeam::query()->find($parentId);
                    $level = $parent ? $parent->level + 1 : $level;
                }
            }

            if ($level > OrgTeam::MAX_LEVEL) {
                $v->errors()->add('parent_id', 'Cấu trúc tối đa 3 cấp.');
            }

            $this->validateSectionIndexes($v);
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tên nhóm.',
            'members.*.employee_id.distinct' => 'Mỗi thành viên chỉ được thêm một lần trong nhóm.',
            'sections.*.title.required' => 'Vui lòng nhập tiêu đề nhánh.',
        ];
    }
}
