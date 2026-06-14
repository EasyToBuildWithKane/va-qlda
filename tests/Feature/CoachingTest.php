<?php

namespace Tests\Feature;

use App\Models\CoachingCourse;
use App\Models\CoachingSession;
use App\Models\SystemAccount;
use App\Support\Enums\CoachingCourseStatus;
use App\Support\Enums\CoachingMaterialType;
use App\Support\Enums\CoachingSessionStatus;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoachingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): SystemAccount
    {
        return SystemAccount::factory()->role(SystemRole::Admin)->create();
    }

    public function test_coaching_dashboard_for_admin(): void
    {
        $this->actingAs($this->admin())
            ->get(route('coaching.dashboard'))
            ->assertOk();
    }

    public function test_admin_can_create_course(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('coaching.courses.store'), [
                'name' => 'Khóa Laravel',
                'status' => CoachingCourseStatus::Active->value,
                'total_fee' => 10000000,
                'hourly_rate' => 500000,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('coaching_courses', [
            'name' => 'Khóa Laravel',
        ]);

        $course = CoachingCourse::query()->where('name', 'Khóa Laravel')->first();
        $this->assertNotNull($course->code);
        $this->assertStringStartsWith('COACH-', $course->code);
    }

    public function test_admin_can_view_course_show_page(): void
    {
        $admin = $this->admin();
        $course = CoachingCourse::create([
            'name' => 'Khóa Vue',
            'status' => CoachingCourseStatus::Active,
        ]);

        $this->actingAs($admin)
            ->get(route('coaching.courses.show', $course))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Coaching/Courses/Show')
                ->has('course.id')
                ->where('course.id', $course->id));
    }

    public function test_coaching_dashboard_counts_student_by_free_text_name(): void
    {
        CoachingCourse::create([
            'name' => 'Khóa tên HV',
            'status' => CoachingCourseStatus::Active,
            'student_name' => 'Nguyễn Văn A',
        ]);

        $this->actingAs($this->admin())
            ->get(route('coaching.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Coaching/Dashboard')
                ->where('summary.students_distinct', 1));
    }

    public function test_viewer_cannot_access_coaching_dashboard(): void
    {
        $viewer = SystemAccount::factory()->role(SystemRole::Viewer)->create();

        $this->actingAs($viewer)
            ->get(route('coaching.dashboard'))
            ->assertForbidden();
    }

    public function test_session_material_rejects_unsafe_embed_url(): void
    {
        $admin = $this->admin();
        $course = CoachingCourse::create([
            'name' => 'Test',
            'status' => CoachingCourseStatus::Active,
        ]);
        $session = CoachingSession::create([
            'course_id' => $course->id,
            'title' => 'Buổi 1',
            'session_number' => 1,
            'status' => CoachingSessionStatus::Pending,
        ]);

        $this->actingAs($admin)
            ->post(route('coaching.sessions.materials.store', $session), [
                'type' => CoachingMaterialType::Youtube->value,
                'title' => 'Evil',
                'url' => 'https://evil.example/watch?v=1',
            ])
            ->assertSessionHasErrors('url');
    }

    public function test_completing_session_syncs_student_progress(): void
    {
        $student = SystemAccount::factory()->role(SystemRole::Member)->create();
        $admin = $this->admin();
        $course = CoachingCourse::create([
            'name' => 'Prog',
            'status' => CoachingCourseStatus::Active,
            'student_id' => $student->employee_id,
        ]);
        $session = CoachingSession::create([
            'course_id' => $course->id,
            'title' => 'Buổi 1',
            'session_number' => 1,
            'status' => CoachingSessionStatus::InProgress,
        ]);

        $this->actingAs($admin)
            ->patch(route('coaching.sessions.update', $session), [
                'status' => CoachingSessionStatus::Completed->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('coaching_progress', [
            'course_id' => $course->id,
            'session_id' => $session->id,
            'system_account_id' => $student->id,
            'is_completed' => true,
        ]);
    }
}
