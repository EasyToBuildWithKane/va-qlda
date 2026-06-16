<?php

namespace App\Models;

use App\Support\Enums\CredentialCategory;
use App\Support\Enums\CredentialEnvironment;
use App\Support\Enums\CredentialPermission;
use App\Support\Enums\CredentialStatus;
use App\Support\Enums\CredentialType;
use App\Support\Enums\SystemRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $name
 * @property CredentialType $credential_type
 * @property CredentialCategory $system_category
 */
class Credential extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'credential_type',
        'system_category',
        'login_url',
        'username',
        'login_password',
        'email',
        'phone',
        'provider_name',
        'description',
        'notes',
        'project_id',
        'department_id',
        'owner_id',
        'environment',
        'status',
        'mfa_enabled',
        'recovery_email',
        'recovery_phone',
        'expires_at',
        'password_changed_at',
        'password_expires_at',
        'is_shared',
        'is_critical',
        'badges',
        'meta',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'credential_type' => CredentialType::class,
        'system_category' => CredentialCategory::class,
        'environment' => CredentialEnvironment::class,
        'status' => CredentialStatus::class,
        'login_password' => 'encrypted',
        'mfa_enabled' => 'boolean',
        'is_shared' => 'boolean',
        'is_critical' => 'boolean',
        'badges' => 'array',
        'meta' => 'array',
        'expires_at' => 'datetime',
        'password_changed_at' => 'datetime',
        'password_expires_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(SystemAccount::class, 'owner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(SystemAccount::class, 'created_by');
    }

    public function accessGrants(): HasMany
    {
        return $this->hasMany(CredentialAccessGrant::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(CredentialAuditLog::class);
    }

    public function passwordHistories(): HasMany
    {
        return $this->hasMany(CredentialPasswordHistory::class);
    }

    public function outgoingRelations(): HasMany
    {
        return $this->hasMany(CredentialRelation::class, 'source_id');
    }

    public function incomingRelations(): HasMany
    {
        return $this->hasMany(CredentialRelation::class, 'target_id');
    }

    public function accessRequests(): HasMany
    {
        return $this->hasMany(CredentialAccessRequest::class);
    }

    public function scopeVisibleTo(Builder $query, SystemAccount $account): Builder
    {
        if ($account->role === SystemRole::Admin) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($account) {
            $q->where('owner_id', $account->id)
                ->orWhere('created_by', $account->id)
                ->orWhereHas('accessGrants', fn (Builder $g) => $g
                    ->where('account_id', $account->id)
                    ->where(function (Builder $g2) {
                        $g2->whereNull('expires_at')
                            ->orWhere('expires_at', '>', now());
                    }));
        });
    }

    public function grantFor(SystemAccount $account): ?CredentialAccessGrant
    {
        return $this->accessGrants()
            ->where('account_id', $account->id)
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();
    }

    public function hasPermission(SystemAccount $account, CredentialPermission $permission): bool
    {
        if ($account->role === SystemRole::Admin) {
            return true;
        }

        if ($this->owner_id === $account->id || $this->created_by === $account->id) {
            return true;
        }

        $grant = $this->grantFor($account);
        if (! $grant) {
            return false;
        }

        $permissions = $grant->permissions ?? [];

        return in_array($permission->value, $permissions, true);
    }
}
