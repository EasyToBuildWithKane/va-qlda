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
        bar: 'from-brand via-brand/80 to-brand/40',
        stripe: 'bg-brand',
        icon: 'text-brand bg-gradient-to-br from-brand/15 to-brand/5 ring-brand/20',
        wash: 'from-brand/[0.09] via-brand/[0.02]',
        glow: 'bg-brand/10',
        chip: 'bg-brand/10 text-brand ring-brand/15',
        metric: 'text-brand',
        track: 'bg-brand',
        soft: 'bg-brand/[0.06]',
    },
    emerald: {
        bar: 'from-emerald-500 via-emerald-400 to-emerald-300',
        stripe: 'bg-emerald-500',
        icon: 'text-emerald-700 bg-gradient-to-br from-emerald-100 to-emerald-50 ring-emerald-200/80',
        wash: 'from-emerald-50 via-emerald-50/30',
        glow: 'bg-emerald-400/15',
        chip: 'bg-emerald-50 text-emerald-700 ring-emerald-200/70',
        metric: 'text-emerald-700',
        track: 'bg-emerald-500',
        soft: 'bg-emerald-50/80',
    },
    sky: {
        bar: 'from-sky-500 via-sky-400 to-sky-300',
        stripe: 'bg-sky-500',
        icon: 'text-sky-700 bg-gradient-to-br from-sky-100 to-sky-50 ring-sky-200/80',
        wash: 'from-sky-50 via-sky-50/30',
        glow: 'bg-sky-400/15',
        chip: 'bg-sky-50 text-sky-700 ring-sky-200/70',
        metric: 'text-sky-700',
        track: 'bg-sky-500',
        soft: 'bg-sky-50/80',
    },
    violet: {
        bar: 'from-violet-500 via-violet-400 to-violet-300',
        stripe: 'bg-violet-500',
        icon: 'text-violet-700 bg-gradient-to-br from-violet-100 to-violet-50 ring-violet-200/80',
        wash: 'from-violet-50 via-violet-50/30',
        glow: 'bg-violet-400/15',
        chip: 'bg-violet-50 text-violet-700 ring-violet-200/70',
        metric: 'text-violet-700',
        track: 'bg-violet-500',
        soft: 'bg-violet-50/80',
    },
    amber: {
        bar: 'from-amber-500 via-amber-400 to-amber-300',
        stripe: 'bg-amber-500',
        icon: 'text-amber-800 bg-gradient-to-br from-amber-100 to-amber-50 ring-amber-200/80',
        wash: 'from-amber-50 via-amber-50/30',
        glow: 'bg-amber-400/15',
        chip: 'bg-amber-50 text-amber-800 ring-amber-200/70',
        metric: 'text-amber-800',
        track: 'bg-amber-500',
        soft: 'bg-amber-50/80',
    },
    rose: {
        bar: 'from-rose-500 via-rose-400 to-rose-300',
        stripe: 'bg-rose-500',
        icon: 'text-rose-700 bg-gradient-to-br from-rose-100 to-rose-50 ring-rose-200/80',
        wash: 'from-rose-50 via-rose-50/30',
        glow: 'bg-rose-400/15',
        chip: 'bg-rose-50 text-rose-700 ring-rose-200/70',
        metric: 'text-rose-700',
        track: 'bg-rose-500',
        soft: 'bg-rose-50/80',
    },
};

const statusClass = {
    active: 'bg-emerald-50 text-emerald-700 ring-emerald-200/80',
    draft: 'bg-amber-50 text-amber-800 ring-amber-200/80',
    missing: 'bg-slate-100 text-slate-600 ring-slate-200/80',
    archived: 'bg-rose-50 text-rose-700 ring-rose-200/80',
};

