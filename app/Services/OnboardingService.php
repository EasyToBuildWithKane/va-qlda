<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\OnboardingProgress;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\SystemAccount;
use App\Models\Task;

/**
 * Onboarding state for the interactive tour guide.
 *
 * Tour *content* lives client-side (resources/js/modules/onboarding/tours);
 * this service only owns persistence (progress per account) and the cheap
 * "smart context" flags that decide which contextual hint to surface.
 */
class OnboardingService
{
    /**
     * Whitelist of tour keys the client may report progress against. Keep in
     * sync with resources/js/modules/onboarding/tours/index.js.
     */
    public const TOURS = [
        'system_overview',
        'role_admin',
        'role_pm',
        'role_lead',
        'role_member',
    ];

    /**
     * Build the shared Inertia payload: per-tour progress + smart-context flags.
     *
     * @return array<string, mixed>
     */
    public function payload(SystemAccount $account): array
    {
        $progress = OnboardingProgress::query()
            ->where('system_account_id', $account->id)
            ->get()
            ->keyBy('tour_key');

        $tours = [];
        foreach (self::TOURS as $key) {
            $row = $progress->get($key);
            $tours[$key] = [
                'status' => $row->status ?? 'pending',
                'current_step' => $row->current_step ?? 0,
                'total_steps' => $row->total_steps ?? 0,
            ];
        }

        $completed = $progress->where('status', 'completed')->count();

        return [
            'seen_welcome' => $account->onboarding_seen_at !== null,
            'role' => $account->role->value,
            'tours' => $tours,
            'completed_tours' => $completed,
            'total_tours' => count(self::TOURS),
            'context' => $this->context($account),
        ];
    }

    /**
     * Cheap existence checks → which "next step" hint to nudge. Uses exists()
     * so this stays light enough to run on every Inertia request.
     *
     * @return array<string, bool>
     */
    public function context(SystemAccount $account): array
    {
        return [
            'hasProject' => Project::query()->exists(),
            'hasSprint' => Sprint::query()->exists(),
            'hasTask' => Task::query()->exists(),
            'hasTeamMembers' => Employee::query()->count() > 1,
        ];
    }

    /**
     * Upsert progress for a tour. Marking a step also moves status forward.
     */
    public function recordStep(SystemAccount $account, string $tourKey, int $currentStep, int $totalSteps): OnboardingProgress
    {
        return OnboardingProgress::updateOrCreate(
            ['system_account_id' => $account->id, 'tour_key' => $tourKey],
            [
                'current_step' => $currentStep,
                'total_steps' => $totalSteps,
                'status' => 'in_progress',
            ],
        );
    }

    public function complete(SystemAccount $account, string $tourKey): void
    {
        OnboardingProgress::updateOrCreate(
            ['system_account_id' => $account->id, 'tour_key' => $tourKey],
            ['status' => 'completed', 'completed_at' => now()],
        );

        $this->markWelcomeSeen($account);
    }

    public function skip(SystemAccount $account, string $tourKey): void
    {
        OnboardingProgress::updateOrCreate(
            ['system_account_id' => $account->id, 'tour_key' => $tourKey],
            ['status' => 'skipped'],
        );

        $this->markWelcomeSeen($account);
    }

    /** "Xem lại từ đầu" — clear a tour so the user can replay it cleanly. */
    public function reset(SystemAccount $account, string $tourKey): void
    {
        OnboardingProgress::query()
            ->where('system_account_id', $account->id)
            ->where('tour_key', $tourKey)
            ->delete();
    }

    public function markWelcomeSeen(SystemAccount $account): void
    {
        if ($account->onboarding_seen_at === null) {
            $account->forceFill(['onboarding_seen_at' => now()])->save();
        }
    }
}
