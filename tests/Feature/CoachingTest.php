<?php

namespace Tests\Feature;

use App\Models\CoachingAssignment;
use App\Models\CoachingCourse;
use App\Models\CoachingSession;
use App\Models\CoachingSessionMaterial;
use App\Models\SystemAccount;
use App\Support\Coaching\CoachingFinancialSummary;
use App\Support\Enums\CoachingAssignmentStatus;
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
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Coaching/Dashboard')
                ->has('revenueSeries')
                ->has('dailySeries'));
    }

    public function test_coaching_daily_series_aggregates_completed_sessions_by_date(): void
    {
        $course = CoachingCourse::create([
            'name' => 'Khóa thống kê ngày',
            'status' => CoachingCourseStatus::Active,
            'hourly_rate' => 100000,
        ]);
        CoachingSession::create([
            'course_id' => $course->id,
            'title' => 'Buổi A',
            'session_number' => 1,
            'date' => '2026-06-10',
            'total_hours' => 2,
            'status' => CoachingSessionStatus::Completed,
        ]);
        CoachingSession::create([
            'course_id' => $course->id,
            'title' => 'Buổi B',
            'session_number' => 2,
            'date' => '2026-06-10',
            'total_hours' => 1,
            'status' => CoachingSessionStatus::Completed,
        ]);

        $series = CoachingFinancialSummary::dailySeries(2026, 6);
        $day10 = collect($series)->firstWhere('day', '2026-06-10');

        $this->assertNotNull($day10);
        $this->assertSame(3.0, $day10['hours']);
        $this->assertSame(300000.0, $day10['revenue']);
        $this->assertCount(30, $series);
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

    public function test_courses_index_includes_progress_percent_from_sessions(): void
    {
        $admin = $this->admin();
        $course = CoachingCourse::create([
            'name' => 'Khóa tiến độ',
            'status' => CoachingCourseStatus::Active,
        ]);
        CoachingSession::create([
            'course_id' => $course->id,
            'title' => 'Buổi 1',
            'session_number' => 1,
            'date' => '2026-06-01',
            'total_hours' => 2,
            'status' => CoachingSessionStatus::Completed,
        ]);
        CoachingSession::create([
            'course_id' => $course->id,
            'title' => 'Buổi 2',
            'session_number' => 2,
            'date' => '2026-06-08',
            'total_hours' => 2,
            'status' => CoachingSessionStatus::Pending,
        ]);

        $this->actingAs($admin)
            ->get(route('coaching.courses.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Coaching/Courses/Index')
                ->has('courses.data', 1)
                ->where('courses.data.0.id', $course->id)
                ->where('courses.data.0.progress_percent', 50));
    }

    public function test_admin_can_store_session_on_course(): void
    {
        $admin = $this->admin();
        $course = CoachingCourse::create([
            'name' => 'Khóa buổi',
            'status' => CoachingCourseStatus::Active,
        ]);

        $this->actingAs($admin)
            ->post(route('coaching.courses.sessions.store', $course), [
                'title' => 'Buổi 1',
                'session_number' => 1,
                'date' => '2026-06-14',
                'total_hours' => 2.5,
            ])
            ->assertRedirect(route('coaching.sessions.index', ['course' => $course->id]))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('coaching_sessions', [
            'course_id' => $course->id,
            'title' => 'Buổi 1',
            'session_number' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('coaching.sessions.index', ['course' => $course->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Coaching/Sessions/Index')
                ->has('selectedCourse.id')
                ->where('selectedCourse.next_session_number', 2));
    }

    public function test_admin_can_view_sessions_schedule_and_index(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('coaching.sessions.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Coaching/Sessions/Index')
                ->has('summary.total'));

        $this->actingAs($admin)
            ->get(route('coaching.sessions.schedule'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Coaching/Sessions/Schedule'));
    }

    public function test_sessions_index_includes_material_and_assignment_counts(): void
    {
        $admin = $this->admin();
        $course = CoachingCourse::create([
            'name' => 'Khóa đếm TL/BT',
            'status' => CoachingCourseStatus::Active,
        ]);
        $session = CoachingSession::create([
            'course_id' => $course->id,
            'title' => 'Buổi có TL/BT',
            'session_number' => 1,
            'status' => CoachingSessionStatus::Pending,
        ]);
        CoachingSessionMaterial::create([
            'session_id' => $session->id,
            'type' => CoachingMaterialType::GoogleDocs,
            'title' => 'Slide',
            'url' => 'https://docs.google.com/document/d/abc/edit',
            'sort_order' => 0,
        ]);
        CoachingAssignment::create([
            'session_id' => $session->id,
            'title' => 'Bài 1',
            'status' => CoachingAssignmentStatus::Todo,
        ]);

        $this->actingAs($admin)
            ->get(route('coaching.sessions.index', ['course' => $course->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Coaching/Sessions/Index')
                ->has('sessions.data', 1)
                ->where('sessions.data.0.id', $session->id)
                ->where('sessions.data.0.materials_count', 1)
                ->where('sessions.data.0.assignments_count', 1)
                ->where('summary.with_materials', 1)
                ->where('summary.with_assignments', 1));
    }

    public function test_duplicate_session_number_returns_validation_error(): void
    {
        $admin = $this->admin();
        $course = CoachingCourse::create([
            'name' => 'Khóa dup',
            'status' => CoachingCourseStatus::Active,
        ]);
        CoachingSession::create([
            'course_id' => $course->id,
            'title' => 'Buổi 1',
            'session_number' => 1,
            'status' => CoachingSessionStatus::Pending,
        ]);

        $this->actingAs($admin)
            ->post(route('coaching.courses.sessions.store', $course), [
                'title' => 'Buổi trùng',
                'session_number' => 1,
            ])
            ->assertSessionHasErrors('session_number');
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

    public function test_assignment_done_requires_completion_notes(): void
    {
        $admin = $this->admin();
        $course = CoachingCourse::create([
            'name' => 'Assign',
            'status' => CoachingCourseStatus::Active,
        ]);
        $session = CoachingSession::create([
            'course_id' => $course->id,
            'title' => 'Buổi 1',
            'session_number' => 1,
            'status' => CoachingSessionStatus::Pending,
        ]);
        $assignment = CoachingAssignment::create([
            'session_id' => $session->id,
            'title' => 'BT1',
            'status' => CoachingAssignmentStatus::Todo,
        ]);

        $this->actingAs($admin)
            ->patch(route('coaching.assignments.update', $assignment), [
                'status' => CoachingAssignmentStatus::Done->value,
            ])
            ->assertSessionHasErrors('notes');

        $this->actingAs($admin)
            ->patch(route('coaching.assignments.update', $assignment), [
                'status' => CoachingAssignmentStatus::Done->value,
                'notes' => 'Đã nộp link GitHub và demo.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('coaching_assignments', [
            'id' => $assignment->id,
            'status' => CoachingAssignmentStatus::Done->value,
            'notes' => 'Đã nộp link GitHub và demo.',
        ]);
    }

    public function test_student_can_complete_assignment_with_notes(): void
    {
        $student = SystemAccount::factory()->role(SystemRole::Member)->create();
        $course = CoachingCourse::create([
            'name' => 'HV',
            'status' => CoachingCourseStatus::Active,
            'student_id' => $student->employee_id,
        ]);
        $session = CoachingSession::create([
            'course_id' => $course->id,
            'title' => 'Buổi 1',
            'session_number' => 1,
            'status' => CoachingSessionStatus::Pending,
        ]);
        $assignment = CoachingAssignment::create([
            'session_id' => $session->id,
            'title' => 'Làm lab',
            'status' => CoachingAssignmentStatus::Todo,
        ]);

        $this->actingAs($student)
            ->patch(route('coaching.assignments.update', $assignment), [
                'status' => CoachingAssignmentStatus::Done->value,
                'notes' => 'Hoàn thành lab, đã push code.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('coaching_assignments', [
            'id' => $assignment->id,
            'status' => CoachingAssignmentStatus::Done->value,
        ]);
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

    public function test_admin_can_delete_session(): void
    {
        $admin = $this->admin();
        $course = CoachingCourse::create([
            'name' => 'Del',
            'status' => CoachingCourseStatus::Active,
        ]);
        $session = CoachingSession::create([
            'course_id' => $course->id,
            'title' => 'Buổi xóa',
            'session_number' => 1,
            'status' => CoachingSessionStatus::Pending,
        ]);

        $this->actingAs($admin)
            ->delete(route('coaching.sessions.destroy', $session))
            ->assertRedirect(route('coaching.sessions.index'));

        $this->assertDatabaseMissing('coaching_sessions', ['id' => $session->id]);
    }

    public function test_sessions_export_returns_json_for_filters(): void
    {
        $admin = $this->admin();
        $course = CoachingCourse::create([
            'name' => 'Export',
            'status' => CoachingCourseStatus::Active,
        ]);
        CoachingSession::create([
            'course_id' => $course->id,
            'title' => 'Buổi A',
            'session_number' => 1,
            'status' => CoachingSessionStatus::Pending,
        ]);

        $this->actingAs($admin)
            ->getJson(route('coaching.sessions.export', ['course' => $course->id]))
            ->assertOk()
            ->assertJsonPath('meta.exported', 1)
            ->assertJsonCount(1, 'data');
    }
}
