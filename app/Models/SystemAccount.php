<?php

namespace App\Models;

use App\Support\Auth\Permissions;
use App\Support\Enums\SystemRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Authenticatable login account for the simulated auth guard ("system").
 *
 * @property int $id
 * @property string $username
 * @property string $display_name
 * @property SystemRole $role
 * @property int|null $employee_id
 * @property bool $is_active
 */
class SystemAccount extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;

    protected $table = 'system_accounts';

    protected $fillable = [
        'username',
        'password',
        'display_name',
        'role',
        'employee_id',
        'is_active',
        'last_login_at',
        'onboarding_seen_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'password' => 'hashed',
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
        'onboarding_seen_at' => 'datetime',
        'role' => SystemRole::class,
    ];

    /**
     * The person this login belongs to.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Whether this account holds any of the given roles.
     */
    public function hasRole(SystemRole ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function allows(string $permission): bool
    {
        return Permissions::roleAllows($this->role, $permission);
    }
}
