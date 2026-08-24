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
        $this->get('/congnghe')->assertRedirect(route('login'));
        $this->get('/phongcongnghe')->assertRedirect(route('login'));
        $this->get('/demo_1')->assertRedirect(route('login'));
    }

    public function test_authenticated_member_can_view_congnghe(): void
    {
        $account = SystemAccount::factory()->role(SystemRole::Member)->create();

        $this->actingAs($account, 'system')
            ->get(route('congnghe'))
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
                ->where('portal.canEnterWorkspace', false)
            );
    }

    public function test_congnghe_passes_editable_content_with_defaults(): void
    {
        $account = SystemAccount::factory()->role(SystemRole::Member)->create();

        $this->actingAs($account, 'system')
            ->get(route('congnghe'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('content.hero')
                ->has('content.nav')
                ->has('content.footer')
                ->has('content.sections', 9)
                ->where('content.hero.title_prefix', 'Kiến tạo')
                ->where('content.sections.0.key', 'about')
                ->has('icons')
            );
    }

    public function test_whitelisted_email_sees_workspace_entry_on_congnghe(): void
    {
        $allowed = TechLoginAccess::allowedEmails()[0];
        $employee = Employee::factory()->create(['email' => $allowed]);
        $account = SystemAccount::factory()->role(SystemRole::Member)->forEmployee($employee)->create();

        $this->actingAs($account, 'system')
            ->get(route('congnghe'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('portal.canEnterWorkspace', true)
                ->where('portal.workspaceHome', '/dashboard')
            );
    }

    public function test_admin_can_enter_workspace_from_congnghe_portal_props(): void
    {
        $account = SystemAccount::factory()->role(SystemRole::Admin)->create();

        $this->actingAs($account, 'system')
            ->get(route('congnghe'))
            ->assertInertia(fn ($page) => $page->where('portal.canEnterWorkspace', true));
    }

    public function test_viewer_can_view_congnghe(): void
    {
        $account = SystemAccount::factory()->role(SystemRole::Viewer)->create();

        $this->actingAs($account, 'system')
            ->get(route('congnghe'))
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
        $account = SystemAccount::factory()->role(SystemRole::TeamLeader)->create();

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
            ->assertInertia(fn ($page) => $page
                ->component('Congnghe/MyProposals')
                ->has('chrome.nav.links', 6)
                ->has('chrome.footer.explore_links')
            );
    }

    public function test_member_own_proposals_list_filters_by_email_sent(): void
    {
        Mail::fake();
        $dept = $this->createActiveDepartment();
        $account = SystemAccount::factory()->role(SystemRole::Member)->create();

        $this->actingAs($account, 'system')
            ->post('/congnghe/de-xuat', [
                'name' => 'Nguyễn Test',
                'email' => 'tester@vaschools.edu.vn',
                'department_id' => $dept->id,
                'title' => 'Có email',
                'content' => 'Nội dung.',
            ]);

        $this->actingAs($account, 'system')
            ->get(route('congnghe.proposal.mine', ['email_sent' => '1']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('proposals.data', 1)
                ->where('proposals.data.0.title', 'Có email')
            );

        $this->actingAs($account, 'system')
            ->get(route('congnghe.proposal.mine', ['email_sent' => '0']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('proposals.data', 0));
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

    public function test_reject_proposal_requires_reason_and_sends_email(): void
    {
        Mail::fake();
        $dept = $this->createActiveDepartment();
        $member = SystemAccount::factory()->role(SystemRole::Member)->create();
        $lead = SystemAccount::factory()->role(SystemRole::TeamLeader)->create();

        $this->actingAs($member, 'system')
            ->post('/congnghe/de-xuat', [
                'name' => 'Nguyễn Test',
                'email' => 'tester@vaschools.edu.vn',
                'department_id' => $dept->id,
                'title' => 'Đề xuất từ chối',
                'content' => 'Nội dung.',
            ]);

        $proposal = \App\Models\CongngheSoftwareProposal::query()->first();
        $this->assertNotNull($proposal);

        $this->actingAs($lead, 'system')
            ->put(route('congnghe.proposals.update', $proposal->id), [
                'status' => 'rejected',
            ])
            ->assertSessionHasErrors('rejection_reason');

        $reason = 'Nội dung chưa đủ thông tin kỹ thuật để đánh giá.';

        $this->actingAs($lead, 'system')
            ->put(route('congnghe.proposals.update', $proposal->id), [
                'status' => 'rejected',
                'rejection_reason' => $reason,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $proposal->refresh();
        $this->assertSame('rejected', $proposal->status->value);
        $this->assertSame($reason, $proposal->rejection_reason);
        $this->assertNotNull($proposal->rejection_email_sent_at);

        Mail::assertSent(\App\Mail\CongngheSoftwareProposalRejectedMail::class, function (\App\Mail\CongngheSoftwareProposalRejectedMail $mail) use ($reason) {
            return $mail->hasTo('tester@vaschools.edu.vn')
                && $mail->rejectionReason === $reason;
        });

        $this->actingAs($member, 'system')
            ->get(route('congnghe.proposal.mine.show', $proposal->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('proposal.rejection_reason', $reason)
                ->where('proposal.status.value', 'rejected')
            );
    }
}
