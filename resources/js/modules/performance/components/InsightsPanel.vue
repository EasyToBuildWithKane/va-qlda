<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import { insightTone } from '../composables/useChartTheme.js';

defineProps({
    insights: { type: Array, default: () => [] },
});
</script>

<template>
  <section class="card flex flex-col p-5">
    <div class="mb-3 flex items-center gap-2">
      <span class="grid h-9 w-9 place-items-center rounded-xl bg-gradient-to-br from-brand to-rose-500 text-white">
        <AppIcon
          name="sparkles"
          :size="18"
        />
      </span>
      <div>
        <h3 class="font-display text-sm font-semibold text-slate-800">
          AI Insights
        </h3>
        <p class="text-[11px] text-slate-400">
          Phân tích tự động từ dữ liệu thật
        </p>
      </div>
    </div>

    <ul
      v-if="insights.length"
      class="flex flex-1 flex-col gap-2.5"
    >
      <li
        v-for="(it, i) in insights"
        :key="i"
        class="flex gap-3 rounded-xl border p-3"
        :class="(insightTone[it.level] || insightTone.info).ring"
      >
        <span
          class="mt-0.5 shrink-0"
          :class="(insightTone[it.level] || insightTone.info).icon"
        >
          <AppIcon
            :name="it.icon || 'info'"
            :size="17"
          />
        </span>
        <div class="min-w-0">
          <p class="text-[13px] font-semibold text-slate-800">
            {{ it.title }}
          </p>
          <p class="mt-0.5 text-[12px] leading-relaxed text-slate-600">
            {{ it.body }}
          </p>
        </div>
      </li>
    </ul>

    <p
      v-else
      class="py-6 text-center text-sm text-slate-400"
    >
      Chưa có nhận xét nào.
    </p>
  </section>
</template>
