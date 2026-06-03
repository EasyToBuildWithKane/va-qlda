<?php

namespace App\Http\Controllers\AiAccount;

use App\Http\Controllers\Controller;
use App\Models\AiAccount;
use App\Support\Enums\AiAccountCostUnit;
use App\Support\Enums\AiAccountGroupFunction;
use App\Support\Enums\AiPurchaseProposalStatus;
use App\Support\Enums\ProposalType;
use App\Support\Enums\SystemRole;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiAccountPageController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', AiAccount::class);

        return Inertia::render('AiAccount/Index', [
            'can' => [
                'create' => $request->user()->can('create', AiAccount::class),
                'trigger_reminder' => $request->user()->can('triggerReminder', AiAccount::class),
            ],
            'options' => [
                'group_function' => AiAccountGroupFunction::options(),
                'cost_unit' => AiAccountCostUnit::options(),
                'license_types' => config('ai_accounts.license_types', []),
            ],
            'exchange_rate' => (int) config('ai_accounts.exchange_rate', 25_500),
        ]);
    }

    public function costReport(Request $request): Response
    {
        $this->authorize('viewAny', AiAccount::class);

        $user = $request->user();
        $user->loadMissing('employee');
        $employee = $user->employee;
        $dept = is_array($employee?->meta) ? ($employee->meta['department_name'] ?? null) : null;

        return Inertia::render('AiAccount/CostReport', [
            'can' => [
                'create' => $request->user()->can('create', AiAccount::class),
                'propose' => $request->user()->can('create', \App\Models\AiPurchaseProposal::class),
                'review_proposals' => $request->user()->role === SystemRole::Admin,
            ],
            'options' => [
                'group_function' => AiAccountGroupFunction::options(),
                'cost_unit' => AiAccountCostUnit::options(),
                'license_types' => config('ai_accounts.license_types', []),
                'proposal_type' => ProposalType::options(),
                'proposal_status' => AiPurchaseProposalStatus::options(),
                'purchase_type' => [
                    ['value' => 'new', 'label' => 'Mua mới'],
                    ['value' => 'renewal', 'label' => 'Gia hạn'],
                ],
            ],
            'proposal_defaults' => [
                'send_to' => config('ai_accounts.proposal.send_to_default'),
                'objectives' => config('ai_accounts.proposal.objectives_sample'),
                'proposer_name' => $employee?->full_name ?? $user->username,
                'proposer_position' => $employee?->role_title,
                'proposer_department' => $dept,
                'recipient_email' => $employee?->email,
                'recipient_phone' => $employee?->phone,
            ],
        ]);
    }
}
