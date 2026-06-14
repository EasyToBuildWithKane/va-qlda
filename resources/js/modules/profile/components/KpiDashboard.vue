<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import EmptyState from '@/shared/ui/EmptyState.vue';

defineProps({
    groups: { type: Array, default: () => [] },
});

function barColor(pct) {
    if (pct == null) return 'bg-slate-300';
    if (pct >= 100) return 'bg-emerald-500';
    if (pct >= 80) return 'bg-sky-500';
    if (pct >= 50) return 'bg-amber-500';
    return 'bg-rose-400';
}
</script>

<template>
  <section class="rounded-2xl border border-slate-200/70 bg-white shadow-sm">
    <header class="flex items-center gap-2.5 border-b border-slate-100 px-5 py-3.5">
      <div class="grid h-8 w-8 place-items-center rounded-lg bg-brand/10 text-brand">
        <AppIcon
          name="performance"
          :size="16"
        />
      </div>
      <h2 class="text-sm font-semibold text-slate-800">
        KPI & Hiệu suất
      </h2>
    </header>

    <div class="p-5">
      <EmptyState
        v-if="!groups.length"
        icon="performance"
        title="Chưa có KPI"
        description="KPI theo tháng/quý/năm sẽ hiển thị tại đây."
      />

      <div
        v-else
        class="grid grid-cols-1 gap-4 lg:grid-cols-3"
      >
        <div
          v-for="g in groups"
          :key="g.period_type.value"
          class="rounded-xl border border-slate-100 p-4"
        >
          <div class="mb-3 flex items-center justify-between">
            <div class="flex items-center gap-2">
              <Badge
                :label="g.period_type.label"
                :color="g.period_type.color"
              />
              <span class="text-[12px] text-slate-400">{{ g.period }}</span>
            </div>
            <span
              v-if="g.score !== null"
              class="text-[15px] font-bold tabular-nums text-slate-800"
            >{{ g.score }}%</span>
          </div>

          <div class="space-y-3">
            <div
              v-for="(k, i) in g.items"
              :key="i"
            >
              <div class="mb-1 flex items-baseline justify-between gap-2 text-[12px]">
                <span class="truncate text-slate-600">{{ k.name }}</span>
                <span class="shrink-0 tabular-nums text-slate-500">
                  {{ k.actual }}<span
                    v-if="k.target"
                    class="text-slate-300"
                  >/{{ k.target }}</span>
                  <span class="text-slate-400">{{ k.unit }}</span>
                </span>
              </div>
              <div class="h-1.5 overflow-hidden rounded-full bg-slate-100">
                <div
                  class="h-full rounded-full transition-all duration-500"
                  :class="barColor(k.attainment_pct)"
                  :style="{ width: Math.min(k.attainment_pct ?? 0, 100) + '%' }"
                />
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>
