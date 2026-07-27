<?php

namespace Tests\Feature\Congnghe;

use App\Models\SystemAccount;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CongngheAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): SystemAccount
    {
        return SystemAccount::factory()->role(SystemRole::Admin)->create();
    }

    public function test_admin_can_view_content_manager(): void
    {
        $this->actingAs($this->admin(), 'system')
            ->get('/congnghe/quan-tri')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('CongngheAdmin/Index')
                ->has('sections', 13)
                ->has('icons')
                ->has('metricKeys')
                ->where('can.manage', true)
            );
    }

    public static function nonAdminRoles(): array
    {
        return [
            'lead' => [SystemRole::Lead],
            'member' => [SystemRole::Member],
            'viewer' => [SystemRole::Viewer],
        ];
    }

    /**
     * @dataProvider nonAdminRoles
     */
    public function test_non_admin_cannot_view_content_manager(SystemRole $role): void
    {
        $account = SystemAccount::factory()->role($role)->create();

        $this->actingAs($account, 'system')
            ->get('/congnghe/quan-tri')
            ->assertForbidden();
    }

    public function test_admin_can_update_section_and_public_page_reflects_it(): void
    {
        $content = config('congnghe.about.content');
        $content['heading']['title'] = 'Tiêu đề mới của ban';

        $this->actingAs($this->admin(), 'system')
            ->from('/congnghe/quan-tri')
            ->put('/congnghe/quan-tri/sections/about', [
                'content' => $content,
                'is_visible' => true,
            ])
            ->assertRedirect('/congnghe/quan-tri');

        $this->assertDatabaseHas('congnghe_sections', ['key' => 'about']);

        $this->actingAs($this->admin(), 'system')
            ->get(route('congnghe'))
            ->assertInertia(fn ($page) => $page
                ->where('content.sections.0.key', 'about')
                ->where('content.sections.0.data.heading.title', 'Tiêu đề mới của ban')
            );
    }

    public function test_admin_can_reset_section_to_default(): void
    {
        $content = config('congnghe.about.content');
        $content['heading']['title'] = 'Đã đổi';

        $admin = $this->admin();

        $this->actingAs($admin, 'system')
            ->from('/congnghe/quan-tri')
            ->put('/congnghe/quan-tri/sections/about', ['content' => $content, 'is_visible' => true]);

        $this->actingAs($admin, 'system')
            ->from('/congnghe/quan-tri')
            ->post('/congnghe/quan-tri/sections/about/reset')
            ->assertRedirect('/congnghe/quan-tri');

        $this->assertDatabaseMissing('congnghe_sections', ['key' => 'about']);

        $this->actingAs($admin, 'system')
            ->get(route('congnghe'))
            ->assertInertia(fn ($page) => $page
                ->where('content.sections.0.data.heading.title', 'Kim chỉ nam cho mọi hoạt động')
            );
    }

    public function test_admin_can_reorder_sections(): void
    {
        $reversed = array_reverse(\App\Support\Congnghe\CongngheContentSchema::orderableKeys());

        $this->actingAs($this->admin(), 'system')
            ->from('/congnghe/quan-tri')
            ->put('/congnghe/quan-tri/order', ['order' => $reversed])
            ->assertRedirect('/congnghe/quan-tri');

        $this->actingAs($this->admin(), 'system')
            ->get(route('congnghe'))
            ->assertInertia(fn ($page) => $page
                ->where('content.sections.0.key', $reversed[0])
            );
    }

    public function test_hidden_section_is_excluded_from_public_page(): void
    {
        $content = config('congnghe.culture.content');

        $this->actingAs($this->admin(), 'system')
            ->from('/congnghe/quan-tri')
            ->put('/congnghe/quan-tri/sections/culture', [
                'content' => $content,
                'is_visible' => false,
            ]);

        $this->actingAs($this->admin(), 'system')
            ->get(route('congnghe'))
            ->assertInertia(fn ($page) => $page->has('content.sections', 8));
    }

    public function test_update_validation_rejects_empty_required_title(): void
    {
        $content = config('congnghe.about.content');
        $content['heading']['title'] = '';

        $this->actingAs($this->admin(), 'system')
            ->from('/congnghe/quan-tri')
            ->put('/congnghe/quan-tri/sections/about', ['content' => $content, 'is_visible' => true])
            ->assertSessionHasErrors('content.heading.title');
    }
}
