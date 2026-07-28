<?php

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Read-only HRM profile (va_hrm.user_info).
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $code
 * @property string|null $phone
 * @property string|null $company_name
 * @property string|null $department_name
 * @property string|null $unit_name
 * @property string|null $headquarter_name
 * @property string|null $position_name
 * @property string|null $concurrent_position_name
 * @property \Illuminate\Support\Carbon|null $start_working_date
 * @property int|null $department_id
 * @property int|null $company_id
 */
class HrmUserInfo extends Model
{
    protected $connection = 'hrm_mysql';

    protected $table = 'user_info';

    protected $guarded = ['*'];

    protected $casts = [
        'start_working_date' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(static fn () => throw new \LogicException('HrmUserInfo is read-only from QLDA.'));
        static::deleting(static fn () => throw new \LogicException('HrmUserInfo is read-only from QLDA.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(HrmUser::class, 'user_id');
    }
}
