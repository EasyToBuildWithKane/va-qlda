<script setup>
import { ref } from 'vue';

defineProps({
    text: { type: String, default: '' },
    // Tooltip width + horizontal anchor.
    align: { type: String, default: 'left' }, // 'left' | 'right'
});

const open = ref(false);
</script>

<template>
  <span
    class="relative inline-flex align-middle"
    @mouseenter="open = true"
    @mouseleave="open = false"
  >
    <button
      type="button"
      class="grid h-4 w-4 place-items-center rounded-full border border-slate-300 text-[10px] font-bold text-slate-400 transition hover:border-brand hover:text-brand focus:outline-none focus:ring-2 focus:ring-brand/30"
      aria-label="Gợi ý"
      @focus="open = true"
      @blur="open = false"
      @click.prevent.stop="open = !open"
    >
      i
    </button>

    <Transition
      enter-active-class="transition duration-100 ease-out"
      enter-from-class="opacity-0 translate-y-1"
      leave-active-class="transition duration-75 ease-in"
      leave-to-class="opacity-0"
    >
      <span
        v-show="open"
        role="tooltip"
        class="absolute top-full z-40 mt-2 w-64 rounded-card border border-slate-700 bg-slate-800 px-3 py-2 text-xs font-normal leading-relaxed text-slate-100 shadow-elevation-3"
        :class="align === 'right' ? 'right-0' : 'left-0'"
      >
        <slot>{{ text }}</slot>
      </span>
    </Transition>
  </span>
</template>
