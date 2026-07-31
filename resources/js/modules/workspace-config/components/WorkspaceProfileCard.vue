<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    workspace: { type: Object, required: true },
    canManage: { type: Boolean, default: false },
    /** grid | list */
    layout: { type: String, default: 'grid' },
    /** comfortable | compact */
    density: { type: String, default: 'comfortable' },
    selectable: { type: Boolean, default: false },
    selected: { type: Boolean, default: false },
});

const emit = defineEmits(['preview', 'toggle-select']);

const accentMap = {
    brand: {
        icon: 'text-brand bg-brand/10 ring-brand/20',
        bar: 'bg-brand',
        wash: 'from-brand/[0.08] via-white to-white',
        glow: 'from-brand/[0.12]',
        chip: 'bg-brand/10 text-brand ring-brand/15',
    },
    emerald: {
        icon: 'text-emerald-700 bg-emerald-50 ring-emerald-200/80',
        bar: 'bg-emerald-500',
        wash: 'from-emerald-50/90 via-white to-white',
        glow: 'from-emerald-100/80',
        chip: 'bg-emerald-50 text-emerald-700 ring-emerald-200/70',
    },
    sky: {
        icon: 'text-sky-700 bg-sky-50 ring-sky-200/80',
        bar: 'bg-sky-500',
        wash: 'from-sky-50/90 via-white to-white',
        glow: 'from-sky-100/80',
        chip: 'bg-sky-50 text-sky-700 ring-sky-200/70',
    },
    violet: {
        icon: 'text-violet-700 bg-violet-50 ring-violet-200/80',
        bar: 'bg-violet-500',
        wash: 'from-violet-50/90 via-white to-white',
        glow: 'from-violet-100/80',
        chip: 'bg-violet-50 text-violet-700 ring-violet-200/70',
    },
    amber: {
        icon: 'text-amber-700 bg-amber-50 ring-amber-200/80',
        bar: 'bg-amber-500',
        wash: 'from-amber-50/90 via-white to-white',
        glow: 'from-amber-100/80',
        chip: 'bg-amber-50 text-amber-800 ring-amber-200/70',
    },
    rose: {
        icon: 'text-rose-700 bg-rose-50 ring-rose-200/80',
        bar: 'bg-rose-500',
        wash: 'from-rose-50/90 via-white to-white',
        glow: 'from-rose-100/80',
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

const isList = computed(() => props.layout === 'list');
const isCompact = computed(() => props.density === 'compact');

function ensureWorkspace() {
    router.post(
        `/workspace-config/w/${encodeURIComponent(props.workspace.department_code)}/ensure`,
        {},
        { preserveScroll: true },
    );
}

function onCardClick(e) {
    if (e.target.closest('a, button, input, label')) return;
    emit('preview', props.workspace);
}
</script>

<template>
  <article
    class="group relative flex cursor-pointer overflow-hidden rounded-2xl bg-white shadow-[0_1px_3px_rgb(15_23_42/0.04)] ring-1 transition duration-200"
    :class="[
      workspace.status === 'missing'
        ? 'ring-dashed ring-slate-300/90 hover:ring-brand/40'
        : 'ring-slate-200/75 hover:ring-brand/30 hover:shadow-md',
      selected ? 'ring-brand/50 shadow-md' : '',
      isList ? 'flex-row items-stretch' : 'flex-col',
    ]"
    @click="onCardClick"
  >
    <div
      class="pointer-events-none absolute inset-x-0 top-0 h-1"
      :class="accent.bar"
      aria-hidden="true"
    />
    <div
      v-if="!isCompact"
      class="pointer-events-none absolute -right-8 -top-10 h-28 w-28 rounded-full bg-gradient-to-bl to-transparent opacity-70"
      :class="accent.glow"
      aria-hidden="true"
    />

    <div
      class="relative flex min-w-0 flex-1 bg-gradient-to-br"
      :class="[
        accent.wash,
        isList
          ? 'flex-row items-center gap-3 px-3 py-3 md:gap-5 md:px-5'
          : (isCompact ? 'flex-col px-3 pb-3 pt-4' : 'flex-col px-4 pb-4 pt-5'),
      ]"
    >
      <div
        v-if="selectable"
        class="absolute right-3 top-3 z-10"
        @click.stop
      >
        <input
          type="checkbox"
          class="h-4 w-4 rounded border-slate-300 text-brand focus:ring-brand/30"
          :checked="selected"
          :aria-label="`Chọn ${workspace.department_name}`"
          @change="emit('toggle-select', workspace.department_code)"
        >
      </div>

      <!-- Identity -->
      <div
        class="flex min-w-0 items-start gap-3"
        :class="isList ? 'w-full max-w-xs shrink-0 md:max-w-sm' : ''"
      >
        <span
          class="relative flex shrink-0 items-center justify-center rounded-2xl font-display font-bold shadow-sm ring-1"
          :class="[
            accent.icon,
            isCompact ? 'h-10 w-10 text-xs' : 'h-12 w-12 text-sm',
          ]"
          :aria-hidden="true"
        >
          {{ initials }}
        </span>
        <div class="min-w-0 flex-1">
          <div class="flex flex-wrap items-center gap-1.5 pr-6">
            <h3
              class="truncate font-display font-semibold text-slate-800 transition group-hover:text-brand"
              :class="isCompact ? 'text-xs' : 'text-sm'"
              :title="workspace.department_name"
            >
              {{ workspace.department_name }}
            </h3>
            <span
              class="inline-flex items-center rounded-md px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide ring-1"
              :class="statusClass[workspace.status] ?? statusClass.missing"
            >
              {{ workspace.status_label }}
            </span>
            <span
              class="inline-flex items-center rounded-md px-1.5 py-0.5 text-[10px] font-semibold tracking-wide ring-1"
              :class="readinessClass[readiness.key] ?? readinessClass.empty"
            >
              {{ readiness.label }}
            </span>
          </div>
          <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[11px] text-slate-400">
            <span class="font-mono font-medium text-slate-500">{{ workspace.department_code }}</span>
            <span
              v-if="workspace.source_label && !isCompact"
              class="inline-flex items-center gap-1"
            >
              <span class="h-1 w-1 rounded-full bg-slate-300" />
              {{ workspace.source_label }}
            </span>
            <span
              v-if="updatedLabel && !isCompact"
              class="inline-flex items-center gap-1"
            >
              <span class="h-1 w-1 rounded-full bg-slate-300" />
              Cập nhật {{ updatedLabel }}
            </span>
          </div>
        </div>
      </div>

      <!-- Metrics -->
      <div
        v-if="!isCompact || isList"
        class="min-w-0"
        :class="isList ? 'hidden flex-1 lg:grid lg:grid-cols-3 lg:gap-3' : 'mt-4 grid grid-cols-3 gap-2'"
      >
        <div class="rounded-xl bg-white/80 px-2.5 py-2 ring-1 ring-slate-100/90">
          <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-400">
            Tiêu chí PB
          </dt>
          <dd class="mt-0.5 flex items-baseline gap-1">
            <span class="font-display text-lg font-semibold tabular-nums text-slate-800">
              {{ workspace.criteria_count }}
            </span>
          </dd>
        </div>
        <div class="rounded-xl bg-white/80 px-2.5 py-2 ring-1 ring-slate-100/90">
          <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-400">
            Module
          </dt>
          <dd class="mt-0.5 font-display text-lg font-semibold tabular-nums text-slate-800">
            {{ workspace.modules_configured }}/{{ workspace.modules_live }}
          </dd>
        </div>
        <div class="rounded-xl bg-white/80 px-2.5 py-2 ring-1 ring-slate-100/90">
          <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-400">
            Sẵn sàng
          </dt>
          <dd class="mt-0.5 font-display text-lg font-semibold tabular-nums text-slate-800">
            {{ readiness.percent }}%
          </dd>
        </div>
      </div>

      <!-- Progress + modules (grid comfortable) -->
      <div
        v-if="!isList && !isCompact"
        class="mt-3"
      >
        <div class="mb-1.5 flex items-center justify-between text-[11px]">
          <span class="font-medium text-slate-500">Mức hoàn thiện cấu hình</span>
          <span class="tabular-nums text-slate-400">
            {{ readiness.configured }}/{{ readiness.total }} module
          </span>
        </div>
        <div class="h-1.5 overflow-hidden rounded-full bg-slate-100">
          <div
            class="h-full rounded-full transition-all duration-500"
            :class="accent.bar"
            :style="{ width: `${readiness.percent}%` }"
          />
        </div>

        <ul
          v-if="workspace.modules?.length"
          class="mt-3 flex flex-wrap gap-1.5"
        >
          <li
            v-for="mod in workspace.modules"
            :key="mod.key"
          >
            <Link
              v-if="mod.href && mod.href !== '#'"
              :href="mod.href"
              class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-[11px] font-medium ring-1 transition hover:brightness-95"
              :class="mod.configured ? accent.chip : 'bg-slate-50 text-slate-500 ring-slate-200/80'"
              @click.stop
            >
              <AppIcon
                :name="mod.icon"
                :size="12"
              />
              <span class="max-w-[9rem] truncate">{{ mod.label }}</span>
              <span
                v-if="mod.count != null"
                class="tabular-nums opacity-80"
              >· {{ mod.count }}</span>
            </Link>
            <span
              v-else
              class="inline-flex items-center gap-1 rounded-lg bg-slate-50 px-2 py-1 text-[11px] font-medium text-slate-400 ring-1 ring-slate-200/70"
            >
              <AppIcon
                :name="mod.icon"
                :size="12"
              />
              {{ mod.label }}
            </span>
          </li>
        </ul>
      </div>

      <div
        v-else-if="isList"
        class="hidden min-w-[7rem] shrink-0 sm:block"
      >
        <p class="text-[10px] font-medium uppercase tracking-wide text-slate-400">
          Sẵn sàng
        </p>
        <p class="mt-0.5 font-display text-base font-semibold tabular-nums text-slate-800">
          {{ readiness.percent }}%
        </p>
        <div class="mt-1.5 h-1.5 w-20 overflow-hidden rounded-full bg-slate-100">
          <div
            class="h-full rounded-full"
            :class="accent.bar"
            :style="{ width: `${readiness.percent}%` }"
          />
        </div>
      </div>

      <!-- Actions -->
      <div
        class="flex shrink-0 flex-wrap items-center gap-2"
        :class="isList ? 'ml-auto' : 'mt-3'"
        @click.stop
      >
        <button
          type="button"
          class="btn-ghost inline-flex h-9 items-center gap-1.5 px-3 text-xs"
          @click="emit('preview', workspace)"
        >
          <AppIcon
            name="documents"
            :size="15"
          />
          Xem nhanh
        </button>
        <Link
          :href="workspace.href"
          class="btn-primary inline-flex h-9 items-center gap-1.5 px-3 text-xs"
        >
          <AppIcon
            name="system-config"
            :size="15"
          />
          Mở
        </Link>
        <Link
          v-if="workspace.evaluation_href && !isCompact"
          :href="workspace.evaluation_href"
          class="btn-ghost inline-flex h-9 items-center gap-1.5 px-3 text-xs"
        >
          <AppIcon
            name="award"
            :size="15"
          />
          <span class="hidden sm:inline">Tiêu chí</span>
          <span
            v-if="workspace.criteria_count"
            class="tabular-nums"
          >({{ workspace.criteria_count }})</span>
        </Link>
        <button
          v-if="canManage && workspace.can_ensure"
          type="button"
          class="btn-ghost inline-flex h-9 items-center gap-1.5 px-3 text-xs"
          @click="ensureWorkspace"
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
</template>
