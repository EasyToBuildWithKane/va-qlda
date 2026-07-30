<script setup>
import { Link, router } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';

defineProps({
    workspaces: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
});

const statusClass = {
    active: 'bg-emerald-50 text-emerald-700 ring-emerald-200/80',
    draft: 'bg-amber-50 text-amber-800 ring-amber-200/80',
    missing: 'bg-slate-100 text-slate-600 ring-slate-200/80',
    archived: 'bg-rose-50 text-rose-700 ring-rose-200/80',
};

function ensureWorkspace(code) {
    router.post(`/workspace-config/w/${encodeURIComponent(code)}/ensure`, {}, {
        preserveScroll: true,
    });
}
</script>

<template>
  <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
    <article
      v-for="ws in workspaces"
      :key="ws.department_code"
      class="group flex flex-col overflow-hidden rounded-xl bg-white shadow-[0_1px_3px_rgb(15_23_42/0.04)] ring-1 transition"
      :class="ws.status === 'missing'
        ? 'ring-dashed ring-slate-300/90 hover:ring-brand/35'
        : 'ring-slate-200/75 hover:ring-brand/30 hover:shadow-md'"
    >
      <div class="flex flex-1 flex-col bg-gradient-to-br from-slate-50/90 via-white to-white px-4 py-4">
        <div class="flex items-start gap-3">
          <span
            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand/10 text-brand shadow-sm ring-1 ring-brand/20"
          >
            <AppIcon
              name="department"
              :size="18"
            />
          </span>
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <h3 class="font-display text-sm font-semibold text-slate-800 group-hover:text-brand">
                {{ ws.department_name }}
              </h3>
              <span
                class="inline-flex items-center rounded-md px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide ring-1"
                :class="statusClass[ws.status] ?? statusClass.missing"
              >
                {{ ws.status_label }}
              </span>
            </div>
            <p class="mt-1 font-mono text-[11px] text-slate-400">
              {{ ws.department_code }}
            </p>
          </div>
        </div>

        <dl class="mt-4 grid grid-cols-2 gap-2 text-[11px]">
          <div class="rounded-lg bg-slate-50 px-2.5 py-2 ring-1 ring-slate-100">
            <dt class="text-slate-400">
              Tiêu chí PB
            </dt>
            <dd class="mt-0.5 font-display text-base font-semibold tabular-nums text-slate-800">
              {{ ws.criteria_count }}
            </dd>
          </div>
          <div class="rounded-lg bg-slate-50 px-2.5 py-2 ring-1 ring-slate-100">
            <dt class="text-slate-400">
              Module live
            </dt>
            <dd class="mt-0.5 font-display text-base font-semibold tabular-nums text-slate-800">
              {{ ws.modules_live }}
            </dd>
          </div>
        </dl>

        <div class="mt-4 flex flex-wrap items-center gap-2">
          <Link
            :href="ws.href"
            class="btn-primary inline-flex h-9 items-center gap-1.5 px-3 text-xs"
          >
            <AppIcon
              name="system-config"
              :size="15"
            />
            Mở workspace
          </Link>
          <button
            v-if="canManage && ws.can_ensure"
            type="button"
            class="btn-ghost inline-flex h-9 items-center gap-1.5 px-3 text-xs"
            @click="ensureWorkspace(ws.department_code)"
          >
            <AppIcon
              name="plus"
              :size="15"
            />
            Kích hoạt
          </button>
        </div>
      </div>
    </article>
  </div>
</template>
