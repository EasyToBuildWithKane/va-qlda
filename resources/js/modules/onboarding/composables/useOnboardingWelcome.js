import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Full-screen Welcome onboarding screen (first login) — a separate one-time
 * concept from the step-by-step TOURS in useOnboarding.js. Reads the
 * `onboarding.welcome` shared Inertia prop (see OnboardingService::welcomePayload).
 *
 * `visible` is a local ref (not a direct mirror of the prop) so the closing
 * <Transition> can play out fully before the component actually unmounts,
 * instead of snapping away the instant the server round-trip resolves.
 */
export function useOnboardingWelcome() {
    const page = usePage();

    const welcome = computed(() => page.props.onboarding?.welcome || null);
    const shouldShow = computed(() => Boolean(welcome.value?.enabled && !welcome.value?.seen));

    const visible = ref(false);

    watch(shouldShow, (show) => {
        if (show) visible.value = true;
    }, { immediate: true });

    /** Close locally right away (smooth UX) then persist fire-and-forget. */
    function markSeen() {
        visible.value = false;
        window.axios.post(route('onboarding.welcome.seen')).catch(() => {});
    }

    return {
        welcome,
        visible,
        markSeen,
    };
}
