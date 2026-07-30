<script setup>
import { computed } from 'vue';
import { toastList, dismissToast } from '@/shared/composables/useToast';
import AppIcon from '@/Components/AppIcon.vue';

/** top-end (mặc định — parity VA-HRM) | top-center | bottom-end */
const props = defineProps({
    placement: {
        type: String,
        default: 'top-end',
        validator: (v) => ['bottom-end', 'top-end', 'top-center'].includes(v),
    },
});

const positionClass = computed(() => {
    if (props.placement === 'bottom-end') {
        return 'fixed bottom-5 right-5 z-[200] flex flex-col-reverse gap-2 items-end pointer-events-none';
    }
    if (props.placement === 'top-center') {
        return 'fixed inset-x-0 top-0 z-[200] flex flex-col items-center gap-2 p-3 pointer-events-none sm:p-4';
    }
    return 'fixed inset-x-0 top-0 z-[200] flex flex-col items-end gap-2 p-3 pointer-events-none sm:p-4';
});

const cfg = {
    success: {
        wrap: 'bg-emerald-600 text-white ring-emerald-400/40',
        icon: 'check',
        label: 'Thành công',
    },
    error: {
        wrap: 'bg-rose-600 text-white ring-rose-400/40',
        icon: 'alert',
        label: 'Lỗi',
    },
    info: {
        wrap: 'bg-sky-600 text-white ring-sky-400/40',
        icon: 'info',
        label: 'Thông tin',
    },
    warning: {
        wrap: 'bg-amber-600 text-white ring-amber-400/40',
        icon: 'flag',
        label: 'Cảnh báo',
    },
};
</script>

<template>
  <Teleport to="body">
    <div
      :class="positionClass"
      aria-live="polite"
      aria-relevant="additions"
    >
      <TransitionGroup
        enter-active-class="toast-slide-in"
        leave-active-class="transition duration-200 ease-in absolute"
        leave-to-class="opacity-0 translate-x-5 scale-95"
        move-class="transition duration-200"
      >
        <div
          v-for="t in toastList"
          :key="t.id"
          role="status"
          class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-2xl px-4 py-3.5 shadow-xl ring-1 backdrop-blur-sm"
          :class="cfg[t.type]?.wrap"
        >
          <span class="mt-0.5 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white/15">
            <AppIcon
              :name="cfg[t.type]?.icon"
              :size="20"
            />
          </span>
          <div class="min-w-0 flex-1 pt-1">
            <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-white/75">
              {{ cfg[t.type]?.label }}
            </p>
            <p class="mt-0.5 text-sm font-semibold leading-snug">
              {{ t.message }}
            </p>
          </div>
          <button
            type="button"
            class="shrink-0 rounded-lg p-1.5 text-white/80 transition hover:bg-white/15 hover:text-white"
            aria-label="Đóng thông báo"
            @click="dismissToast(t.id)"
          >
            <AppIcon
              name="close"
              :size="16"
            />
          </button>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>
