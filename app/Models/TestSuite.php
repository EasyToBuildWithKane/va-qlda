<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $project_id
 * @property string $name
 * @property int $sort_order
 */
class TestSuite extends Model
{
    protected $fillable = [
        'project_id',
        'name',
        'sort_order',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function testCases(): HasMany
    {
        return $this->hasMany(TestCase::class, 'suite_id')->orderBy('id');
    }
}
