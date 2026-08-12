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

    public function test_contributor_can_create_blank_file_in_folder(): void
    {
        Storage::fake('public');

        $account = SystemAccount::factory()->role(SystemRole::Admin)->create();
        $project = Project::factory()->create();

        $this->actingAs($account, 'system')->post("/projects/{$project->id}/attachments", [
            'category' => ProjectAttachmentCategory::Customer->value,
            'is_folder' => true,
            'folder_name' => 'Ghi chú',
        ])->assertRedirect();

        $folder = ProjectAttachment::query()->where('project_id', $project->id)->where('is_folder', true)->firstOrFail();

        $this->actingAs($account, 'system')->post("/projects/{$project->id}/attachments", [
            'category' => ProjectAttachmentCategory::Customer->value,
            'parent_id' => $folder->id,
            'is_new_file' => true,
            'file_name' => 'Bien ban hop',
            'file_type' => 'txt',
        ])->assertRedirect();

        $file = ProjectAttachment::query()
            ->where('project_id', $project->id)
            ->where('parent_id', $folder->id)
            ->where('is_folder', false)
            ->first();

        $this->assertNotNull($file);
        $this->assertSame('Bien ban hop.txt', $file->original_name);
        $this->assertSame('text/plain', $file->mime_type);
        Storage::disk('public')->assertExists($file->path);
    }

    public function test_create_blank_file_requires_name(): void
    {
        $account = SystemAccount::factory()->role(SystemRole::Admin)->create();
        $project = Project::factory()->create();

        $this->actingAs($account, 'system')->post("/projects/{$project->id}/attachments", [
            'category' => ProjectAttachmentCategory::Customer->value,
            'is_new_file' => true,
            'file_name' => '',
            'file_type' => 'txt',
        ])->assertSessionHasErrors('file_name');
    }

    public function test_create_blank_file_rejects_non_txt_type(): void
    {
        $account = SystemAccount::factory()->role(SystemRole::Admin)->create();
        $project = Project::factory()->create();

        $this->actingAs($account, 'system')->post("/projects/{$project->id}/attachments", [
            'category' => ProjectAttachmentCategory::Customer->value,
            'is_new_file' => true,
            'file_name' => 'Ghi chu',
            'file_type' => 'md',
        ])->assertSessionHasErrors('file_type');
    }

    public function test_contributor_can_move_file_into_folder(): void
    {
        Storage::fake('public');

        $account = SystemAccount::factory()->role(SystemRole::Admin)->create();
        $project = Project::factory()->create();

        $folder = ProjectAttachment::query()->create([
            'project_id' => $project->id,
            'category' => ProjectAttachmentCategory::Customer,
            'is_folder' => true,
            'original_name' => 'Hợp đồng',
            'path' => '',
            'size' => 0,
        ]);

        $file = UploadedFile::fake()->create('spec.pdf', 100, 'application/pdf');
        $this->actingAs($account, 'system')->post("/projects/{$project->id}/attachments", [
            'category' => ProjectAttachmentCategory::Customer->value,
            'files' => [$file],
        ])->assertRedirect();

        $attachment = ProjectAttachment::query()
            ->where('project_id', $project->id)
            ->where('original_name', 'spec.pdf')
            ->firstOrFail();

        $this->actingAs($account, 'system')->put("/projects/{$project->id}/attachments/{$attachment->id}", [
            'parent_id' => $folder->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('project_attachments', [
            'id' => $attachment->id,
            'parent_id' => $folder->id,
        ]);
    }

    public function test_cannot_move_folder_into_its_descendant(): void
    {
        $account = SystemAccount::factory()->role(SystemRole::Admin)->create();
        $project = Project::factory()->create();

        $parent = ProjectAttachment::query()->create([
            'project_id' => $project->id,
            'category' => ProjectAttachmentCategory::Customer,
            'is_folder' => true,
            'original_name' => 'Cha',
            'path' => '',
            'size' => 0,
        ]);

        $child = ProjectAttachment::query()->create([
            'project_id' => $project->id,
            'category' => ProjectAttachmentCategory::Customer,
            'parent_id' => $parent->id,
            'is_folder' => true,
            'original_name' => 'Con',
            'path' => '',
            'size' => 0,
        ]);

        $this->actingAs($account, 'system')->put("/projects/{$project->id}/attachments/{$parent->id}", [
            'parent_id' => $child->id,
        ])->assertSessionHasErrors('parent_id');
    }

    public function test_can_move_item_to_category_root(): void
    {
        Storage::fake('public');

        $account = SystemAccount::factory()->role(SystemRole::Admin)->create();
        $project = Project::factory()->create();

        $folder = ProjectAttachment::query()->create([
            'project_id' => $project->id,
            'category' => ProjectAttachmentCategory::Customer,
            'is_folder' => true,
            'original_name' => 'Hộp',
            'path' => '',
            'size' => 0,
        ]);

        $file = UploadedFile::fake()->create('note.txt', 10, 'text/plain');
        $this->actingAs($account, 'system')->post("/projects/{$project->id}/attachments", [
            'category' => ProjectAttachmentCategory::Customer->value,
            'parent_id' => $folder->id,
            'files' => [$file],
        ])->assertRedirect();

        $attachment = ProjectAttachment::query()
            ->where('project_id', $project->id)
            ->where('original_name', 'note.txt')
            ->firstOrFail();

        $this->actingAs($account, 'system')->put("/projects/{$project->id}/attachments/{$attachment->id}", [
            'parent_id' => null,
        ])->assertRedirect();

        $this->assertDatabaseHas('project_attachments', [
            'id' => $attachment->id,
            'parent_id' => null,
        ]);
    }
}
