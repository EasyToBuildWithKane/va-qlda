<?php

namespace Tests\Feature;

use App\Models\SystemAccount;
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
            ->getJson(route('notifications.index', ['tab' => 'all']))
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_guest_cannot_access_notifications_json(): void
    {
        $this->getJson(route('notifications.unread-count'))
            ->assertUnauthorized();
    }
}
