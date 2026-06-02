<?php

namespace App\Models;

use App\Support\Enums\NotificationCategory;
use App\Support\Enums\NotificationPriority;
use App\Support\Enums\NotificationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $recipient_account_id
 * @property NotificationType $type
 * @property NotificationCategory $category
 * @property NotificationPriority $priority
 */
class AppNotification extends Model
{
    protected $table = 'app_notifications';

    protected $fillable = [
        'recipient_account_id',
        'type',
        'category',
        'priority',
        'title',
        'body',
        'actor_account_id',
        'actor_name',
        'project_id',
        'sprint_id',
        'task_id',
        'entity_type',
        'entity_id',
        'action_url',
        'meta',
        'read_at',
        'acknowledged_at',
        'assigned_to_account_id',
        'is_admin_feed',
    ];

    protected $casts = [
        'type' => NotificationType::class,
        'category' => NotificationCategory::class,
        'priority' => NotificationPriority::class,
        'meta' => 'array',
        'read_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'is_admin_feed' => 'boolean',
    ];

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(SystemAccount::class, 'recipient_account_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(SystemAccount::class, 'actor_account_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function isCritical(): bool
    {
        return $this->priority === NotificationPriority::Critical;
    }
}
