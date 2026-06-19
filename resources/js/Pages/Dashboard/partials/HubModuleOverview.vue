<script setup>
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';

defineProps({
    moduleGroups: { type: Array, default: () => [] },
});

const toneIcon = {
    brand: 'text-brand bg-brand/10 ring-brand/20',
    emerald: 'text-emerald-700 bg-emerald-50 ring-emerald-200/80',
    amber: 'text-amber-700 bg-amber-50 ring-amber-200/80',
    sky: 'text-sky-700 bg-sky-50 ring-sky-200/80',
    violet: 'text-violet-700 bg-violet-50 ring-violet-200/80',
    rose: 'text-rose-700 bg-rose-50 ring-rose-200/80',
    slate: 'text-slate-600 bg-slate-100 ring-slate-200/80',
};

const toneStat = {
    brand: 'text-brand',
    emerald: 'text-emerald-700',
    amber: 'text-amber-700',
    sky: 'text-sky-700',
    violet: 'text-violet-700',
    rose: 'text-rose-700',
    slate: 'text-slate-600',
};

const toneGroupAccent = {
    brand: 'from-brand/80 to-brand/30',
    emerald: 'from-emerald-500/80 to-emerald-400/30',
    amber: 'from-amber-500/80 to-amber-400/30',
    sky: 'from-sky-500/80 to-sky-400/30',
    violet: 'from-violet-500/80 to-violet-400/30',
    rose: 'from-rose-500/80 to-rose-400/30',
    slate: 'from-slate-400/80 to-slate-300/30',
};
</script>

<template>
  <section
    class="kpi-strip relative overflow-hidden rounded-card border border-slate-200/80 bg-gradient-to-b from-slate-50/90 to-white shadow-sm"
    aria-label="Tổng quan module hệ thống"
  >
    <div
      class="kpi-strip__bg-outer pointer-events-none absolute -right-6 top-0 h-full w-1/2 bg-gradient-to-l from-brand/[0.04] to-transparent"
      aria-hidden="true"
    />
    <div
      class="kpi-strip__bg-inner pointer-events-none absolute right-0 top-0 h-24 w-32 bg-gradient-to-bl from-brand/[0.06] to-transparent"
      aria-hidden="true"
    />

    <header class="relative border-b border-slate-100/80 px-5 py-4">
      <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-brand/80">
        Module
      </p>
      <div class="mt-1 flex flex-wrap items-end justify-between gap-2">
        <h2 class="font-display text-base font-semibold text-slate-800">
          Tổng quan & truy cập nhanh
        </h2>
        <p class="text-[11px] text-slate-400">
          Hiển thị theo quyền tài khoản của bạn
        </p>
      </div>
    </header>

    <div class="relative grid grid-cols-1 gap-4 p-4 md:grid-cols-2 xl:grid-cols-3">
      <article
        v-for="group in moduleGroups"
        :key="group.key"
        class="flex flex-col overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm"
      >
        <div class="flex items-center gap-3 border-b border-slate-100 px-4 py-3">
          <span
            class="h-9 w-1 shrink-0 rounded-full bg-gradient-to-b"
            :class="toneGroupAccent[group.tone] ?? toneGroupAccent.slate"
            aria-hidden="true"
          />
          <span
            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg ring-1"
            :class="toneIcon[group.tone] ?? toneIcon.slate"
          >
            <AppIcon
              :name="group.icon"
              :size="17"
            />
          </span>
          <h3 class="min-w-0 font-display text-sm font-semibold text-slate-800">
            {{ group.label }}
          </h3>
        </div>

        <ul class="flex flex-1 flex-col gap-1.5 p-3">
          <li
            v-for="mod in group.modules"
            :key="mod.key"
          >
            <Link
              :href="mod.href"
              class="group flex items-center gap-3 rounded-lg border border-transparent px-2.5 py-2.5 transition hover:border-slate-200/90 hover:bg-slate-50/90"
            >
              <span
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg ring-1 transition group-hover:shadow-sm"
                :class="toneIcon[mod.tone] ?? toneIcon.slate"
              >
                <AppIcon
                  :name="mod.icon"
                  :size="16"
                />
              </span>
              <span class="min-w-0 flex-1">
                <span class="block truncate text-[13px] font-medium text-slate-700 group-hover:text-slate-900">
                  {{ mod.label }}
                </span>
                <span class="block truncate text-[11px] text-slate-400">
                  {{ mod.statLabel }}
                </span>
              </span>
              <span class="flex shrink-0 flex-col items-end gap-0.5">
                <span
                  v-if="mod.stat !== null && mod.stat !== undefined"
                  class="font-display text-lg font-bold tabular-nums leading-none"
                  :class="toneStat[mod.tone] ?? 'text-slate-700'"
                >
                  {{ mod.stat }}
                </span>
                <AppIcon
                  name="chevron-right"
                  :size="14"
                  class="text-slate-300 transition group-hover:text-brand/70"
                />
              </span>
            </Link>
          </li>
        </ul>
      </article>
    </div>
  </section>
</template>

<style scoped>
@import '@/shared/styles/kpi-summary-strip.css';
</style>
