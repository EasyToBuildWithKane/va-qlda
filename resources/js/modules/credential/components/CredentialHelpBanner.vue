<script setup>
import AppIcon from '@/Components/AppIcon.vue';

defineProps({
    title: { type: String, required: true },
    intro: { type: String, default: '' },
    steps: { type: Array, default: () => [] },
    note: {
        type: String,
        default: 'Trường có dấu * là bắt buộc. Bấm biểu tượng ⓘ cạnh nhãn để xem gợi ý chi tiết.',
    },
});
</script>

<template>
  <section
    class="mb-5 rounded-card border border-slate-200/80 bg-gradient-to-b from-brand/[0.06] to-white p-4 shadow-sm sm:p-5"
    aria-label="Hướng dẫn sử dụng"
  >
    <div class="flex gap-3">
      <div
        class="hidden h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-brand/10 text-brand sm:flex"
        aria-hidden="true"
      >
        <AppIcon
          name="info"
          :size="20"
        />
      </div>
      <div class="min-w-0 flex-1">
        <h2 class="font-display text-sm font-semibold text-slate-900">
          {{ title }}
        </h2>
        <p
          v-if="intro"
          class="mt-1 text-sm leading-relaxed text-slate-600"
        >
          {{ intro }}
        </p>
        <ol
          v-if="steps.length"
          class="mt-3 space-y-1.5 text-sm text-slate-600"
        >
          <li
            v-for="(step, i) in steps"
            :key="i"
            class="flex gap-2"
          >
            <span class="font-display tabular-nums text-brand">{{ i + 1 }}.</span>
            <span>{{ step }}</span>
          </li>
        </ol>
        <p class="mt-3 text-[11px] leading-relaxed text-slate-500">
          {{ note }}
        </p>
      </div>
    </div>
  </section>
</template>
