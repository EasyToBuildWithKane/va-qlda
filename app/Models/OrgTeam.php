<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $parent_id
 * @property string $name
 * @property int $level
 * @property int|null $leader_id
 * @property int $sort_order
 * @property bool $is_active
 */
class OrgTeam extends Model
{
    public const MAX_LEVEL = 3;

    protected $fillable = [
        'parent_id',
        'name',
        'level',
        'leader_id',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'level' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'leader_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(OrgTeamMember::class)->orderBy('sort_order');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(OrgTeamSection::class)->orderBy('sort_order');
    }

    /**
     * @return list<int>
     */
    public function descendantIds(): array
    {
        $ids = [];
        $queue = $this->children()->pluck('id')->all();

        while ($queue !== []) {
            $id = array_shift($queue);
            $ids[] = $id;
            $queue = array_merge(
                $queue,
                self::query()->where('parent_id', $id)->pluck('id')->all(),
            );
        }

        return $ids;
    }
}
