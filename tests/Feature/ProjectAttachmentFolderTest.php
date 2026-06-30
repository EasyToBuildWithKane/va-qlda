<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Models\SystemAccount;
use App\Support\Enums\ProjectAttachmentCategory;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectAttachmentFolderTest extends TestCase
{
    use RefreshDatabase;

    public function test_contributor_can_create_folder_and_upload_into_it(): void
    {
        Storage::fake('public');

        $account = SystemAccount::factory()->role(SystemRole::Admin)->create();
        $project = Project::factory()->create();

        $this->actingAs($account, 'system')->post("/projects/{$project->id}/attachments", [
            'category' => ProjectAttachmentCategory::Customer->value,
            'is_folder' => true,
            'folder_name' => 'Hợp đồng',
        ])->assertRedirect();

        $folder = ProjectAttachment::query()->where('project_id', $project->id)->where('is_folder', true)->first();
        $this->assertNotNull($folder);
        $this->assertSame('Hợp đồng', $folder->original_name);

        $file = UploadedFile::fake()->create('spec.pdf', 100, 'application/pdf');

        $this->actingAs($account, 'system')->post("/projects/{$project->id}/attachments", [
            'category' => ProjectAttachmentCategory::Customer->value,
            'parent_id' => $folder->id,
            'files' => [$file],
        ])->assertRedirect();

        $this->assertDatabaseHas('project_attachments', [
            'project_id' => $project->id,
            'parent_id' => $folder->id,
            'original_name' => 'spec.pdf',
            'is_folder' => false,
        ]);
    }

    public function test_nested_folder_must_match_parent_category(): void
    {
        $account = SystemAccount::factory()->role(SystemRole::Admin)->create();
        $project = Project::factory()->create();

        $folder = ProjectAttachment::query()->create([
            'project_id' => $project->id,
            'category' => ProjectAttachmentCategory::Images,
            'is_folder' => true,
            'original_name' => 'Ảnh dự án',
            'path' => '',
            'size' => 0,
        ]);

        $this->actingAs($account, 'system')->post("/projects/{$project->id}/attachments", [
            'category' => ProjectAttachmentCategory::Customer->value,
            'is_folder' => true,
            'folder_name' => 'Con sai danh mục',
            'parent_id' => $folder->id,
        ])->assertSessionHasErrors('parent_id');
    }

    public function test_can_create_nested_subfolders(): void
    {
        $account = SystemAccount::factory()->role(SystemRole::Admin)->create();
        $project = Project::factory()->create();

        $this->actingAs($account, 'system')->post("/projects/{$project->id}/attachments", [
            'category' => ProjectAttachmentCategory::Customer->value,
            'is_folder' => true,
            'folder_name' => 'Cấp 1',
        ])->assertRedirect();

        $level1 = ProjectAttachment::query()->where('original_name', 'Cấp 1')->firstOrFail();

        $this->actingAs($account, 'system')->post("/projects/{$project->id}/attachments", [
            'category' => ProjectAttachmentCategory::Customer->value,
            'is_folder' => true,
            'folder_name' => 'Cấp 2',
            'parent_id' => $level1->id,
        ])->assertRedirect();

        $level2 = ProjectAttachment::query()->where('original_name', 'Cấp 2')->firstOrFail();

        $this->actingAs($account, 'system')->post("/projects/{$project->id}/attachments", [
            'category' => ProjectAttachmentCategory::Customer->value,
            'is_folder' => true,
            'folder_name' => 'Cấp 3',
            'parent_id' => $level2->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('project_attachments', [
            'original_name' => 'Cấp 3',
            'parent_id' => $level2->id,
            'is_folder' => true,
        ]);
    }
}
