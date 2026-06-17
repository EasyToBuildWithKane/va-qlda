<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import WelcomeModal from '@/modules/onboarding/components/WelcomeModal.vue';
import HelpWidget from '@/modules/onboarding/components/HelpWidget.vue';
import TourProgressHud from '@/modules/onboarding/components/TourProgressHud.vue';
import TourCompleteModal from '@/modules/onboarding/components/TourCompleteModal.vue';
import SmartContextHint from '@/modules/onboarding/components/SmartContextHint.vue';
import { useOnboarding } from '@/modules/onboarding/composables/useOnboarding';
import { useTour } from '@/modules/onboarding/composables/useTour';
import { useSmartContext } from '@/modules/onboarding/composables/useSmartContext';
import { tourTitle, tourEstMinutes } from '@/modules/onboarding/tours';

const page = usePage();
const ob = useOnboarding();
const { start, destroy } = useTour();
const { hint } = useSmartContext(ob.context, ob.role);

const showWelcome = ref(false);
const showComplete = ref(false);
const hintDismissed = ref(false);
const tourRunning = ref(false);

const hud = reactive({ show: false, title: '', current: 0, total: 0, est: 0 });

onMounted(() => {
    if (!ob.seenWelcome.value) {
        showWelcome.value = true;
    }
    document.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
    document.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = '';
    destroy();
});

// Lock background scroll while a welcome/complete modal is open.
const modalOpen = computed(() => showWelcome.value || showComplete.value);
watch(modalOpen, (open) => {
    document.body.style.overflow = open ? 'hidden' : '';
});

function onKeydown(e) {
    if (e.key !== 'Escape') return;
    if (showComplete.value) showComplete.value = false;
    else if (showWelcome.value) dismissWelcome();
}

function startTour(tourKey) {
    if (tourRunning.value) return; // guard double-start
    showWelcome.value = false;
    tourRunning.value = true;
    // Defer so the welcome modal unmounts before driver.js measures anchors.
    setTimeout(() => {
        hud.title = tourTitle(tourKey);
        hud.est = tourEstMinutes(tourKey);
        const instance = start(tourKey, {
            onStep: (current, total) => {
                hud.show = true;
                hud.current = current;
                hud.total = total;
                ob.recordStep(tourKey, current, total);
            },
            onComplete: () => {
                hud.show = false;
                tourRunning.value = false;
                ob.complete(tourKey);
                showComplete.value = true;
            },
            onSkip: () => {
                hud.show = false;
                tourRunning.value = false;
                ob.skip(tourKey);
            },
        });
        // No anchors present on this page → nothing to show; release the guard.
        if (!instance) tourRunning.value = false;
    }, 220);
}

function dismissWelcome() {
    showWelcome.value = false;
    ob.dismissWelcome();
}
</script>

<template>
  <WelcomeModal
    :show="showWelcome"
    :role="ob.role.value"
    :app-name="page.props.app?.name || 'hệ thống'"
    @start="startTour"
    @dismiss="dismissWelcome"
  />

  <TourProgressHud
    :show="hud.show"
    :title="hud.title"
    :current="hud.current"
    :total="hud.total"
    :est-minutes="hud.est"
  />

  <TourCompleteModal
    :show="showComplete"
    :completed-tours="ob.completedTours.value"
    :total-tours="ob.totalTours.value"
    @close="showComplete = false"
  />

  <SmartContextHint
    :hint="!modalOpen && !tourRunning && !hintDismissed ? hint : null"
    @dismiss="hintDismissed = true"
  />

  <HelpWidget
    :role="ob.role.value"
    @replay="startTour"
  />
</template>
