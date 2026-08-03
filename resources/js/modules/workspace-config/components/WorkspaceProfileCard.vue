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
        stripe: 'bg-brand',
        avatar: 'bg-brand/10 text-brand',
        track: 'bg-brand',
        chipOn: 'bg-brand/10 text-brand',
    },
    emerald: {
        stripe: 'bg-emerald-500',
        avatar: 'bg-emerald-100 text-emerald-700',
        track: 'bg-emerald-500',
        chipOn: 'bg-emerald-100 text-emerald-700',
    },
    sky: {
        stripe: 'bg-sky-500',
        avatar: 'bg-sky-100 text-sky-700',
        track: 'bg-sky-500',
        chipOn: 'bg-sky-100 text-sky-700',
    },
    violet: {
        stripe: 'bg-violet-500',
        avatar: 'bg-violet-100 text-violet-700',
        track: 'bg-violet-500',
        chipOn: 'bg-violet-100 text-violet-700',
    },
    amber: {
        stripe: 'bg-amber-500',
        avatar: 'bg-amber-100 text-amber-800',
        track: 'bg-amber-500',
        chipOn: 'bg-amber-100 text-amber-800',
    },
    rose: {
        stripe: 'bg-rose-500',
        avatar: 'bg-rose-100 text-rose-700',
        track: 'bg-rose-500',
        chipOn: 'bg-rose-100 text-rose-700',
    },
};

const statusTone = {
    active: 'text-emerald-700',
    draft: 'text-amber-700',
    missing: 'text-slate-500',
    archived: 'text-rose-700',
};

const statusDot = {
    active: 'bg-emerald-500',
    draft: 'bg-amber-500',
    missing: 'bg-slate-400',
    archived: 'bg-rose-500',
};

const readinessTone = {
    ready: 'text-emerald-700',
    partial: 'text-sky-700',
    empty: 'text-slate-500',
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

function onCardClick(e) {
    if (e.target.closest('a, button, input, label')) return;
    emit('preview', props.workspace);
}
</script>

<template>
  <article
    class="ws-card group relative flex h-full cursor-pointer flex-col rounded-2xl pl-4 transition duration-200"
    :class="[
      selected
        ? 'bg-brand/[0.07]'
        : workspace.status === 'missing'
          ? 'bg-slate-100/70 hover:bg-slate-100'
          : 'bg-slate-50 hover:bg-white hover:shadow-[0_8px_24px_-12px_rgb(15_23_42/0.18)]',
    ]"
    @click="onCardClick"
  >
    <span
      class="absolute inset-y-3 left-0 w-1 rounded-full"
      :class="accent.stripe"
      aria-hidden="true"
    />

    <div class="flex flex-1 flex-col gap-4 p-4">
      <div class="flex items-start gap-3">
        <span
          class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl font-display text-sm font-bold"
          :class="accent.avatar"
          aria-hidden="true"
        >
          {{ initials }}
        </span>

        <div class="min-w-0 flex-1">
          <div class="flex items-start gap-2">
            <div class="min-w-0 flex-1">
              <Link
                :href="workspace.href"
                class="block truncate font-display text-[15px] font-semibold leading-snug text-slate-800 transition group-hover:text-brand"
                :title="workspace.department_name"
                @click.stop
              >
                {{ workspace.department_name }}
              </Link>
              <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[12px]">
                <span class="font-mono font-medium text-slate-500">{{ workspace.department_code }}</span>
                <span
                  class="inline-flex items-center gap-1.5 font-medium"
                  :class="statusTone[workspace.status] ?? statusTone.missing"
                >
                  <span
                    class="h-1.5 w-1.5 rounded-full"
                    :class="statusDot[workspace.status] ?? statusDot.missing"
                    aria-hidden="true"
                  />
                  {{ workspace.status_label }}
                </span>
              </p>
            </div>

            <div
              v-if="selectable"
              class="shrink-0 pt-0.5"
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
          </div>

          <p
            v-if="showMetaRow"
            class="mt-1.5 flex flex-wrap items-center gap-x-2.5 gap-y-0.5 text-[11px] text-slate-400"
          >
            <span
              v-if="showReadinessBadge"
              class="font-medium"
              :class="readinessTone[readiness.key] ?? readinessTone.empty"
            >
              {{ readiness.label }}
            </span>
            <span v-if="showSource && workspace.source_label">{{ workspace.source_label }}</span>
            <span v-if="showUpdated && updatedLabel">{{ updatedLabel }}</span>
          </p>
        </div>
      </div>

      <div
        v-if="hasMetrics"
        class="mt-auto flex flex-col gap-3"
      >
        <div
          class="grid gap-3"
          :style="{ gridTemplateColumns: `repeat(${metricCount}, minmax(0, 1fr))` }"
        >
          <div
            v-if="showCriteria"
            class="min-w-0"
          >
            <p class="font-display text-2xl font-semibold tabular-nums leading-none text-slate-800">
              {{ workspace.criteria_count }}
            </p>
            <p class="mt-1.5 text-[11px] font-medium text-slate-400">
              Tiêu chí
            </p>
          </div>

          <div
            v-if="showModules"
            class="min-w-0"
          >
            <p class="font-display text-2xl font-semibold tabular-nums leading-none text-slate-800">
              {{ workspace.modules_configured }}/{{ workspace.modules_live }}
            </p>
            <p class="mt-1.5 text-[11px] font-medium text-slate-400">
              Module
            </p>
          </div>

          <div
            v-if="showReadiness"
            class="min-w-0"
          >
            <p class="font-display text-2xl font-semibold tabular-nums leading-none text-slate-800">
              {{ readiness.percent }}%
            </p>
            <p class="mt-1.5 text-[11px] font-medium text-slate-400">
              Sẵn sàng
            </p>
          </div>
        </div>

        <div
          v-if="showProgressTrack"
          class="space-y-1.5"
        >
          <div class="flex items-center justify-between text-[11px] text-slate-500">
            <span>{{ readiness.label }}</span>
            <span class="tabular-nums text-slate-400">
              {{ readiness.configured }}/{{ readiness.total }}
            </span>
          </div>
          <div class="h-1 overflow-hidden rounded-full bg-slate-200/80">
            <div
              class="h-full rounded-full transition-all duration-500 ease-out"
              :class="accent.track"
              :style="{ width: `${readiness.percent}%` }"
            />
          </div>
        </div>

        <ul
          v-if="showProgress && workspace.modules?.length"
          class="flex flex-wrap gap-1.5"
        >
          <li
            v-for="mod in workspace.modules"
            :key="mod.key"
          >
            <Link
              v-if="mod.href && mod.href !== '#'"
              :href="mod.href"
              class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-[11px] font-medium transition hover:opacity-80"
              :class="mod.configured ? accent.chipOn : 'bg-slate-200/60 text-slate-500'"
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
              class="inline-flex items-center gap-1.5 rounded-lg bg-slate-200/60 px-2 py-1 text-[11px] font-medium text-slate-400"
            >
              {{ mod.label }}
            </span>
          </li>
        </ul>
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
