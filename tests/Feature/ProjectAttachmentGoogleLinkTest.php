<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\SystemAccount;
use App\Support\Enums\ProjectAttachmentCategory;
use App\Support\Enums\SystemRole;
use App\Support\GoogleWorkspaceUrl;
use App\Support\ProjectAttachmentExternalUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectAttachmentGoogleLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_workspace_url_parser(): void
    {
        $doc = GoogleWorkspaceUrl::parse('https://docs.google.com/document/d/abc123_XYZ/edit?usp=sharing');
        $this->assertNotNull($doc);
        $this->assertSame('document', $doc['type']);
        $this->assertStringContainsString('/document/d/abc123_XYZ/preview', $doc['embed_url']);

        $sheet = GoogleWorkspaceUrl::parse('https://docs.google.com/spreadsheets/d/sheet99/edit#gid=0');
        $this->assertNotNull($sheet);
        $this->assertSame('spreadsheet', $sheet['type']);
        $this->assertStringContainsString('/spreadsheets/d/sheet99/preview', $sheet['embed_url']);
    }

    public function test_contributor_can_store_google_doc_link(): void
    {
        $account = SystemAccount::factory()->role(SystemRole::Admin)->create();
        $project = Project::factory()->create();

        $url = 'https://docs.google.com/document/d/testDocId123/edit';

        $response = $this->actingAs($account, 'system')->post("/projects/{$project->id}/attachments", [
            'category' => ProjectAttachmentCategory::Customer->value,
            'title' => 'Tài liệu mẫu',
            'external_url' => $url,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('project_attachments', [
            'project_id' => $project->id,
            'original_name' => 'Tài liệu mẫu',
            'external_url' => 'https://docs.google.com/document/d/testDocId123/edit',
            'path' => '',
        ]);
    }

    public function test_contributor_can_store_direct_pdf_link(): void
    {
        $account = SystemAccount::factory()->role(SystemRole::Admin)->create();
        $project = Project::factory()->create();

        $url = 'https://cdn.example.com/specs/phase-1.pdf';

        $response = $this->actingAs($account, 'system')->post("/projects/{$project->id}/attachments", [
            'category' => ProjectAttachmentCategory::Customer->value,
            'title' => 'Đặc tả PDF',
            'external_url' => $url,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('project_attachments', [
            'project_id' => $project->id,
            'original_name' => 'Đặc tả PDF',
            'external_url' => $url,
            'mime_type' => 'application/pdf',
            'path' => '',
        ]);
    }

    public function test_project_attachment_external_url_parser_accepts_pdf_and_drive(): void
    {
        $pdf = ProjectAttachmentExternalUrl::parse('https://files.example.org/docs/report.pdf?v=2');
        $this->assertNotNull($pdf);
        $this->assertSame('pdf', $pdf['type']);
        $this->assertSame('application/pdf', $pdf['mime_type']);

        $drive = ProjectAttachmentExternalUrl::parse('https://drive.google.com/file/d/abcPDF99/view?usp=sharing');
        $this->assertNotNull($drive);
        $this->assertSame('pdf', $drive['type']);
        $this->assertStringContainsString('/file/d/abcPDF99/preview', $drive['embed_url']);
    }
}
