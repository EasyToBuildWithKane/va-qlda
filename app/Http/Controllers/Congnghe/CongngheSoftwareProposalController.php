<?php

namespace App\Http\Controllers\Congnghe;

use App\Http\Controllers\Controller;
use App\Http\Requests\Congnghe\StoreCongngheSoftwareProposalRequest;
use App\Mail\CongngheSoftwareProposalMail;
use App\Services\Congnghe\CongngheSoftwareProposalRecorder;
use App\Support\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class CongngheSoftwareProposalController extends Controller
{
    public function __construct(
        private readonly CongngheSoftwareProposalRecorder $recorder,
    ) {}

    public function create(): Response
    {
        $account = request()->user();
        $employee = $account?->employee;

        return Inertia::render('Congnghe/Proposal', [
            'defaults' => [
                'name' => $employee?->full_name ?? $account?->display_name ?? '',
                'email' => $employee?->email ?? '',
                'department' => '',
            ],
            'departmentOptions' => Options::departments()->values()->all(),
            'recipientEmail' => config('va.congnghe_proposal_email'),
        ]);
    }

    public function store(StoreCongngheSoftwareProposalRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $files = $request->file('attachments', []);

        $proposal = $this->recorder->record(
            (int) $request->user()->id,
            [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'department' => $validated['department'],
                'title' => $validated['title'],
                'content' => $validated['content'],
            ],
            $files,
        );

        $recipient = (string) config('va.congnghe_proposal_email');

        try {
            Mail::to($recipient)->send(new CongngheSoftwareProposalMail($proposal));
            $proposal->forceFill(['email_sent_at' => now(), 'email_error' => null])->saveQuietly();
        } catch (\Throwable $e) {
            report($e);
            $proposal->forceFill([
                'email_error' => mb_substr($e->getMessage(), 0, 500),
            ])->saveQuietly();

            return redirect()
                ->route('congnghe.proposal')
                ->with('error', 'Đề xuất đã được lưu ('.$proposal->reference_code.') nhưng email chưa gửi được. Phòng Công nghệ vẫn xem trong hệ thống hoặc liên hệ '.$recipient);
        }

        return redirect()
            ->route('congnghe.proposal')
            ->with('success', 'Đã ghi nhận đề xuất '.$proposal->reference_code.' và gửi email tới Phòng Công Nghệ.');
    }
}
