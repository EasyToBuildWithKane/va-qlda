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
        $this->assertSame('text', $file->fresh()->previewKind());
    }
}
