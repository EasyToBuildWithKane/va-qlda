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
    slate: 'text-slate-800',
};

const toneHeaderBg = {
    brand: 'from-brand/[0.07] via-white to-white',
    emerald: 'from-emerald-500/[0.08] via-white to-white',
    amber: 'from-amber-500/[0.08] via-white to-white',
    sky: 'from-sky-500/[0.08] via-white to-white',
    violet: 'from-violet-500/[0.08] via-white to-white',
    rose: 'from-rose-500/[0.08] via-white to-white',
    slate: 'from-slate-200/40 via-white to-white',
};

const alertChip = {
    brand: 'bg-brand/5 ring-brand/15 hover:bg-brand/[0.09]',
    emerald: 'bg-emerald-50/90 ring-emerald-200/70 hover:bg-emerald-50',
    amber: 'bg-amber-50/90 ring-amber-200/70 hover:bg-amber-50',
    sky: 'bg-sky-50/90 ring-sky-200/70 hover:bg-sky-50',
    violet: 'bg-violet-50/90 ring-violet-200/70 hover:bg-violet-50',
    rose: 'bg-rose-50/90 ring-rose-200/70 hover:bg-rose-50',
    slate: 'bg-slate-50 ring-slate-200/80 hover:bg-slate-50',
};
</script>

<template>
  <section
    class="hub-panel mb-5 overflow-hidden rounded-card border border-slate-200/80 bg-white shadow-sm"
    aria-label="Ảnh chụp nhanh các domain hệ thống"
  >
    <header class="border-b border-slate-100/90 px-5 py-4">
      <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-brand/80">
        Domain
      </p>
      <h2 class="mt-1 font-display text-base font-semibold text-slate-800">
        Ảnh chụp hệ sinh thái
      </h2>
    </header>

    <div
      v-if="alerts.length"
      class="border-b border-slate-100 px-5 py-4"
    >
      <ul class="flex flex-wrap gap-2.5">
        <li
          v-for="item in alerts"
          :key="item.key"
        >
          <Link
            :href="item.href"
            class="group flex items-center gap-3 rounded-xl px-3.5 py-2.5 ring-1 transition hover:shadow-sm"
            :class="alertChip[item.tone] ?? alertChip.slate"
          >
            <span
              class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg ring-1"
              :class="toneIcon[item.tone] ?? toneIcon.slate"
            >
              <AppIcon
                :name="item.icon"
                :size="16"
              />
            </span>
            <span class="min-w-0 text-left">
              <span class="block text-[12px] font-medium text-slate-700">{{ item.label }}</span>
              <span
                class="font-display text-xl font-bold tabular-nums leading-tight"
                :class="toneValue[item.tone] ?? toneValue.slate"
              >
                {{ item.value }}
              </span>
            </span>
            <AppIcon
              name="chevron-right"
              :size="15"
              class="shrink-0 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-brand/70"
            />
          </Link>
        </li>
      </ul>
    </div>

    <div class="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2 xl:grid-cols-3">
      <article
        v-for="domain in domains"
        :key="domain.key"
        class="hub-domain-card flex flex-col overflow-hidden rounded-xl bg-white ring-1 ring-slate-200/75 shadow-[0_1px_3px_rgb(15_23_42/0.04)]"
      >
        <div
          class="flex items-center gap-3 bg-gradient-to-br px-4 py-3.5"
          :class="toneHeaderBg[domain.tone] ?? toneHeaderBg.slate"
        >
          <span
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ring-1 shadow-sm"
            :class="toneIcon[domain.tone] ?? toneIcon.slate"
          >
            <AppIcon
              :name="domain.icon"
              :size="18"
            />
          </span>
          <h3 class="font-display text-sm font-semibold leading-snug text-slate-800">
            {{ domain.title }}
          </h3>
        </div>

        <ul class="flex flex-1 flex-col">
          <li
            v-for="(metric, idx) in domain.metrics"
            :key="`${domain.key}-${idx}`"
            class="border-t border-slate-100/90 first:border-t-0"
          >
            <Link
              v-if="metric.href"
              :href="metric.href"
              class="group flex items-center justify-between gap-4 px-4 py-3.5 transition hover:bg-slate-50/80"
            >
              <span class="min-w-0 flex-1 text-[13px] font-medium text-slate-600 group-hover:text-slate-900">
                {{ metric.label }}
              </span>
              <span class="flex shrink-0 items-center gap-2">
                <span class="flex flex-col items-end gap-1">
                  <span
                    class="font-display text-2xl font-bold tabular-nums leading-none"
                    :class="toneValue[domain.tone] ?? 'text-slate-800'"
                  >
                    {{ metric.value }}
                  </span>
                  <span
                    v-if="metric.badge"
                    class="rounded-md px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                    :class="metric.badgeClass ?? 'bg-slate-100 text-slate-600'"
                  >
                    {{ metric.badge }}
                  </span>
                </span>
                <AppIcon
                  name="chevron-right"
                  :size="14"
                  class="text-slate-300 transition group-hover:text-brand/60"
                />
              </span>
            </Link>
            <div
              v-else
              class="flex items-center justify-between gap-4 px-4 py-3.5"
            >
              <span class="min-w-0 flex-1 text-[13px] font-medium text-slate-600">
                {{ metric.label }}
              </span>
              <span
                class="font-display text-2xl font-bold tabular-nums leading-none"
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
