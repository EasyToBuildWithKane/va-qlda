<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Models\SystemAccount;
use App\Support\Enums\ProjectAttachmentCategory;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Preview nhúng trong iframe chỉ hiển thị được khi server trả `inline`.
 * Trước đây mọi request đều là `attachment` nên PDF bị tải xuống thay vì mở.
 */
class ProjectAttachmentPreviewHeadersTest extends TestCase
{
    use RefreshDatabase;

    private function makePdfAttachment(Project $project, SystemAccount $account): ProjectAttachment
    {
        $path = "projects/{$project->id}/customer/spec.pdf";
        Storage::disk('public')->put($path, '%PDF-1.4 fake');

        return ProjectAttachment::query()->create([
            'project_id' => $project->id,
            'category' => ProjectAttachmentCategory::Customer,
            'is_folder' => false,
            'original_name' => 'spec.pdf',
            'path' => $path,
            'mime_type' => 'application/pdf',
            'size' => Storage::disk('public')->size($path),
            'uploaded_by_id' => $account->employee_id,
        ]);
    }

    public function test_file_is_served_inline_for_preview(): void
    {
        Storage::fake('public');

        $account = SystemAccount::factory()->role(SystemRole::Admin)->create();
        $project = Project::factory()->create();
        $attachment = $this->makePdfAttachment($project, $account);

        $response = $this->actingAs($account, 'system')
            ->get("/projects/{$project->id}/attachments/{$attachment->id}/file");

        $response->assertOk();
        $this->assertStringStartsWith(
            'inline',
            (string) $response->headers->get('Content-Disposition'),
        );
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    /**
     * SVG tải lên có thể chứa <script>; trả `inline` sẽ cho nó chạy trên
     * origin của app (stored XSS). Phải luôn ép tải xuống.
     */
    public function test_svg_is_never_served_inline(): void
    {
        Storage::fake('public');

        $account = SystemAccount::factory()->role(SystemRole::Admin)->create();
        $project = Project::factory()->create();

        $path = "projects/{$project->id}/customer/evil.svg";
        Storage::disk('public')->put(
            $path,
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
        );

        $attachment = ProjectAttachment::query()->create([
            'project_id' => $project->id,
            'category' => ProjectAttachmentCategory::Customer,
            'is_folder' => false,
            'original_name' => 'evil.svg',
            'path' => $path,
            'mime_type' => 'image/svg+xml',
            'size' => Storage::disk('public')->size($path),
            'uploaded_by_id' => $account->employee_id,
        ]);

        $response = $this->actingAs($account, 'system')
            ->get("/projects/{$project->id}/attachments/{$attachment->id}/file");

        $response->assertOk();
        $this->assertStringStartsWith(
            'attachment',
            (string) $response->headers->get('Content-Disposition'),
        );
    }

    public function test_image_is_served_inline(): void
    {
        Storage::fake('public');

        $account = SystemAccount::factory()->role(SystemRole::Admin)->create();
        $project = Project::factory()->create();

        $path = "projects/{$project->id}/customer/photo.png";
        Storage::disk('public')->put($path, 'fake-png-bytes');

        $attachment = ProjectAttachment::query()->create([
            'project_id' => $project->id,
            'category' => ProjectAttachmentCategory::Customer,
            'is_folder' => false,
            'original_name' => 'photo.png',
            'path' => $path,
            'mime_type' => 'image/png',
            'size' => Storage::disk('public')->size($path),
            'uploaded_by_id' => $account->employee_id,
        ]);

        $response = $this->actingAs($account, 'system')
            ->get("/projects/{$project->id}/attachments/{$attachment->id}/file");

        $response->assertOk();
        $this->assertStringStartsWith(
            'inline',
            (string) $response->headers->get('Content-Disposition'),
        );
    }

    public function test_download_flag_forces_attachment_disposition(): void
    {
        Storage::fake('public');

        $account = SystemAccount::factory()->role(SystemRole::Admin)->create();
        $project = Project::factory()->create();
        $attachment = $this->makePdfAttachment($project, $account);

        $response = $this->actingAs($account, 'system')
            ->get("/projects/{$project->id}/attachments/{$attachment->id}/file?download=1");

        $response->assertOk();
        $this->assertStringStartsWith(
            'attachment',
            (string) $response->headers->get('Content-Disposition'),
        );
    }
}
