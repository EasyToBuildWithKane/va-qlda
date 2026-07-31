<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    workspace: { type: Object, required: true },
    selectable: { type: Boolean, default: false },
    selected: { type: Boolean, default: false },
    /** Visible field keys from column picker */
    visibleFields: {
        type: Object,
        default: () => ({
            criteria: true,
            modules: true,
            readiness: true,
            readiness_badge: false,
            source: false,
            progress: false,
            updated: false,
        }),
    },
});

const emit = defineEmits(['preview', 'toggle-select']);

const accentMap = {
    brand: {
        icon: 'text-brand bg-brand/10 ring-brand/20',
        bar: 'bg-brand',
        wash: 'from-brand/[0.07]',
        chip: 'bg-brand/10 text-brand ring-brand/15',
    },
    emerald: {
        icon: 'text-emerald-700 bg-emerald-50 ring-emerald-200/80',
        bar: 'bg-emerald-500',
        wash: 'from-emerald-50',
        chip: 'bg-emerald-50 text-emerald-700 ring-emerald-200/70',
    },
    sky: {
        icon: 'text-sky-700 bg-sky-50 ring-sky-200/80',
        bar: 'bg-sky-500',
        wash: 'from-sky-50',
        chip: 'bg-sky-50 text-sky-700 ring-sky-200/70',
    },
    violet: {
        icon: 'text-violet-700 bg-violet-50 ring-violet-200/80',
        bar: 'bg-violet-500',
        wash: 'from-violet-50',
        chip: 'bg-violet-50 text-violet-700 ring-violet-200/70',
    },
    amber: {
        icon: 'text-amber-700 bg-amber-50 ring-amber-200/80',
        bar: 'bg-amber-500',
        wash: 'from-amber-50',
        chip: 'bg-amber-50 text-amber-800 ring-amber-200/70',
    },
    rose: {
        icon: 'text-rose-700 bg-rose-50 ring-rose-200/80',
        bar: 'bg-rose-500',
        wash: 'from-rose-50',
        chip: 'bg-rose-50 text-rose-700 ring-rose-200/70',
    },
};

const statusClass = {
    active: 'bg-emerald-50 text-emerald-700 ring-emerald-200/80',
    draft: 'bg-amber-50 text-amber-800 ring-amber-200/80',
    missing: 'bg-slate-100 text-slate-600 ring-slate-200/80',
    archived: 'bg-rose-50 text-rose-700 ring-rose-200/80',
};

const readinessClass = {
    ready: 'bg-emerald-50 text-emerald-700 ring-emerald-200/80',
    partial: 'bg-sky-50 text-sky-700 ring-sky-200/80',
    empty: 'bg-slate-100 text-slate-500 ring-slate-200/80',
};

const accent = computed(() => accentMap[props.workspace.accent] ?? accentMap.brand);

const readiness = computed(() => props.workspace.readiness ?? {
    key: 'empty',
    label: 'Chưa có nội dung',
    percent: 0,
    configured: 0,
    total: 0,
});

const initials = computed(() => {
    const name = String(props.workspace.department_name || '');
    const parts = name.trim().split(/\s+/).filter(Boolean);
    if (parts.length >= 2) {
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }
    return (props.workspace.department_code || '?').slice(0, 2).toUpperCase();
});

const updatedLabel = computed(() => {
    const raw = props.workspace.updated_at;
    if (!raw) return null;
    try {
        return new Intl.DateTimeFormat('vi-VN', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
        }).format(new Date(raw));
    } catch {
        return null;
    }
});

const showCriteria = computed(() => props.visibleFields.criteria !== false);
const showModules = computed(() => props.visibleFields.modules !== false);
const showReadiness = computed(() => props.visibleFields.readiness !== false);
const showReadinessBadge = computed(() => props.visibleFields.readiness_badge === true);
const showSource = computed(() => props.visibleFields.source === true);
const showProgress = computed(() => props.visibleFields.progress === true);
const showUpdated = computed(() => props.visibleFields.updated === true);

const hasMetrics = computed(() => showCriteria.value || showModules.value || showReadiness.value);
const showMetaRow = computed(() => showSource.value || showUpdated.value || showReadinessBadge.value);

function onCardClick(e) {
    if (e.target.closest('a, button, input, label')) return;
    emit('preview', props.workspace);
}
</script>

