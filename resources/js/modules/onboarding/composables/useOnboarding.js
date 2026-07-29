import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

/**
 * Onboarding state + persistence.
 *
 * Reads the shared `onboarding` Inertia prop (no extra fetch). Mutations go out
 * via axios as fire-and-forget POSTs (server returns 204) so stepping through a
 * tour never triggers an Inertia re-render. After terminal actions we refresh
 * just the `onboarding` prop so progress counts stay accurate.
 */
export function useOnboarding() {
    const page = usePage();

    const data = computed(() => page.props.onboarding || null);
    const role = computed(() => data.value?.role || 'member');
    const tours = computed(() => data.value?.tours || {});
    const context = computed(() => data.value?.context || {});
    const completedTours = computed(() => data.value?.completed_tours || 0);
    const totalTours = computed(() => data.value?.total_tours || 0);

    function send(routeName, payload = {}) {
        // Fire-and-forget: a dropped progress ping must never surface an error.
        return window.axios.post(route(routeName), payload).catch(() => {});
    }

    function refresh() {
        router.reload({ only: ['onboarding'] });
    }

    const recordStep = (tourKey, currentStep, totalSteps) =>
        send('onboarding.progress', { tour_key: tourKey, current_step: currentStep, total_steps: totalSteps });

    const complete = (tourKey) => send('onboarding.complete', { tour_key: tourKey }).then(refresh);
    const skip = (tourKey) => send('onboarding.skip', { tour_key: tourKey }).then(refresh);
    const reset = (tourKey) => send('onboarding.reset', { tour_key: tourKey }).then(refresh);

    const statusOf = (tourKey) => tours.value[tourKey]?.status || 'pending';

    return {
        data,
        role,
        tours,
        context,
        completedTours,
        totalTours,
        statusOf,
        recordStep,
        complete,
        skip,
        reset,
    };
}
