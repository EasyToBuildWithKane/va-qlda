<?php

namespace Database\Factories;

use App\Models\Project;
use App\Support\Enums\ProjectScope;
use App\Support\Enums\ProjectStatus;
use App\Support\Enums\ProjectType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    private static int $seq = 0;

    public function definition(): array
    {
        $n = ++self::$seq;

        return [
            'code' => 'PRJ-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT),
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'color' => fake()->hexColor(),
            'status' => ProjectStatus::Planning,
            'type' => ProjectType::Rnd,
            'scope' => ProjectScope::Headquarters,
            'is_active' => true,
            'sort_order' => $n,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => ProjectStatus::Active, 'is_active' => true]);
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => ProjectStatus::Completed, 'is_active' => false]);
    }
}
