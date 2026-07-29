<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import HelpWidget from '@/modules/onboarding/components/HelpWidget.vue';
import TourProgressHud from '@/modules/onboarding/components/TourProgressHud.vue';
import TourCompleteModal from '@/modules/onboarding/components/TourCompleteModal.vue';
import SmartContextHint from '@/modules/onboarding/components/SmartContextHint.vue';
import { useOnboarding } from '@/modules/onboarding/composables/useOnboarding';
import { useTour } from '@/modules/onboarding/composables/useTour';
import { useSmartContext } from '@/modules/onboarding/composables/useSmartContext';
import { tourTitle, tourEstMinutes } from '@/modules/onboarding/tours';

const ob = useOnboarding();
const { start, destroy } = useTour();
const { hint } = useSmartContext(ob.context, ob.role);

const showComplete = ref(false);
const hintDismissed = ref(false);
const tourRunning = ref(false);

const hud = reactive({ show: false, title: '', current: 0, total: 0, est: 0 });

onMounted(() => {
    document.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
    document.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = '';
    destroy();
});

// Lock background scroll while the complete modal is open.
watch(showComplete, (open) => {
    document.body.style.overflow = open ? 'hidden' : '';
});

function onKeydown(e) {
    if (e.key !== 'Escape') return;
    if (showComplete.value) showComplete.value = false;
}

function startTour(tourKey) {
    if (tourRunning.value) return; // guard double-start
    tourRunning.value = true;
    // Defer so any overlay unmounts before driver.js measures anchors.
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

const showHint = computed(() => !showComplete.value && !tourRunning.value && !hintDismissed.value);
</script>

<template>
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
    :hint="showHint ? hint : null"
    @dismiss="hintDismissed = true"
  />

  <HelpWidget
    :role="ob.role.value"
    @replay="startTour"
  />
</template>
