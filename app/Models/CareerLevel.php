<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A rung on the global career ladder, with promotion requirements used by the
 * skill-gap analysis and career roadmap.
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property int $rank
 * @property string|null $description
 * @property array<string, mixed>|null $requirements
 */
class CareerLevel extends Model
{
    protected $fillable = [
        'key',
        'name',
        'rank',
        'description',
        'requirements',
        'sort_order',
    ];

    protected $casts = [
        'rank' => 'integer',
        'requirements' => 'array',
        'sort_order' => 'integer',
    ];

    /**
     * Required skill → minimum level map, e.g. ["Laravel" => 4].
     *
     * @return array<string, int>
     */
    public function requiredSkills(): array
    {
        $skills = $this->requirements['skills'] ?? [];

        return is_array($skills) ? $skills : [];
    }
}
