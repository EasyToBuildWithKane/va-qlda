<script setup>
import { computed, toRef } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import { useCountUp } from '@/shared/composables/useCountUp.js';

const props = defineProps({
    label: { type: String, required: true },
    value: { type: Number, default: null },
    suffix: { type: String, default: '' },
    sub: { type: String, default: '' },
    icon: { type: String, default: 'overview' },
    tone: { type: String, default: 'brand' },
    progress: { type: Number, default: null },
    index: { type: Number, default: 0 },
});

const hasValue = computed(() => props.value != null);
const { display } = useCountUp(toRef(props, 'value'));

const toneClass = {
    brand: 'text-brand bg-brand/10 ring-brand/20',
    emerald: 'text-emerald-700 bg-emerald-50 ring-emerald-200/80',
    amber: 'text-amber-700 bg-amber-50 ring-amber-200/80',
    sky: 'text-sky-700 bg-sky-50 ring-sky-200/80',
    violet: 'text-violet-700 bg-violet-50 ring-violet-200/80',
};

const barClass = {
    brand: 'bg-brand',
    emerald: 'bg-emerald-500',
    amber: 'bg-amber-500',
    sky: 'bg-sky-500',
    violet: 'bg-violet-500',
};
</script>

<template>
  <div
    class="org-stat"
    :style="{ '--reveal-delay': `${index * 70}ms` }"
  >
    <div
      class="org-stat__glow"
      aria-hidden="true"
    />
    <div class="relative z-[1] flex h-full flex-col">
      <div class="flex items-start justify-between gap-2">
        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">
          {{ label }}
        </p>
        <span
          class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg ring-1"
          :class="toneClass[tone] ?? toneClass.brand"
        >
          <AppIcon
            :name="icon"
            :size="17"
          />
        </span>
      </div>

      <p
        class="mt-1.5 font-display text-2xl font-bold tabular-nums leading-none tracking-tight"
        :class="tone === 'brand' ? 'text-brand' : 'text-slate-900'"
      >
        <template v-if="hasValue">
          {{ display }}<span
            v-if="suffix"
            class="text-base font-semibold text-slate-400"
          >{{ suffix }}</span>
        </template>
        <span
          v-else
          class="text-slate-300"
        >—</span>
      </p>

      <div class="mt-auto pt-2">
        <div
          v-if="progress != null && hasValue"
          class="mb-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100"
        >
          <div
            class="h-full rounded-full transition-[width] duration-700 ease-out"
            :class="barClass[tone] ?? barClass.brand"
            :style="{ width: `${Math.min(100, Math.max(0, progress))}%` }"
          />
        </div>
        <p
          v-if="sub"
          class="text-[11px] leading-snug text-slate-500"
        >
          {{ sub }}
        </p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.org-stat {
    position: relative;
    overflow: hidden;
    min-height: 7rem;
    padding: 0.85rem 0.9rem;
    border-radius: 16px;
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(148, 163, 184, 0.28);
    box-shadow: 0 10px 26px -20px rgba(15, 23, 42, 0.5);
    transition: transform 0.25s ease, box-shadow 0.3s ease, border-color 0.3s ease;
    animation: org-stat-in 0.5s cubic-bezier(0.22, 1, 0.36, 1) both;
    animation-delay: var(--reveal-delay, 0ms);
}

.org-stat:hover {
    transform: translateY(-3px);
    border-color: rgba(154, 0, 54, 0.35);
    box-shadow: 0 18px 36px -22px rgba(154, 0, 54, 0.45);
}

.org-stat__glow {
    position: absolute;
    top: -40%;
    right: -20%;
    width: 60%;
    height: 120%;
    background: radial-gradient(circle, rgba(154, 0, 54, 0.1), transparent 70%);
    opacity: 0;
    transition: opacity 0.4s ease;
}

.org-stat:hover .org-stat__glow {
    opacity: 1;
}

@keyframes org-stat-in {
    from {
        opacity: 0;
        transform: translateY(12px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (prefers-reduced-motion: reduce) {
    .org-stat {
        animation: none;
    }
}
</style>
