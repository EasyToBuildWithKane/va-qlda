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
 * @property \Illuminate\Support\Carbon|null $onboarding_seen_at
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

    /**
     * The top tier — full god-mode access; the only role that may edit system
     * configuration, the permission matrix and assign roles.
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === SystemRole::SuperAdmin;
    }

    /** super_admin or admin — the administrative tier. */
    public function isAdminTier(): bool
    {
        return $this->hasRole(SystemRole::SuperAdmin, SystemRole::Admin);
    }

    /** super_admin, admin, manager, deputy_manager or team_leader — the managerial tier. */
    public function isManagerTier(): bool
    {
        return $this->hasRole(
            SystemRole::SuperAdmin,
            SystemRole::Admin,
            SystemRole::Manager,
            SystemRole::DeputyManager,
            SystemRole::TeamLeader,
        );
    }

    /** Any contributing role (everyone except viewer). */
    public function isMemberTier(): bool
    {
        return $this->hasRole(
            SystemRole::SuperAdmin,
            SystemRole::Admin,
            SystemRole::Manager,
            SystemRole::DeputyManager,
            SystemRole::TeamLeader,
            SystemRole::Member,
        );
    }

    public function allows(string $permission): bool
    {
        // Super admin is unconditionally all-powerful (also enforced via
        // Gate::before for policy checks); short-circuit so direct allows()
        // calls outside the Gate layer agree.
        if ($this->role === SystemRole::SuperAdmin) {
            return true;
        }

        return Permissions::roleAllows($this->role, $permission);
    }

    /**
     * Bản sao chỉ đổi role — dùng để dựng nav/permissions "xem thử" (View As) mà
     * không đụng tới bản ghi/authenticated instance thật. KHÔNG dùng kết quả này
     * để authorize bất cứ hành động ghi dữ liệu nào.
     */
    public function asRole(SystemRole $role): self
    {
        $clone = clone $this;
        $clone->role = $role;

        return $clone;
    }
}
