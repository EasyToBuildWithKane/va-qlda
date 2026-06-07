<?php

namespace App\Models;

use App\Support\Enums\OrgTeamMemberBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $org_team_id
 * @property int $employee_id
 * @property int|null $section_id
 * @property OrgTeamMemberBranch|null $branch
 */
class OrgTeamMember extends Model
{
    protected $fillable = [
        'org_team_id',
        'employee_id',
        'section_id',
        'branch',
        'sort_order',
    ];

    protected $casts = [
        'branch' => OrgTeamMemberBranch::class,
        'sort_order' => 'integer',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(OrgTeam::class, 'org_team_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(OrgTeamSection::class, 'section_id');
    }
}
