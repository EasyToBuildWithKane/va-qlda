<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    snapshot: {
        type: Object,
        default: () => ({ alerts: [], domains: [] }),
    },
});

const alerts = computed(() => props.snapshot?.alerts ?? []);
const domains = computed(() => props.snapshot?.domains ?? []);

const toneIcon = {
    brand: 'text-brand bg-brand/10 ring-brand/20',
    emerald: 'text-emerald-700 bg-emerald-50 ring-emerald-200/80',
    amber: 'text-amber-700 bg-amber-50 ring-amber-200/80',
    sky: 'text-sky-700 bg-sky-50 ring-sky-200/80',
    violet: 'text-violet-700 bg-violet-50 ring-violet-200/80',
    rose: 'text-rose-700 bg-rose-50 ring-rose-200/80',
    slate: 'text-slate-600 bg-slate-100 ring-slate-200/80',
};

const toneValue = {
    brand: 'text-brand',
    emerald: 'text-emerald-700',
    amber: 'text-amber-700',
    sky: 'text-sky-700',
    violet: 'text-violet-700',
    rose: 'text-rose-700',
    slate: 'text-slate-700',
};

const alertSurface = {
    brand: 'border-brand/25 bg-brand/5 hover:bg-brand/[0.08]',
    emerald: 'border-emerald-200/80 bg-emerald-50/60 hover:bg-emerald-50',
    amber: 'border-amber-200/80 bg-amber-50/70 hover:bg-amber-50',
    sky: 'border-sky-200/80 bg-sky-50/60 hover:bg-sky-50',
    violet: 'border-violet-200/80 bg-violet-50/60 hover:bg-violet-50',
    rose: 'border-rose-200/80 bg-rose-50/70 hover:bg-rose-50',
    slate: 'border-slate-200 bg-slate-50/80 hover:bg-slate-50',
};

const domainBorder = {
    brand: 'border-t-brand/70',
    emerald: 'border-t-emerald-500/70',
    amber: 'border-t-amber-500/70',
    sky: 'border-t-sky-500/70',
    violet: 'border-t-violet-500/70',
    rose: 'border-t-rose-500/70',
    slate: 'border-t-slate-400/70',
};
</script>

<template>
  <section
    class="mb-5 overflow-hidden rounded-card border border-slate-200/80 bg-white shadow-sm"
    aria-label="Ảnh chụp nhanh các domain hệ thống"
  >
    <header class="border-b border-slate-100 bg-gradient-to-r from-slate-50/90 to-white px-5 py-4">
      <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-brand/80">
        Tổng quan domain
      </p>
      <div class="mt-1 flex flex-wrap items-end justify-between gap-2">
        <h2 class="font-display text-base font-semibold text-slate-800">
          Ảnh chụp hệ sinh thái QLDA
        </h2>
        <p class="text-[11px] text-slate-400">
          Khác Dashboard công việc — tập trung module & luồng chéo
        </p>
      </div>
    </header>

    <div
      v-if="alerts.length"
      class="border-b border-slate-100 bg-slate-50/50 px-5 py-3"
    >
      <p class="mb-2 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500">
        Cần chú ý
      </p>
      <ul class="flex flex-wrap gap-2">
        <li
          v-for="item in alerts"
          :key="item.key"
        >
          <Link
            :href="item.href"
            class="flex items-center gap-2 rounded-lg border px-3 py-2 transition"
            :class="alertSurface[item.tone] ?? alertSurface.slate"
          >
            <span
              class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md ring-1"
              :class="toneIcon[item.tone] ?? toneIcon.slate"
            >
              <AppIcon
                :name="item.icon"
                :size="15"
              />
            </span>
            <span class="min-w-0 text-left">
              <span class="block text-[11px] font-medium text-slate-600">{{ item.label }}</span>
              <span class="flex items-baseline gap-1.5">
                <span
                  class="font-display text-lg font-bold tabular-nums leading-none"
                  :class="toneValue[item.tone] ?? toneValue.slate"
                >
                  {{ item.value }}
                </span>
                <span class="text-[10px] text-slate-400">{{ item.hint }}</span>
              </span>
            </span>
            <AppIcon
              name="chevron-right"
              :size="14"
              class="shrink-0 text-slate-300"
            />
          </Link>
        </li>
      </ul>
    </div>

    <div class="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2 xl:grid-cols-3">
      <article
        v-for="domain in domains"
        :key="domain.key"
        class="flex flex-col overflow-hidden rounded-xl border border-slate-200/80 border-t-[3px] bg-white"
        :class="domainBorder[domain.tone] ?? domainBorder.slate"
      >
        <div class="flex items-center gap-2.5 border-b border-slate-100 px-4 py-3">
          <span
            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg ring-1"
            :class="toneIcon[domain.tone] ?? toneIcon.slate"
          >
            <AppIcon
              :name="domain.icon"
              :size="17"
            />
          </span>
          <h3 class="font-display text-sm font-semibold text-slate-800">
            {{ domain.title }}
          </h3>
        </div>

        <ul class="flex flex-1 flex-col divide-y divide-slate-100">
          <li
            v-for="(metric, idx) in domain.metrics"
            :key="`${domain.key}-${idx}`"
          >
            <Link
              v-if="metric.href"
              :href="metric.href"
              class="group flex items-center justify-between gap-3 px-4 py-3 transition hover:bg-slate-50/90"
            >
              <span class="min-w-0">
                <span class="block text-[12px] font-medium text-slate-600 group-hover:text-slate-800">
                  {{ metric.label }}
                </span>
                <span
                  v-if="metric.sub"
                  class="block text-[11px] text-slate-400"
                >
                  {{ metric.sub }}
                </span>
              </span>
              <span class="flex shrink-0 items-center gap-1.5">
                <span
                  class="font-display text-xl font-bold tabular-nums"
                  :class="toneValue[domain.tone] ?? 'text-slate-800'"
                >
                  {{ metric.value }}
                </span>
                <AppIcon
                  name="chevron-right"
                  :size="14"
                  class="text-slate-300 group-hover:text-brand/60"
                />
              </span>
            </Link>
            <div
              v-else
              class="flex items-center justify-between gap-3 px-4 py-3"
            >
              <span class="min-w-0">
                <span class="block text-[12px] font-medium text-slate-600">{{ metric.label }}</span>
                <span
                  v-if="metric.sub"
                  class="block text-[11px] text-slate-400"
                >
                  {{ metric.sub }}
                </span>
              </span>
              <span
                class="font-display text-xl font-bold tabular-nums"
                :class="toneValue[domain.tone] ?? 'text-slate-800'"
              >
                {{ metric.value }}
              </span>
            </div>
          </li>
        </ul>
      </article>
    </div>
  </section>
</template>
