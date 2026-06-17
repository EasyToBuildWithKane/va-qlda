<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\OnboardingTourRequest;
use App\Services\OnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Interactive tour guide — progress tracking. Tour content is client-side;
 * these endpoints only persist state per account. Thin: delegate to service.
 */
class OnboardingController extends Controller
{
    public function __construct(private readonly OnboardingService $service) {}

    /** JSON snapshot for the Help widget / progress HUD. */
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->service->payload($request->user()));
    }

    public function progress(OnboardingTourRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->service->recordStep(
            $request->user(),
            $data['tour_key'],
            (int) ($data['current_step'] ?? 0),
            (int) ($data['total_steps'] ?? 0),
        );

        return back();
    }

    public function complete(OnboardingTourRequest $request): RedirectResponse
    {
        $this->service->complete($request->user(), $request->validated()['tour_key']);

        return back()->with('success', 'Đã hoàn thành hướng dẫn. Chúc bạn làm việc hiệu quả!');
    }

    public function skip(OnboardingTourRequest $request): RedirectResponse
    {
        $this->service->skip($request->user(), $request->validated()['tour_key']);

        return back();
    }

    public function reset(OnboardingTourRequest $request): RedirectResponse
    {
        $this->service->reset($request->user(), $request->validated()['tour_key']);

        return back();
    }

    /** Dismiss the first-login Welcome without starting a tour. */
    public function dismissWelcome(Request $request): RedirectResponse
    {
        $this->service->markWelcomeSeen($request->user());

        return back();
    }
}
