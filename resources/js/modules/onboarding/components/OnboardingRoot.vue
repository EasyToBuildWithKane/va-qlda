<script setup>
import { onMounted, reactive, ref } from 'vue';
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
const { start } = useTour();
const { hint } = useSmartContext(ob.context, ob.role);

const showWelcome = ref(false);
const showComplete = ref(false);
const hintDismissed = ref(false);

const hud = reactive({ show: false, title: '', current: 0, total: 0, est: 0 });

onMounted(() => {
    if (!ob.seenWelcome.value) {
        showWelcome.value = true;
    }
});

function startTour(tourKey) {
    showWelcome.value = false;
    // Defer so the welcome modal unmounts before driver.js measures anchors.
    setTimeout(() => {
        hud.title = tourTitle(tourKey);
        hud.est = tourEstMinutes(tourKey);
        start(tourKey, {
            onStep: (current, total) => {
                hud.show = true;
                hud.current = current;
                hud.total = total;
                ob.recordStep(tourKey, current, total);
            },
            onComplete: () => {
                hud.show = false;
                ob.complete(tourKey);
                showComplete.value = true;
            },
            onSkip: () => {
                hud.show = false;
                ob.skip(tourKey);
            },
        });
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
    :hint="!showWelcome && !hud.show && !hintDismissed ? hint : null"
    @dismiss="hintDismissed = true"
  />

  <HelpWidget
    :role="ob.role.value"
    @replay="startTour"
  />
</template>
