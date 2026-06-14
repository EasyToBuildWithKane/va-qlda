<script setup>
import AppIcon from '@/Components/AppIcon.vue';

defineProps({
    levels: { type: Array, default: () => [] },
});

function reqSummary(req) {
    const parts = [];
    const skills = req.skills || {};
    const skillNames = Object.keys(skills);
    if (skillNames.length) {
        parts.push(skillNames.slice(0, 3).map((s) => `${s} ≥ ${skills[s]}`).join(', '));
    }
    if (req.kpi) parts.push(`KPI ≥ ${req.kpi}%`);
    if (req.certifications) parts.push(`${req.certifications} chứng chỉ`);
    return parts.join(' · ');
}
</script>

<template>
  <section class="rounded-2xl border border-slate-200/70 bg-white shadow-sm">
    <header class="flex items-center gap-2.5 border-b border-slate-100 px-5 py-3.5">
      <div class="grid h-8 w-8 place-items-center rounded-lg bg-brand/10 text-brand">
        <AppIcon
          name="career"
          :size="16"
        />
      </div>
      <h2 class="text-sm font-semibold text-slate-800">
        Lộ trình phát triển
      </h2>
    </header>

    <ol class="relative p-5 before:absolute before:left-[31px] before:top-7 before:bottom-7 before:w-px before:bg-slate-100">
      <li
        v-for="lvl in levels"
        :key="lvl.key"
        class="relative flex gap-3.5 pb-5 last:pb-0"
      >
        <span
          class="z-10 grid h-8 w-8 shrink-0 place-items-center rounded-full text-[12px] font-bold ring-4 ring-white"
          :class="lvl.is_current
            ? 'bg-brand text-white'
            : lvl.achieved
              ? 'bg-emerald-100 text-emerald-600'
              : lvl.is_target
                ? 'bg-amber-100 text-amber-600'
                : 'bg-slate-100 text-slate-400'"
        >
          <AppIcon
            v-if="lvl.achieved && !lvl.is_current"
            name="check"
            :size="15"
          />
          <template v-else>{{ lvl.rank }}</template>
        </span>

        <div class="min-w-0 flex-1">
          <div class="flex flex-wrap items-center gap-2">
            <h3
              class="text-[14px] font-semibold"
              :class="lvl.is_current ? 'text-brand' : 'text-slate-800'"
            >
              {{ lvl.name }}
            </h3>
            <span
              v-if="lvl.is_current"
              class="rounded-full bg-brand/10 px-2 py-0.5 text-[10px] font-semibold text-brand"
            >Hiện tại</span>
            <span
              v-else-if="lvl.is_target"
              class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700"
            >Mục tiêu kế tiếp</span>
          </div>
          <p
            v-if="reqSummary(lvl.requirements)"
            class="mt-1 text-[12px] text-slate-500"
          >
            {{ reqSummary(lvl.requirements) }}
          </p>
          <p
            v-else
            class="mt-1 text-[12px] text-slate-400"
          >
            {{ lvl.description }}
          </p>
        </div>
      </li>
    </ol>
  </section>
</template>
