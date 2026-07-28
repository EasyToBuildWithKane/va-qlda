<?php

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Read-only HRM account (va_hrm.users). Do not persist changes from QLDA.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $avatar
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class HrmUser extends Model
{
    use SoftDeletes;

    protected $connection = 'hrm_mysql';

    protected $table = 'users';

    protected $guarded = ['*'];

    /** @var array<int, string> */
    protected $hidden = [
        'password',
        'remember_token',
        'google_id',
        'google_access_token',
        'google_refresh_token',
        'google_token_expires_at',
        'google_scopes',
    ];

    protected static function booted(): void
    {
        static::saving(static fn () => throw new \LogicException('HrmUser is read-only from QLDA.'));
        static::deleting(static fn () => throw new \LogicException('HrmUser is read-only from QLDA.'));
    }

    public function info(): HasOne
    {
        return $this->hasOne(HrmUserInfo::class, 'user_id');
    }
}
