<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\SystemAccount;
use App\Support\Enums\SystemRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SystemAccount>
 */
class SystemAccountFactory extends Factory
{
    protected $model = SystemAccount::class;

    public function definition(): array
    {
        return [
            'username' => fake()->unique()->userName(),
            'password' => 'password', // hashed via model cast
            'display_name' => fake()->name(),
            'role' => SystemRole::Member,
            'employee_id' => Employee::factory(),
            'is_active' => true,
        ];
    }

    public function role(SystemRole $role): static
    {
        return $this->state(fn () => ['role' => $role]);
    }

    public function forEmployee(Employee $employee): static
    {
        return $this->state(fn () => [
            'employee_id' => $employee->id,
            'display_name' => $employee->full_name,
        ]);
    }
}
