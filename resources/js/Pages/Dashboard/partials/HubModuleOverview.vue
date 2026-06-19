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
    slate: 'text-slate-700',
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
        Truy cập
      </p>
      <h2 class="mt-1 font-display text-base font-semibold text-slate-800">
        Module theo quyền của bạn
      </h2>
    </header>

    <div class="relative grid grid-cols-1 gap-4 p-4 md:grid-cols-2 xl:grid-cols-3">
      <article
        v-for="group in moduleGroups"
        :key="group.key"
        class="flex flex-col overflow-hidden rounded-xl bg-white ring-1 ring-slate-200/75 shadow-[0_1px_3px_rgb(15_23_42/0.04)]"
      >
        <div
          class="flex items-center gap-3 bg-gradient-to-br px-4 py-3.5"
          :class="toneHeaderBg[group.tone] ?? toneHeaderBg.slate"
        >
          <span
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ring-1 shadow-sm"
            :class="toneIcon[group.tone] ?? toneIcon.slate"
          >
            <AppIcon
              :name="group.icon"
              :size="18"
            />
          </span>
          <h3 class="min-w-0 font-display text-sm font-semibold text-slate-800">
            {{ group.label }}
          </h3>
        </div>

        <ul class="flex flex-1 flex-col">
          <li
            v-for="mod in group.modules"
            :key="mod.key"
            class="border-t border-slate-100/90 first:border-t-0"
          >
            <Link
              :href="mod.href"
              class="group flex items-center gap-3 px-3 py-3 transition hover:bg-slate-50/80"
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
              <span class="min-w-0 flex-1 truncate text-[13px] font-medium text-slate-700 group-hover:text-slate-900">
                {{ mod.label }}
              </span>
              <span class="flex shrink-0 items-center gap-2">
                <span
                  v-if="mod.stat !== null && mod.stat !== undefined"
                  class="flex items-baseline gap-1 tabular-nums"
                >
                  <span
                    class="font-display text-xl font-bold leading-none"
                    :class="toneStat[mod.tone] ?? 'text-slate-800'"
                  >
                    {{ mod.stat }}
                  </span>
                  <span
                    v-if="mod.statUnit"
                    class="text-[10px] font-semibold uppercase tracking-wide text-slate-400"
                  >
                    {{ mod.statUnit }}
                  </span>
                </span>
                <AppIcon
                  name="chevron-right"
                  :size="14"
                  class="text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-brand/70"
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
