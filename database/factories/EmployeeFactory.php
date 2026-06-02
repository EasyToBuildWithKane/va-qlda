<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'code' => 'EMP-'.fake()->unique()->numberBetween(1000, 9999),
            'full_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'role_title' => 'Developer',
            'join_date' => now()->subMonths(6),
            'skills' => ['php', 'laravel', 'vue'],
            'is_active' => true,
        ];
    }
}
