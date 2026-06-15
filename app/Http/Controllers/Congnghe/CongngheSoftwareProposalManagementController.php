<?php

namespace App\Http\Controllers\Congnghe;

use App\Http\Controllers\Controller;
use App\Http\Requests\Congnghe\UpdateCongngheSoftwareProposalRequest;
use App\Http\Resources\CongngheSoftwareProposalResource;
use App\Models\CongngheSoftwareProposal;
use App\Support\Enums\CongngheSoftwareProposalStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CongngheSoftwareProposalManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', CongngheSoftwareProposal::class);

        $query = CongngheSoftwareProposal::query()
            ->withCount('attachments')
            ->latest();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('reference_code', 'like', "%{$search}%")
                    ->orWhere('submitter_name', 'like', "%{$search}%")
                    ->orWhere('submitter_email', 'like', "%{$search}%")
                    ->orWhere('department', 'like', "%{$search}%");
            });
        }

        if ($request->query('email_pending') === '1') {
            $query->whereNull('email_sent_at');
        }

        $perPage = min(max((int) $request->query('per_page', 20), 5), 50);

        return Inertia::render('Congnghe/Proposals/Index', [
            'proposals' => CongngheSoftwareProposalResource::collection(
                $query->paginate($perPage)->withQueryString(),
            ),
            'filters' => (object) $request->only(['status', 'q', 'email_pending', 'per_page']),
            'summary' => [
                'total' => CongngheSoftwareProposal::count(),
                'new' => CongngheSoftwareProposal::where('status', CongngheSoftwareProposalStatus::New)->count(),
                'in_progress' => CongngheSoftwareProposal::where('status', CongngheSoftwareProposalStatus::InProgress)->count(),
                'done' => CongngheSoftwareProposal::where('status', CongngheSoftwareProposalStatus::Done)->count(),
                'email_pending' => CongngheSoftwareProposal::whereNull('email_sent_at')->count(),
            ],
            'options' => [
                'statuses' => CongngheSoftwareProposalStatus::options(),
            ],
            'can' => [
                'manage' => $request->user()->can('viewAny', CongngheSoftwareProposal::class),
            ],
        ]);
    }

    public function show(CongngheSoftwareProposal $proposal): Response
    {
        $this->authorize('view', $proposal);

        $proposal->load('attachments');

        return Inertia::render('Congnghe/Proposals/Show', [
            'proposal' => (new CongngheSoftwareProposalResource($proposal))->resolve(),
            'options' => [
                'statuses' => CongngheSoftwareProposalStatus::options(),
            ],
        ]);
    }

    public function update(
        UpdateCongngheSoftwareProposalRequest $request,
        CongngheSoftwareProposal $proposal,
    ): RedirectResponse {
        $proposal->update($request->validated());

        return back()->with('success', 'Đã cập nhật trạng thái đề xuất.');
    }
}
