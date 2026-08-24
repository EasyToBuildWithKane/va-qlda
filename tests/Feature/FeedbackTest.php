<?php

namespace Tests\Feature;

use App\Models\Feedback;
use App\Models\Project;
use App\Models\SystemAccount;
use App\Support\Enums\FeedbackCategory;
use App\Support\Enums\FeedbackStatus;
use App\Support\Enums\SystemRole;
use App\Support\Enums\TaskPriority;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedbackTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): SystemAccount
    {
        return SystemAccount::factory()->role(SystemRole::Admin)->create();
    }

    private function lead(): SystemAccount
    {
        return SystemAccount::factory()->role(SystemRole::TeamLeader)->create();
    }

    private function member(): SystemAccount
    {
        return SystemAccount::factory()->role(SystemRole::Member)->create();
    }

    private function viewer(): SystemAccount
    {
        return SystemAccount::factory()->role(SystemRole::Viewer)->create();
    }

    private function feedbackPayload(array $overrides = []): array
    {
        return array_merge([
            'category' => FeedbackCategory::Improvement->value,
            'title' => 'Test Feedback',
            'description' => 'This is a feedback description.',
            'priority' => TaskPriority::Medium->value,
            'reporter_name' => 'Người dùng',
        ], $overrides);
    }

    private function createFeedback(array $overrides = []): Feedback
    {
        return Feedback::create(array_merge([
            'category' => FeedbackCategory::FeatureRequest->value,
            'title' => 'Existing Feedback',
            'description' => 'Description.',
            'priority' => TaskPriority::Low->value,
            'status' => FeedbackStatus::New->value,
            'reporter_name' => 'Người gửi',
        ], $overrides));
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    public function test_authenticated_user_can_view_feedback(): void
    {
        $this->actingAs($this->member(), 'system')
            ->get('/feedback')
            ->assertOk();
    }

    public function test_viewer_can_view_feedback(): void
    {
        $this->actingAs($this->viewer(), 'system')
            ->get('/feedback')
            ->assertOk();
    }

    public function test_guest_is_redirected_from_feedback(): void
    {
        $this->get('/feedback')->assertRedirect('/login');
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function test_member_can_create_feedback(): void
    {
        $this->actingAs($this->member(), 'system')
            ->post('/feedback', $this->feedbackPayload())
            ->assertRedirect();

        $this->assertDatabaseHas('feedbacks', ['title' => 'Test Feedback']);
    }

    public function test_admin_can_create_feedback(): void
    {
        $this->actingAs($this->admin(), 'system')
            ->post('/feedback', $this->feedbackPayload(['title' => 'Admin Feedback']))
            ->assertRedirect();

        $this->assertDatabaseHas('feedbacks', ['title' => 'Admin Feedback']);
    }

    public function test_viewer_cannot_create_feedback(): void
    {
        $this->actingAs($this->viewer(), 'system')
            ->post('/feedback', $this->feedbackPayload())
            ->assertForbidden();
    }

    public function test_feedback_requires_category_title_description_priority(): void
    {
        $this->actingAs($this->member(), 'system')
            ->post('/feedback', ['reporter_name' => 'Tester'])
            ->assertSessionHasErrors(['category', 'title', 'description', 'priority']);
    }

    public function test_reporter_name_required_without_employee(): void
    {
        $this->actingAs($this->member(), 'system')
            ->post('/feedback', [
                'category' => FeedbackCategory::Other->value,
                'title' => 'No reporter',
                'description' => 'Missing reporter.',
                'priority' => TaskPriority::Low->value,
            ])
            ->assertSessionHasErrors('reporter_name');
    }

    public function test_store_redirects_to_feedback_show(): void
    {
        $response = $this->actingAs($this->member(), 'system')
            ->post('/feedback', $this->feedbackPayload());

        $response->assertRedirect();
        $this->assertMatchesRegularExpression('|/feedback/\d+|', $response->headers->get('Location'));
    }

    public function test_feedback_linked_to_project(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->member(), 'system')
            ->post('/feedback', $this->feedbackPayload(['project_id' => $project->id, 'title' => 'Project Feedback']))
            ->assertRedirect();

        $this->assertDatabaseHas('feedbacks', [
            'title' => 'Project Feedback',
            'project_id' => $project->id,
        ]);
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function test_any_authenticated_user_can_view_feedback_detail(): void
    {
        $feedback = $this->createFeedback();

        $this->actingAs($this->viewer(), 'system')
            ->get("/feedback/{$feedback->id}")
            ->assertOk();
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function test_admin_can_update_feedback(): void
    {
        $feedback = $this->createFeedback();

        $this->actingAs($this->admin(), 'system')
            ->put("/feedback/{$feedback->id}", [
                'title' => 'Updated Feedback',
                'category' => FeedbackCategory::Complaint->value,
                'description' => 'Updated description.',
                'priority' => TaskPriority::High->value,
                'status' => FeedbackStatus::InProgress->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('feedbacks', ['id' => $feedback->id, 'title' => 'Updated Feedback']);
    }

    public function test_unrelated_member_cannot_update_feedback(): void
    {
        $feedback = $this->createFeedback();

        $this->actingAs($this->member(), 'system')
            ->put("/feedback/{$feedback->id}", ['title' => 'Hacked'])
            ->assertForbidden();
    }

    public function test_resolving_feedback_sets_resolved_at(): void
    {
        $feedback = $this->createFeedback();

        $this->actingAs($this->admin(), 'system')
            ->put("/feedback/{$feedback->id}", [
                'title' => $feedback->title,
                'category' => $feedback->category->value,
                'description' => $feedback->description,
                'priority' => $feedback->priority->value,
                'status' => FeedbackStatus::Resolved->value,
            ]);

        $this->assertNotNull($feedback->fresh()->resolved_at);
    }

    // ─── Delete ───────────────────────────────────────────────────────────────

    public function test_admin_can_delete_feedback(): void
    {
        $feedback = $this->createFeedback();

        $this->actingAs($this->admin(), 'system')
            ->delete("/feedback/{$feedback->id}")
            ->assertRedirect(route('feedback.index'));

        $this->assertDatabaseMissing('feedbacks', ['id' => $feedback->id]);
    }

    public function test_member_cannot_delete_feedback(): void
    {
        $feedback = $this->createFeedback();

        $this->actingAs($this->member(), 'system')
            ->delete("/feedback/{$feedback->id}")
            ->assertForbidden();
    }

    public function test_lead_cannot_delete_feedback(): void
    {
        $feedback = $this->createFeedback();

        $this->actingAs($this->lead(), 'system')
            ->delete("/feedback/{$feedback->id}")
            ->assertForbidden();
    }
}
