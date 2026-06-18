<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\SystemAccount;
use App\Support\Enums\NotificationCategory;
use App\Support\Enums\NotificationPriority;
use App\Support\Enums\NotificationType;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private function member(): SystemAccount
    {
        return SystemAccount::factory()->role(SystemRole::Member)->create();
    }

    public function test_authenticated_user_can_fetch_unread_count(): void
    {
        $this->actingAs($this->member(), 'system')
            ->getJson(route('notifications.unread-count'))
            ->assertOk()
            ->assertJsonStructure(['count']);
    }

    public function test_authenticated_user_can_fetch_notification_list(): void
    {
        $this->actingAs($this->member(), 'system')
            ->getJson(route('notifications.list', ['tab' => 'all']))
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['total', 'from', 'to', 'current_page', 'last_page', 'per_page', 'has_more'],
            ]);
    }

    public function test_notification_list_respects_per_page_and_page(): void
    {
        $account = $this->member();

        for ($i = 0; $i < 12; $i++) {
            AppNotification::create([
                'recipient_account_id' => $account->id,
                'type' => NotificationType::TaskUpdated,
                'category' => NotificationCategory::Task,
                'priority' => NotificationPriority::Medium,
                'title' => "Thông báo thử {$i}",
            ]);
        }

        $this->actingAs($account, 'system')
            ->getJson(route('notifications.list', ['tab' => 'all', 'per_page' => 5, 'page' => 2]))
            ->assertOk()
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonCount(5, 'data');
    }

    public function test_guest_cannot_access_notifications_json(): void
    {
        $this->getJson(route('notifications.unread-count'))
            ->assertUnauthorized();
    }
}
