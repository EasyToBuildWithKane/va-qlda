<script setup>
import { computed } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    tip: { type: Object, required: true },
});

const tipSubClass = computed(() => ({
    amber: 'text-amber-300',
    sky: 'text-sky-300',
    accent: 'text-accent',
}[props.tip.tone] || 'text-slate-300'));

const style = computed(() => ({
    top: `${props.tip.top}px`,
    left: `${props.tip.left}px`,
}));
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-100 ease-out"
      enter-from-class="opacity-0 scale-95"
      enter-to-class="opacity-100 scale-100"
      leave-active-class="transition duration-75 ease-in"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-95"
    >
      <div
        v-if="show && tip.show"
        :style="style"
        class="pointer-events-none fixed z-[65] -translate-y-1/2"
        role="tooltip"
      >
        <div class="relative rounded-lg bg-slate-900/95 px-2.5 py-1.5 shadow-elevation-2 ring-1 ring-white/10">
          <span class="absolute -left-1 top-1/2 h-2 w-2 -translate-y-1/2 rotate-45 bg-slate-900/95 ring-1 ring-white/10" />
          <span class="block whitespace-nowrap text-[12.5px] font-medium leading-tight text-white">{{ tip.label }}</span>
          <span
            v-if="tip.sub"
            class="block whitespace-nowrap text-[10.5px] leading-tight"
            :class="tipSubClass"
          >{{ tip.sub }}</span>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
