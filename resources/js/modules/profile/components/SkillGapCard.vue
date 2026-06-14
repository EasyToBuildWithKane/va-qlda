<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';

defineProps({
    gap: { type: Object, default: null },
});
</script>

<template>
  <section
    v-if="gap"
    class="rounded-2xl border border-slate-200/70 bg-white shadow-sm"
  >
    <header class="flex items-center justify-between border-b border-slate-100 px-5 py-3.5">
      <div class="flex items-center gap-2.5">
        <div class="grid h-8 w-8 place-items-center rounded-lg bg-brand/10 text-brand">
          <AppIcon
            name="target"
            :size="16"
          />
        </div>
        <div>
          <h2 class="text-sm font-semibold text-slate-800">
            Khoảng cách kỹ năng
          </h2>
          <p class="text-[12px] text-slate-400">
            Mục tiêu: <span class="font-medium text-slate-600">{{ gap.target.name }}</span>
          </p>
        </div>
      </div>
      <Badge
        :label="gap.met ? 'Đã đạt yêu cầu' : 'Còn thiếu'"
        :color="gap.met ? 'emerald' : 'amber'"
      />
    </header>

    <div class="space-y-3.5 p-5">
      <div
        v-for="item in gap.items"
        :key="item.name"
      >
        <div class="mb-1 flex items-center justify-between text-[12.5px]">
          <span class="font-medium text-slate-700">{{ item.name }}</span>
          <span
            class="tabular-nums"
            :class="item.gap > 0 ? 'text-amber-600' : 'text-emerald-600'"
          >
            {{ item.current }}/{{ item.required }}
            <span class="text-slate-300">·</span>
            <template v-if="item.gap > 0">thiếu {{ item.gap }}</template>
            <template v-else>đạt</template>
          </span>
        </div>
        <!-- Required track with current fill -->
        <div class="relative h-2 rounded-full bg-slate-100">
          <div
            class="absolute inset-y-0 left-0 rounded-full bg-slate-200"
            :style="{ width: item.required_pct + '%' }"
          />
          <div
            class="absolute inset-y-0 left-0 rounded-full transition-all duration-500"
            :class="item.gap > 0 ? 'bg-amber-400' : 'bg-emerald-500'"
            :style="{ width: item.current_pct + '%' }"
          />
        </div>
      </div>

      <div class="flex items-center gap-3 border-t border-slate-100 pt-3 text-[11px] text-slate-400">
        <span class="inline-flex items-center gap-1">
          <span class="h-2 w-2 rounded-full bg-slate-200" /> Yêu cầu
        </span>
        <span class="inline-flex items-center gap-1">
          <span class="h-2 w-2 rounded-full bg-emerald-500" /> Hiện tại
        </span>
      </div>
    </div>
  </section>
</template>