<template>
  <article
    class="group relative flex h-full cursor-pointer flex-col overflow-hidden rounded-2xl bg-white shadow-[0_1px_2px_rgb(15_23_42/0.04)] ring-1 transition duration-200"
    :class="[
      workspace.status === 'missing'
        ? 'ring-dashed ring-slate-300/85 hover:ring-brand/40 hover:shadow-md'
        : 'ring-slate-200/70 hover:ring-brand/30 hover:shadow-md',
      selected ? 'ring-2 ring-brand/50 shadow-md' : '',
    ]"
    @click="onCardClick"
  >
    <div
      class="pointer-events-none absolute inset-0 bg-gradient-to-br via-white to-white"
      :class="accent.wash"
      aria-hidden="true"
    />

    <div class="relative flex flex-1 flex-col p-5">
      <!-- Header -->
      <div class="flex items-start gap-3.5">
        <span
          class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl font-display text-sm font-bold shadow-sm ring-1"
          :class="accent.icon"
          aria-hidden="true"
        >
          {{ initials }}
        </span>

        <div class="min-w-0 flex-1">
          <div class="flex items-start gap-2">
            <div class="min-w-0 flex-1">
              <Link
                :href="workspace.href"
                class="block truncate font-display text-base font-semibold leading-snug text-slate-800 transition group-hover:text-brand"
                :title="workspace.department_name"
                @click.stop
              >
                {{ workspace.department_name }}
              </Link>
              <div class="mt-1.5 flex flex-wrap items-center gap-2">
                <span class="font-mono text-xs font-medium text-slate-500">
                  {{ workspace.department_code }}
                </span>
                <span
                  class="inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide ring-1"
                  :class="statusClass[workspace.status] ?? statusClass.missing"
                >
                  {{ workspace.status_label }}
                </span>
              </div>
            </div>

            <div
              class="flex shrink-0 items-center gap-2 pt-0.5"
              @click.stop
            >
              <input
                v-if="selectable"
                type="checkbox"
                class="h-4 w-4 rounded border-slate-300 text-brand focus:ring-brand/30"
                :checked="selected"
                :aria-label="`Chọn ${workspace.department_name}`"
                @change="emit('toggle-select', workspace.department_code)"
              >
              <span
                class="grid h-8 w-8 place-items-center rounded-lg text-slate-300 transition group-hover:bg-white/80 group-hover:text-brand"
                aria-hidden="true"
              >
                <AppIcon
                  name="chevron-right"
                  :size="16"
                />
              </span>
            </div>
          </div>

          <div
            v-if="showMetaRow"
            class="mt-2 flex flex-wrap items-center gap-x-2.5 gap-y-1 text-[11px] text-slate-400"
          >
            <span
              v-if="showReadinessBadge"
              class="inline-flex items-center rounded-md px-2 py-0.5 font-medium ring-1"
              :class="readinessClass[readiness.key] ?? readinessClass.empty"
            >
              {{ readiness.label }}
            </span>
            <span v-if="showSource && workspace.source_label">{{ workspace.source_label }}</span>
            <span v-if="showUpdated && updatedLabel">Cập nhật {{ updatedLabel }}</span>
          </div>
        </div>
      </div>

      <!-- Metrics -->
      <div
        v-if="hasMetrics"
        class="mt-5"
      >
        <div
          class="grid gap-2.5"
          :style="{ gridTemplateColumns: `repeat(${[showCriteria, showModules, showReadiness].filter(Boolean).length}, minmax(0, 1fr))` }"
        >
          <div
            v-if="showCriteria"
            class="rounded-xl bg-white/90 px-3 py-3 text-center shadow-sm ring-1 ring-slate-100/90"
          >
            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">
              Tiêu chí
            </p>
            <p class="mt-1 font-display text-2xl font-semibold tabular-nums text-slate-800">
              {{ workspace.criteria_count }}
            </p>
          </div>
          <div
            v-if="showModules"
            class="rounded-xl bg-white/90 px-3 py-3 text-center shadow-sm ring-1 ring-slate-100/90"
          >
            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">
              Module
            </p>
            <p class="mt-1 font-display text-2xl font-semibold tabular-nums text-slate-800">
              {{ workspace.modules_configured }}/{{ workspace.modules_live }}
            </p>
          </div>
          <div
            v-if="showReadiness"
            class="rounded-xl bg-white/90 px-3 py-3 text-center shadow-sm ring-1 ring-slate-100/90"
          >
            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">
              Sẵn sàng
            </p>
            <p class="mt-1 font-display text-2xl font-semibold tabular-nums text-slate-800">
              {{ readiness.percent }}%
            </p>
          </div>
        </div>

        <div
          v-if="showReadiness || showProgress"
          class="mt-3"
        >
          <div class="mb-1.5 flex items-center justify-between text-[11px]">
            <span class="font-medium text-slate-500">Hoàn thiện cấu hình</span>
            <span class="tabular-nums text-slate-400">
              {{ readiness.configured }}/{{ readiness.total }}
            </span>
          </div>
          <div class="h-1.5 overflow-hidden rounded-full bg-slate-100">
            <div
              class="h-full rounded-full transition-all duration-500"
              :class="accent.bar"
              :style="{ width: `${readiness.percent}%` }"
            />
          </div>
        </div>

        <ul
          v-if="showProgress && workspace.modules?.length"
          class="mt-3 flex flex-wrap gap-1.5"
        >
          <li
            v-for="mod in workspace.modules"
            :key="mod.key"
          >
            <Link
              v-if="mod.href && mod.href !== '#'"
              :href="mod.href"
              class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-[11px] font-medium ring-1 transition hover:brightness-95"
              :class="mod.configured ? accent.chip : 'bg-slate-50 text-slate-500 ring-slate-200/80'"
              @click.stop
            >
              <AppIcon
                :name="mod.icon"
                :size="12"
              />
              <span class="max-w-[9rem] truncate">{{ mod.label }}</span>
            </Link>
            <span
              v-else
              class="inline-flex items-center gap-1.5 rounded-lg bg-slate-50 px-2.5 py-1 text-[11px] font-medium text-slate-400 ring-1 ring-slate-200/70"
            >
              {{ mod.label }}
            </span>
          </li>
        </ul>
      </div>
    </div>
  </article>
</template>
