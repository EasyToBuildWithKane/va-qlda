<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\OrgTeam;
use App\Models\OrgTeamMember;
use App\Models\SystemAccount;
use App\Support\Auth\TechLoginAccess;
use App\Support\Enums\SystemRole;
use App\Support\Options\DepartmentOptions;
use App\Support\OrgTeam\OrgTeamOverviewBuilder;
use App\Support\OrgTeamTreeBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CongngheTest extends TestCase
{
    use RefreshDatabase;

    private function createActiveDepartment(string $name = 'Phòng Học vụ'): Department
    {
        $dept = Department::create([
            'code' => 'DEPT-HV',
            'name' => $name,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        app(DepartmentOptions::class)->flush();

        return $dept;
    }

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
        $dept = $this->createActiveDepartment();
        $account = SystemAccount::factory()->role(SystemRole::Member)->create();

        $this->actingAs($account, 'system')
            ->get('/congnghe/de-xuat')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Congnghe/Proposal')
                ->has('defaults')
                ->has('departmentOptions', 1)
                ->where('departmentOptions.0.id', $dept->id)
            );
    }

    public function test_member_can_submit_software_proposal_and_mail_is_sent(): void
    {
        Mail::fake();

        $dept = $this->createActiveDepartment();
        $account = SystemAccount::factory()->role(SystemRole::Member)->create();

        $this->actingAs($account, 'system')
            ->post('/congnghe/de-xuat', [
                'name' => 'Nguyễn Test',
                'email' => 'tester@vaschools.edu.vn',
                'department_id' => $dept->id,
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
            'department' => 'Phòng Học vụ',
            'status' => 'new',
        ]);
    }

    public function test_software_proposal_accepts_file_attachments(): void
    {
        Mail::fake();

        $dept = $this->createActiveDepartment();
        $account = SystemAccount::factory()->role(SystemRole::Member)->create();
        $file = UploadedFile::fake()->create('yeu-cau.pdf', 120, 'application/pdf');

        $this->actingAs($account, 'system')
            ->post('/congnghe/de-xuat', [
                'name' => 'Nguyễn Test',
                'email' => 'tester@vaschools.edu.vn',
                'department_id' => $dept->id,
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

    public function test_member_can_download_own_proposal_attachment(): void
    {
        Mail::fake();
        Storage::fake('public');
        $dept = $this->createActiveDepartment();
        $account = SystemAccount::factory()->role(SystemRole::Member)->create();
        $file = UploadedFile::fake()->create('yeu-cau.pdf', 120, 'application/pdf');

        $this->actingAs($account, 'system')
            ->post('/congnghe/de-xuat', [
                'name' => 'Nguyễn Test',
                'email' => 'tester@vaschools.edu.vn',
                'department_id' => $dept->id,
                'title' => 'Có file',
                'content' => 'Nội dung.',
                'attachments' => [$file],
            ]);

        $proposal = \App\Models\CongngheSoftwareProposal::query()->with('attachments')->first();
        $attachment = $proposal->attachments->first();

        $this->actingAs($account, 'system')
            ->get(route('congnghe.proposal.mine.attachments.file', [
                'proposal' => $proposal->id,
                'attachment' => $attachment->id,
            ]))
            ->assertOk();
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

    public function test_member_can_view_own_proposals_list(): void
    {
        $account = SystemAccount::factory()->role(SystemRole::Member)->create();

        $this->actingAs($account, 'system')
            ->get(route('congnghe.proposal.mine'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Congnghe/MyProposals'));
    }

    public function test_member_can_view_own_proposal_detail(): void
    {
        Mail::fake();
        $dept = $this->createActiveDepartment();
        $account = SystemAccount::factory()->role(SystemRole::Member)->create();

        $this->actingAs($account, 'system')
            ->post('/congnghe/de-xuat', [
                'name' => 'Nguyễn Test',
                'email' => 'tester@vaschools.edu.vn',
                'department_id' => $dept->id,
                'title' => 'Đề xuất riêng',
                'content' => 'Nội dung.',
            ]);

        $proposalId = \App\Models\CongngheSoftwareProposal::query()->value('id');

        $this->actingAs($account, 'system')
            ->get(route('congnghe.proposal.mine.show', $proposalId))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Congnghe/MyProposalShow')
                ->where('proposal.title', 'Đề xuất riêng')
            );
    }

    public function test_member_cannot_view_other_users_proposal_detail(): void
    {
        $dept = $this->createActiveDepartment();
        $owner = SystemAccount::factory()->role(SystemRole::Member)->create();
        $other = SystemAccount::factory()->role(SystemRole::Member)->create();

        $this->actingAs($owner, 'system')
            ->post('/congnghe/de-xuat', [
                'name' => 'Owner',
                'email' => 'owner@vaschools.edu.vn',
                'department_id' => $dept->id,
                'title' => 'Private',
                'content' => 'Secret.',
            ]);

        $proposalId = \App\Models\CongngheSoftwareProposal::query()->value('id');

        $this->actingAs($other, 'system')
            ->get(route('congnghe.proposal.mine.show', $proposalId))
            ->assertForbidden();
    }
}
