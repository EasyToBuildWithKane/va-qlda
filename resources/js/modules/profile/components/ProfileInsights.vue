<script setup>
import AppIcon from '@/Components/AppIcon.vue';

defineProps({
    insights: { type: Array, default: () => [] },
});

const toneClass = {
    emerald: 'bg-emerald-50 text-emerald-600 ring-emerald-100',
    sky: 'bg-sky-50 text-sky-600 ring-sky-100',
    violet: 'bg-violet-50 text-violet-600 ring-violet-100',
    amber: 'bg-amber-50 text-amber-600 ring-amber-100',
    brand: 'bg-brand/5 text-brand ring-brand/10',
};
</script>

<template>
  <section class="rounded-2xl border border-slate-200/70 bg-white shadow-sm">
    <header class="flex items-center gap-2.5 border-b border-slate-100 px-5 py-3.5">
      <div class="grid h-8 w-8 place-items-center rounded-lg bg-brand/10 text-brand">
        <AppIcon
          name="sparkles"
          :size="16"
        />
      </div>
      <div>
        <h2 class="text-sm font-semibold text-slate-800">
          Gợi ý phát triển
        </h2>
        <p class="text-[12px] text-slate-400">
          Phân tích từ dữ liệu kỹ năng của bạn
        </p>
      </div>
    </header>

    <div class="p-5">
      <ul
        v-if="insights.length"
        class="space-y-3"
      >
        <li
          v-for="(it, i) in insights"
          :key="i"
          class="flex items-start gap-3"
        >
          <span
            class="mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-lg ring-1 ring-inset"
            :class="toneClass[it.tone] || toneClass.brand"
          >
            <AppIcon
              :name="it.icon"
              :size="14"
            />
          </span>
          <p class="text-[13px] leading-relaxed text-slate-600">
            {{ it.text }}
          </p>
        </li>
      </ul>
      <p
        v-else
        class="text-[13px] text-slate-400"
      >
        Chưa có gợi ý — thêm và chấm mức độ kỹ năng để nhận phân tích.
      </p>
    </div>
  </section>
</template>
