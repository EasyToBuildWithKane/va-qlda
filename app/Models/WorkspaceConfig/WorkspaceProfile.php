<?php

namespace App\Models\WorkspaceConfig;

use App\Models\Department;
use App\Models\SystemAccount;
use App\Support\Enums\WorkspaceProfileStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Per-department workspace shell under /workspace-config.
 *
 * @property int $id
 * @property string $department_code
 * @property string $department_name
 * @property int|null $local_department_id
 * @property WorkspaceProfileStatus|string $status
 * @property string|null $notes
 * @property int|null $created_by
 */
class WorkspaceProfile extends Model
{
    use SoftDeletes;

    protected $table = 'workspace_profiles';

    protected $fillable = [
        'department_code',
        'department_name',
        'local_department_id',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'status' => WorkspaceProfileStatus::class,
    ];

    public function localDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'local_department_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(SystemAccount::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', WorkspaceProfileStatus::Active);
    }

    public function scopeNotArchived(Builder $query): Builder
    {
        return $query->where('status', '!=', WorkspaceProfileStatus::Archived);
    }
}
