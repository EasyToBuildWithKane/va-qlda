import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Full-screen Welcome onboarding screen (first login) — a separate one-time
 * concept from the step-by-step TOURS in useOnboarding.js. Reads the
 * `onboarding.welcome` shared Inertia prop (see OnboardingService::welcomePayload).
 *
 * Shared module state so /settings/onboarding can open a preview that the
 * single <WelcomeScreen /> in AppLayout actually shows.
 */
const previewOverride = ref(null);
const visible = ref(false);
let previewCloseTimer = null;

export function useOnboardingWelcome() {
    const page = usePage();

    const liveWelcome = computed(() => page.props.onboarding?.welcome || null);
    const welcome = computed(() => previewOverride.value || liveWelcome.value);
    const isPreview = computed(() => previewOverride.value !== null);

    const shouldShowLive = computed(() =>
        Boolean(liveWelcome.value?.enabled && !liveWelcome.value?.seen),
    );

    watch(
        [shouldShowLive, previewOverride],
        ([showLive, preview]) => {
            if (preview || showLive) {
                visible.value = true;
            } else if (!preview) {
                visible.value = false;
            }
        },
        { immediate: true },
    );

    /** Close locally right away (smooth UX) then persist fire-and-forget. */
    function markSeen() {
        visible.value = false;
        window.axios.post(route('onboarding.welcome.seen')).catch(() => {});
    }

    /** Admin preview — does not call the seen endpoint. */
    function openPreview(data) {
        if (!data) return;
        if (previewCloseTimer) {
            window.clearTimeout(previewCloseTimer);
            previewCloseTimer = null;
        }
        previewOverride.value = { ...data, enabled: true, seen: false };
        visible.value = true;
    }

    function closePreview() {
        visible.value = false;
        previewCloseTimer = window.setTimeout(() => {
            previewOverride.value = null;
            previewCloseTimer = null;
        }, 220);
    }

    return {
        welcome,
        visible,
        isPreview,
        markSeen,
        openPreview,
        closePreview,
    };
}
