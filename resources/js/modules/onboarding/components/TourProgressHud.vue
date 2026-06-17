<script setup>
import { computed } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: '' },
    current: { type: Number, default: 0 },
    total: { type: Number, default: 0 },
    estMinutes: { type: Number, default: 0 },
});

const percent = computed(() => (props.total ? Math.round((props.current / props.total) * 100) : 0));

// Thời gian còn lại ≈ tỉ lệ bước chưa xong × tổng thời gian dự kiến.
const minutesLeft = computed(() => {
    if (!props.total || !props.estMinutes) return 0;
    const remain = (props.total - props.current) / props.total;
    return Math.max(1, Math.ceil(remain * props.estMinutes));
});
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0 translate-y-2"
      leave-active-class="transition duration-150 ease-in"
      leave-to-class="opacity-0 translate-y-2"
    >
      <div
        v-if="show"
        class="fixed left-1/2 top-4 z-[100000] w-[min(92vw,26rem)] -translate-x-1/2 rounded-xl border border-white/10 bg-slate-900/95 px-4 py-3 text-white shadow-2xl backdrop-blur"
        role="status"
      >
        <div class="flex items-center justify-between gap-3">
          <p class="truncate text-[13px] font-semibold">
            {{ title }}
          </p>
          <p class="shrink-0 text-[12px] font-medium text-white/70 tabular-nums">
            {{ current }} / {{ total }} bước
          </p>
        </div>
        <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-white/15">
          <div
            class="h-full rounded-full bg-accent transition-[width] duration-300 ease-out"
            :style="{ width: `${percent}%` }"
          />
        </div>
        <div class="mt-1.5 flex items-center justify-between text-[11px] text-white/60 tabular-nums">
          <span>{{ percent }}% hoàn thành</span>
          <span v-if="minutesLeft">≈ còn {{ minutesLeft }} phút</span>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
