<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tiêu đề nhóm trên sơ đồ — không phải cấp tổ chức.
 *
 * @property int $id
 * @property int $org_team_id
 * @property string $title
 * @property int $sort_order
 */
class OrgTeamSection extends Model
{
    protected $fillable = [
        'org_team_id',
        'title',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(OrgTeam::class, 'org_team_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(OrgTeamMember::class, 'section_id')->orderBy('sort_order');
    }
}