const statusDot = {
    active: 'bg-emerald-500',
    draft: 'bg-amber-500',
    missing: 'bg-slate-400',
    archived: 'bg-rose-500',
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
const showProgressTrack = computed(() => showReadiness.value || showProgress.value);

const metricCount = computed(() => [showCriteria.value, showModules.value, showReadiness.value]
    .filter(Boolean).length);

const ringDash = computed(() => {
    const r = 16;
    const c = 2 * Math.PI * r;
    const pct = Math.min(100, Math.max(0, readiness.value.percent || 0));
    return {
        circumference: c,
        offset: c - (pct / 100) * c,
    };
});

const footerCta = computed(() => {
    if (props.workspace.status === 'missing') return 'Kích hoạt workspace';
    if (readiness.value.key === 'empty') return 'Bắt đầu cấu hình';
    if (readiness.value.key === 'partial') return 'Tiếp tục cấu hình';
    return 'Mở workspace';
});

function onCardClick(e) {
    if (e.target.closest('a, button, input, label')) return;
    emit('preview', props.workspace);
}
</script>

<template>
  <article
    class="ws-card group relative flex h-full cursor-pointer flex-col overflow-hidden rounded-2xl bg-white transition duration-300"
    :class="[
      workspace.status === 'missing'
        ? 'shadow-[0_1px_2px_rgb(15_23_42/0.04)] ring-1 ring-dashed ring-slate-300/90 hover:ring-brand/45'
        : 'shadow-[0_1px_3px_rgb(15_23_42/0.05),0_4px_12px_-4px_rgb(15_23_42/0.06)] ring-1 ring-slate-200/70 hover:ring-brand/25',
      selected
        ? 'ring-2 ring-brand/55 shadow-[0_8px_24px_-8px_rgb(154_0_54/0.28)]'
        : 'hover:-translate-y-0.5 hover:shadow-[0_12px_28px_-10px_rgb(15_23_42/0.14)]',
    ]"
    @click="onCardClick"
  >
    <!-- Accent top bar -->
    <div
      class="pointer-events-none absolute inset-x-0 top-0 z-10 h-[3px] bg-gradient-to-r"
      :class="accent.bar"
      aria-hidden="true"
    />

    <!-- Atmosphere -->
    <div
      class="pointer-events-none absolute inset-0 bg-gradient-to-br to-white"
      :class="accent.wash"
      aria-hidden="true"
    />
    <div
      class="pointer-events-none absolute -right-8 -top-10 h-28 w-28 rounded-full blur-2xl transition duration-500 group-hover:scale-110"
      :class="accent.glow"
      aria-hidden="true"
    />
    <div
      class="pointer-events-none absolute -bottom-10 -left-6 h-24 w-24 rounded-full bg-slate-200/30 blur-2xl"
      aria-hidden="true"
    />

    <div class="relative flex flex-1 flex-col p-5 pt-5">
      <!-- Header -->
      <div class="flex items-start gap-3.5">
        <div class="relative shrink-0">
          <span
            class="flex h-12 w-12 items-center justify-center rounded-2xl font-display text-sm font-bold shadow-sm ring-1 ring-inset"
            :class="accent.icon"
            aria-hidden="true"
          >
            {{ initials }}
          </span>
          <span
            class="absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full ring-2 ring-white"
            :class="statusDot[workspace.status] ?? statusDot.missing"
            :title="workspace.status_label"
            aria-hidden="true"
          />
        </div>

        <div class="min-w-0 flex-1">
          <div class="flex items-start gap-2">
            <div class="min-w-0 flex-1">
              <Link
                :href="workspace.href"
                class="block truncate font-display text-[15px] font-semibold leading-snug tracking-tight text-slate-800 transition group-hover:text-brand"
                :title="workspace.department_name"
                @click.stop
              >
                {{ workspace.department_name }}
              </Link>
              <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                <span class="inline-flex items-center rounded-md bg-white/80 px-1.5 py-0.5 font-mono text-[11px] font-medium tracking-tight text-slate-500 ring-1 ring-slate-200/70">
                  {{ workspace.department_code }}
                </span>
                <span
                  class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide ring-1"
                  :class="statusClass[workspace.status] ?? statusClass.missing"
                >
                  <span
                    class="h-1.5 w-1.5 rounded-full"
                    :class="statusDot[workspace.status] ?? statusDot.missing"
                    aria-hidden="true"
                  />
                  {{ workspace.status_label }}
                </span>
              </div>
            </div>

            <div
              class="flex shrink-0 items-center gap-1.5 pt-0.5"
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
                class="grid h-8 w-8 place-items-center rounded-xl text-slate-300 ring-1 ring-transparent transition group-hover:bg-white/90 group-hover:text-brand group-hover:ring-slate-200/80"
                aria-hidden="true"
              >
                <AppIcon
                  name="chevron-right"
                  :size="16"
                  class="transition duration-300 group-hover:translate-x-0.5"
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
        class="mt-5 flex flex-1 flex-col"
      >
        <div
          class="grid gap-2"
          :style="{ gridTemplateColumns: `repeat(${metricCount}, minmax(0, 1fr))` }"
        >
          <div
            v-if="showCriteria"
            class="relative overflow-hidden rounded-xl px-2.5 py-3 text-center ring-1 ring-slate-200/60"
            :class="accent.soft"
          >
            <div class="mx-auto mb-1.5 flex h-7 w-7 items-center justify-center rounded-lg bg-white/90 text-slate-400 shadow-sm ring-1 ring-slate-100">
              <AppIcon
                name="award"
                :size="13"
              />
            </div>
            <p class="font-display text-xl font-semibold tabular-nums leading-none text-slate-800 sm:text-2xl">
              {{ workspace.criteria_count }}
            </p>
            <p class="mt-1.5 text-[10px] font-semibold uppercase tracking-wider text-slate-400">
              Tiêu chí
            </p>
          </div>

          <div
            v-if="showModules"
            class="relative overflow-hidden rounded-xl px-2.5 py-3 text-center ring-1 ring-slate-200/60"
            :class="accent.soft"
          >
            <div class="mx-auto mb-1.5 flex h-7 w-7 items-center justify-center rounded-lg bg-white/90 text-slate-400 shadow-sm ring-1 ring-slate-100">
              <AppIcon
                name="system-config"
                :size="13"
              />
            </div>
            <p class="font-display text-xl font-semibold tabular-nums leading-none text-slate-800 sm:text-2xl">
              {{ workspace.modules_configured }}/{{ workspace.modules_live }}
            </p>
            <p class="mt-1.5 text-[10px] font-semibold uppercase tracking-wider text-slate-400">
              Module
            </p>
          </div>

          <div
            v-if="showReadiness"
            class="relative overflow-hidden rounded-xl px-2.5 py-3 text-center ring-1 ring-slate-200/60"
            :class="accent.soft"
          >
            <div class="relative mx-auto mb-1.5 flex h-7 w-7 items-center justify-center">
              <svg
                class="absolute inset-0 h-7 w-7 -rotate-90"
                viewBox="0 0 40 40"
                aria-hidden="true"
              >
                <circle
                  cx="20"
                  cy="20"
                  r="16"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="4"
                  class="text-white"
                />
                <circle
                  cx="20"
                  cy="20"
                  r="16"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="4"
                  stroke-linecap="round"
                  :stroke-dasharray="ringDash.circumference"
                  :stroke-dashoffset="ringDash.offset"
                  class="transition-[stroke-dashoffset] duration-700"
                  :class="accent.metric"
                />
              </svg>
            </div>
            <p class="font-display text-xl font-semibold tabular-nums leading-none text-slate-800 sm:text-2xl">
              {{ readiness.percent }}%
            </p>
            <p class="mt-1.5 text-[10px] font-semibold uppercase tracking-wider text-slate-400">
              Sẵn sàng
            </p>
          </div>
        </div>

        <div
          v-if="showProgressTrack"
          class="mt-3.5"
        >
          <div class="mb-1.5 flex items-center justify-between text-[11px]">
            <span class="font-medium text-slate-500">{{ readiness.label }}</span>
            <span class="tabular-nums text-slate-400">
              {{ readiness.configured }}/{{ readiness.total }}
            </span>
          </div>
          <div class="h-1.5 overflow-hidden rounded-full bg-slate-100/90 ring-1 ring-inset ring-slate-200/40">
            <div
              class="h-full rounded-full transition-all duration-700 ease-out"
              :class="accent.track"
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

      <!-- Footer CTA -->
      <div class="relative mt-auto pt-4">
        <div
          class="flex items-center justify-between gap-2 border-t border-slate-100/90 pt-3"
        >
          <span
            class="inline-flex items-center gap-1.5 text-[12px] font-semibold transition"
            :class="workspace.status === 'missing' ? 'text-slate-500 group-hover:text-brand' : 'text-brand'"
          >
            {{ footerCta }}
            <AppIcon
              name="chevron-right"
              :size="13"
              class="transition duration-300 group-hover:translate-x-0.5"
            />
          </span>
          <span
            v-if="readiness.key === 'ready'"
            class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 ring-1 ring-emerald-200/70"
          >
            <AppIcon
              name="done"
              :size="11"
            />
            Sẵn sàng
          </span>
          <span
            v-else-if="workspace.status === 'missing'"
            class="text-[10px] font-medium text-slate-400"
          >
            Chưa có hồ sơ
          </span>
        </div>
      </div>
    </div>
  </article>
</template>

<style scoped>
@media (prefers-reduced-motion: reduce) {
  .ws-card,
  .ws-card * {
    transition-duration: 0.01ms !important;
    transform: none !important;
  }
}
</style>
