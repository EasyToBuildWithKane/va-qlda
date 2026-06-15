<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\OrgTeam;
use App\Models\OrgTeamMember;
use App\Models\SystemAccount;
use App\Support\Auth\TechLoginAccess;
use App\Support\Enums\SystemRole;
use App\Support\OrgTeam\OrgTeamOverviewBuilder;
use App\Support\OrgTeamTreeBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CongngheTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_congnghe(): void
    {
        $this->get('/congnghe')->assertRedirect(route('tech.login'));
    }

    public function test_authenticated_member_can_view_congnghe(): void
    {
        $account = SystemAccount::factory()->role(SystemRole::Member)->create();

        $this->actingAs($account, 'system')
            ->get('/congnghe')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Congnghe/Index')
                ->has('metrics')
                ->has('metrics.projects')
                ->has('metrics.orgPeople')
                ->has('metrics.members')
                ->has('phases')
                ->has('products')
                ->has('org.overview')
                ->has('org.forest')
                ->where('portal.canEnterQlda', false)
            );
    }

    public function test_whitelisted_email_sees_qlda_entry_on_congnghe(): void
    {
        $allowed = TechLoginAccess::allowedEmails()[0];
        $employee = Employee::factory()->create(['email' => $allowed]);
        $account = SystemAccount::factory()->role(SystemRole::Member)->forEmployee($employee)->create();

        $this->actingAs($account, 'system')
            ->get('/congnghe')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('portal.canEnterQlda', true)
                ->where('portal.qldaHome', '/dashboard')
            );
    }

    public function test_admin_can_enter_qlda_from_congnghe_portal_props(): void
    {
        $account = SystemAccount::factory()->role(SystemRole::Admin)->create();

        $this->actingAs($account, 'system')
            ->get('/congnghe')
            ->assertInertia(fn ($page) => $page->where('portal.canEnterQlda', true));
    }

    public function test_viewer_can_view_congnghe(): void
    {
        $account = SystemAccount::factory()->role(SystemRole::Viewer)->create();

        $this->actingAs($account, 'system')
            ->get('/congnghe')
            ->assertOk();
    }

    public function test_congnghe_forest_resolves_nested_phong_cong_nghe_branch(): void
    {
        $otherRoot = OrgTeam::create(['name' => 'Ban Giám hiệu', 'level' => 1, 'sort_order' => 0]);
        $cntt = OrgTeam::create([
            'name' => 'Phòng Công nghệ thông tin',
            'level' => 2,
            'parent_id' => $otherRoot->id,
            'sort_order' => 1,
        ]);

        $leader = Employee::factory()->create();
        $cntt->update(['leader_id' => $leader->id]);

        $members = Employee::factory()->count(12)->create();
        foreach ($members as $i => $employee) {
            OrgTeamMember::create([
                'org_team_id' => $cntt->id,
                'employee_id' => $employee->id,
                'sort_order' => $i,
            ]);
        }

        $forest = OrgTeamTreeBuilder::congngheForest();
        $this->assertCount(1, $forest);
        $this->assertSame('Phòng Công nghệ thông tin', $forest[0]['name']);
        $this->assertCount(12, $forest[0]['members'] ?? []);

        $overview = OrgTeamOverviewBuilder::buildFromForest($forest);
        $this->assertSame(13, $overview['people_total']);
    }

    public function test_member_can_view_software_proposal_form(): void
    {
        $account = SystemAccount::factory()->role(SystemRole::Member)->create();

        $this->actingAs($account, 'system')
            ->get('/congnghe/de-xuat')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Congnghe/Proposal')
                ->has('defaults')
                ->has('departmentOptions')
            );
    }

    public function test_member_can_submit_software_proposal_and_mail_is_sent(): void
    {
        Mail::fake();

        $account = SystemAccount::factory()->role(SystemRole::Member)->create();

        $this->actingAs($account, 'system')
            ->post('/congnghe/de-xuat', [
                'name' => 'Nguyễn Test',
                'email' => 'tester@vaschools.edu.vn',
                'department' => 'Phòng Học vụ',
                'title' => 'Phần mềm đăng ký học',
                'content' => 'Cần module đăng ký trực tuyến cho phụ huynh.',
            ])
            ->assertRedirect(route('congnghe.proposal'));

        Mail::assertSent(\App\Mail\CongngheSoftwareProposalMail::class, function (\App\Mail\CongngheSoftwareProposalMail $mail) {
            return $mail->proposal->title === 'Phần mềm đăng ký học'
                && $mail->proposal->submitter_email === 'tester@vaschools.edu.vn';
        });

        $this->assertDatabaseHas('congnghe_software_proposals', [
            'submitter_email' => 'tester@vaschools.edu.vn',
            'title' => 'Phần mềm đăng ký học',
            'status' => 'new',
        ]);
    }

    public function test_software_proposal_accepts_file_attachments(): void
    {
        Mail::fake();

        $account = SystemAccount::factory()->role(SystemRole::Member)->create();
        $file = UploadedFile::fake()->create('yeu-cau.pdf', 120, 'application/pdf');

        $this->actingAs($account, 'system')
            ->post('/congnghe/de-xuat', [
                'name' => 'Nguyễn Test',
                'email' => 'tester@vaschools.edu.vn',
                'department' => 'Phòng Học vụ',
                'title' => 'Đề xuất có file',
                'content' => 'Nội dung.',
                'attachments' => [$file],
            ])
            ->assertRedirect(route('congnghe.proposal'));

        Mail::assertSent(\App\Mail\CongngheSoftwareProposalMail::class, function (\App\Mail\CongngheSoftwareProposalMail $mail) {
            return $mail->proposal->attachments->count() === 1;
        });

        $this->assertDatabaseCount('congnghe_software_proposal_attachments', 1);
    }

    public function test_lead_can_view_proposals_index(): void
    {
        $account = SystemAccount::factory()->role(SystemRole::Lead)->create();

        $this->actingAs($account, 'system')
            ->get(route('congnghe.proposals.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Congnghe/Proposals/Index'));
    }

    public function test_member_cannot_view_proposals_index(): void
    {
        $account = SystemAccount::factory()->role(SystemRole::Member)->create();

        $this->actingAs($account, 'system')
            ->get(route('congnghe.proposals.index'))
            ->assertForbidden();
    }
}
