import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

/**
 * Onboarding state + persistence. Reads the shared `onboarding` Inertia prop
 * (no extra fetch) and posts progress back with a partial reload so the active
 * tour overlay (mounted on <body> by driver.js) is never disrupted.
 */
export function useOnboarding() {
    const page = usePage();

    const data = computed(() => page.props.onboarding || null);
    const role = computed(() => data.value?.role || 'member');
    const seenWelcome = computed(() => data.value?.seen_welcome ?? true);
    const tours = computed(() => data.value?.tours || {});
    const context = computed(() => data.value?.context || {});
    const completedTours = computed(() => data.value?.completed_tours || 0);
    const totalTours = computed(() => data.value?.total_tours || 0);

    function post(routeName, payload = {}) {
        router.post(route(routeName), payload, {
            preserveState: true,
            preserveScroll: true,
            only: ['onboarding'],
        });
    }

    const recordStep = (tourKey, currentStep, totalSteps) =>
        post('onboarding.progress', { tour_key: tourKey, current_step: currentStep, total_steps: totalSteps });

    const complete = (tourKey) => post('onboarding.complete', { tour_key: tourKey });
    const skip = (tourKey) => post('onboarding.skip', { tour_key: tourKey });
    const reset = (tourKey) => post('onboarding.reset', { tour_key: tourKey });
    const dismissWelcome = () => post('onboarding.dismiss-welcome');

    const statusOf = (tourKey) => tours.value[tourKey]?.status || 'pending';

    return {
        data,
        role,
        seenWelcome,
        tours,
        context,
        completedTours,
        totalTours,
        statusOf,
        recordStep,
        complete,
        skip,
        reset,
        dismissWelcome,
    };
}
