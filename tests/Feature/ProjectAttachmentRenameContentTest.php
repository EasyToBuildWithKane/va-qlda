<?php

namespace Tests\Feature;

use App\Http\Resources\ProjectAttachmentResource;
use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Models\SystemAccount;
use App\Support\Enums\ProjectAttachmentCategory;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectAttachmentRenameContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_rename_folder_and_text_file(): void
    {
        Storage::fake('public');

        $account = SystemAccount::factory()->role(SystemRole::Admin)->create();
        $project = Project::factory()->create();

        $folder = ProjectAttachment::query()->create([
            'project_id' => $project->id,
            'category' => ProjectAttachmentCategory::Customer,
            'is_folder' => true,
            'original_name' => 'Cũ',
            'path' => '',
            'size' => 0,
            'uploaded_by_id' => $account->employee_id,
        ]);

        $this->actingAs($account, 'system')
            ->put("/projects/{$project->id}/attachments/{$folder->id}", [
                'title' => 'Mới',
            ])
            ->assertRedirect();

        $this->assertSame('Mới', $folder->fresh()->original_name);

        $path = "projects/{$project->id}/customer/note.txt";
        Storage::disk('public')->put($path, "hello\n");

        $file = ProjectAttachment::query()->create([
            'project_id' => $project->id,
            'category' => ProjectAttachmentCategory::Customer,
            'parent_id' => $folder->id,
            'is_folder' => false,
            'original_name' => 'note.txt',
            'path' => $path,
            'mime_type' => 'text/plain',
            'size' => 6,
            'uploaded_by_id' => $account->employee_id,
        ]);

        $this->actingAs($account, 'system')
            ->put("/projects/{$project->id}/attachments/{$file->id}", [
                'title' => 'bien-ban',
            ])
            ->assertRedirect();

        $this->assertSame('bien-ban.txt', $file->fresh()->original_name);
    }

    public function test_can_update_text_file_content(): void
    {
        Storage::fake('public');

        $account = SystemAccount::factory()->role(SystemRole::Admin)->create();
        $project = Project::factory()->create();

        $path = "projects/{$project->id}/customer/draft.md";
        Storage::disk('public')->put($path, "# draft\n");

        $file = ProjectAttachment::query()->create([
            'project_id' => $project->id,
            'category' => ProjectAttachmentCategory::Customer,
            'is_folder' => false,
            'original_name' => 'draft.md',
            'path' => $path,
            'mime_type' => 'text/markdown',
            'size' => Storage::disk('public')->size($path),
            'uploaded_by_id' => $account->employee_id,
        ]);

        $this->actingAs($account, 'system')
            ->put("/projects/{$project->id}/attachments/{$file->id}", [
                'content' => "# Bản ghi mới\n\nNội dung cập nhật.",
            ])
            ->assertRedirect();

        $this->assertSame("# Bản ghi mới\n\nNội dung cập nhật.", Storage::disk('public')->get($path));
        $this->assertTrue($file->fresh()->isTextEditable());
        $this->assertSame('markdown', $file->fresh()->previewKind());
    }

    public function test_text_file_exposes_preview_snippet_in_resource(): void
    {
        Storage::fake('public');

        $account = SystemAccount::factory()->role(SystemRole::Admin)->create();
        $project = Project::factory()->create();

        $body = "Dòng một\nDòng hai\nDòng ba";
        $path = "projects/{$project->id}/customer/ghi-chu.txt";
        Storage::disk('public')->put($path, $body);

        $file = ProjectAttachment::query()->create([
            'project_id' => $project->id,
            'category' => ProjectAttachmentCategory::Customer,
            'is_folder' => false,
            'original_name' => 'ghi-chu.txt',
            'path' => $path,
            'mime_type' => 'text/plain',
            'size' => Storage::disk('public')->size($path),
            'uploaded_by_id' => $account->employee_id,
        ]);

        $this->assertSame($body, $file->previewSnippet());

        $payload = (new ProjectAttachmentResource($file))->toArray(Request::create('/'));
        $this->assertSame($body, $payload['preview_snippet']);
    }

    public function test_pdf_has_null_preview_snippet(): void
    {
        Storage::fake('public');

        $account = SystemAccount::factory()->role(SystemRole::Admin)->create();
        $project = Project::factory()->create();

        $path = "projects/{$project->id}/customer/doc.pdf";
        Storage::disk('public')->put($path, '%PDF-1.4 fake');

        $file = ProjectAttachment::query()->create([
            'project_id' => $project->id,
            'category' => ProjectAttachmentCategory::Customer,
            'is_folder' => false,
            'original_name' => 'doc.pdf',
            'path' => $path,
            'mime_type' => 'application/pdf',
            'size' => Storage::disk('public')->size($path),
            'uploaded_by_id' => $account->employee_id,
        ]);

        $this->assertNull($file->previewSnippet());

        $payload = (new ProjectAttachmentResource($file))->toArray(Request::create('/'));
        $this->assertNull($payload['preview_snippet']);
    }
}
