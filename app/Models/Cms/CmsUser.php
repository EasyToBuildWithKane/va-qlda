<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Read-only CMS account (table `users`). Do not persist changes from QLDA.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $avatar
 */
class CmsUser extends Model
{
    use SoftDeletes;

    protected $connection = 'cms_mysql';

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
        static::saving(static fn () => throw new \LogicException('CmsUser is read-only from QLDA.'));
        static::deleting(static fn () => throw new \LogicException('CmsUser is read-only from QLDA.'));
    }

    public function info(): HasOne
    {
        return $this->hasOne(CmsUserInfo::class, 'user_id');
    }
}
