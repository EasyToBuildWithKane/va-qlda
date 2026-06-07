<?php

namespace App\Http\Requests\OrgTeam;

use App\Models\OrgTeam;
use App\Support\Enums\OrgTeamMemberBranch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreOrgTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', OrgTeam::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:org_teams,id'],
            'leader_id' => ['nullable', 'integer', 'exists:employees,id'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['sometimes', 'boolean'],
            'members' => ['nullable', 'array', 'max:50'],
            'members.*.employee_id' => ['required', 'integer', 'distinct', 'exists:employees,id'],
            'members.*.branch' => ['nullable', 'string', Rule::in(OrgTeamMemberBranch::values())],
            'members.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $parentId = $this->input('parent_id');
            $level = 1;

            if ($parentId) {
                $parent = OrgTeam::query()->find($parentId);
                if ($parent === null) {
                    return;
                }
                if ($parent->level >= OrgTeam::MAX_LEVEL) {
                    $v->errors()->add('parent_id', 'Nhóm cha đã ở cấp tối đa (3). Không thể thêm nhóm con.');

                    return;
                }
                $level = $parent->level + 1;
            }

            $members = $this->input('members', []);
            if ($level === OrgTeam::MAX_LEVEL && is_array($members)) {
                foreach ($members as $index => $row) {
                    if (empty($row['branch'])) {
                        $v->errors()->add(
                            "members.{$index}.branch",
                            'Ở cấp 3, mỗi thành viên cần chọn nhánh (GVS, phần mềm phòng ban hoặc trợ lý dự án).',
                        );
                    }
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
            'name.required' => 'Vui lòng nhập tên nhóm.',
            'parent_id.exists' => 'Nhóm cha không tồn tại.',
            'leader_id.exists' => 'Trưởng nhóm không hợp lệ.',
            'members.*.employee_id.distinct' => 'Mỗi thành viên chỉ được thêm một lần trong nhóm.',
        ];
    }

    public function resolvedLevel(): int
    {
        $parentId = $this->input('parent_id');
        if (! $parentId) {
            return 1;
        }

        $parent = OrgTeam::query()->findOrFail((int) $parentId);

        return $parent->level + 1;
    }
}
