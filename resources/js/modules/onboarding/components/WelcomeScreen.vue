<script setup>
import WelcomePanel from '@/modules/onboarding/components/WelcomePanel.vue';
import { useOnboardingWelcome } from '@/modules/onboarding/composables/useOnboardingWelcome';

const { welcome, visible, isPreview, markSeen, closePreview } = useOnboardingWelcome();

function onDismiss() {
    if (isPreview.value) {
        closePreview();
        return;
    }
    markSeen();
}
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-300 ease-out"
      enter-from-class="opacity-0"
      leave-active-class="transition duration-200 ease-in"
      leave-to-class="opacity-0"
    >
      <div
        v-if="visible && welcome"
        class="fixed inset-0 z-[90] flex items-center justify-center overflow-hidden bg-slate-950/55 p-4 backdrop-blur-md"
        role="dialog"
        aria-modal="true"
        :aria-label="isPreview ? 'Xem trước màn hình chào mừng' : 'Màn hình chào mừng'"
        @keydown.esc="onDismiss"
      >
        <div
          class="pointer-events-none absolute inset-0 overflow-hidden"
          aria-hidden="true"
        >
          <div class="absolute -left-24 top-10 h-72 w-72 rounded-full bg-brand/30 blur-3xl" />
          <div class="absolute -right-16 bottom-0 h-80 w-80 rounded-full bg-rose-500/20 blur-3xl" />
        </div>

        <Transition
          appear
          enter-active-class="transition duration-300 ease-out"
          enter-from-class="opacity-0 scale-95 translate-y-2"
          leave-active-class="transition duration-150 ease-in"
          leave-to-class="opacity-0 scale-95"
        >
          <div class="relative w-full max-w-4xl">
            <WelcomePanel
              :welcome="welcome"
              @dismiss="onDismiss"
              @skip="onDismiss"
            />
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
