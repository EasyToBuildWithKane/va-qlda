<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\OnboardingTourRequest;
use App\Services\OnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Interactive tour guide — progress tracking. Tour content is client-side;
 * these endpoints only persist state per account. Thin: delegate to service.
 *
 * Mutations are called from the browser via axios (fire-and-forget) so they
 * return 204 to avoid an Inertia re-render mid-tour; the client refreshes the
 * shared `onboarding` prop itself after terminal actions.
 */
class OnboardingController extends Controller
{
    public function __construct(private readonly OnboardingService $service) {}

    /** JSON snapshot for the Help widget / progress HUD. */
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->service->payload($request->user()));
    }

    public function progress(OnboardingTourRequest $request): Response
    {
        $data = $request->validated();
        $this->service->recordStep(
            $request->user(),
            $data['tour_key'],
            (int) ($data['current_step'] ?? 0),
            (int) ($data['total_steps'] ?? 0),
        );

        return $this->ok($request);
    }

    public function complete(OnboardingTourRequest $request): Response
    {
        $this->service->complete($request->user(), $request->validated()['tour_key']);

        return $this->ok($request, 'Đã hoàn thành hướng dẫn. Chúc bạn làm việc hiệu quả!');
    }

    public function skip(OnboardingTourRequest $request): Response
    {
        $this->service->skip($request->user(), $request->validated()['tour_key']);

        return $this->ok($request);
    }

    public function reset(OnboardingTourRequest $request): Response
    {
        $this->service->reset($request->user(), $request->validated()['tour_key']);

        return $this->ok($request);
    }

    /** Dismiss the first-login Welcome without starting a tour. */
    public function dismissWelcome(Request $request): Response
    {
        $this->service->markWelcomeSeen($request->user());

        return $this->ok($request);
    }

    /** 204 for ajax callers; graceful redirect fallback otherwise. */
    private function ok(Request $request, ?string $flash = null): Response
    {
        if ($request->expectsJson()) {
            return response()->noContent();
        }

        return $flash ? back()->with('success', $flash) : back();
    }
}
