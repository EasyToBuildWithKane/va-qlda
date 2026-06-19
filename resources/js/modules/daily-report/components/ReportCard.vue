<script setup>
/* eslint-disable vue/no-v-html -- rendered HORENSO rich-text report fields */
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import StatusBadge from '@/modules/daily-report/components/StatusBadge.vue';
import TaskStatusBadge from '@/Components/TaskStatusBadge.vue';
import GradePill from '@/modules/daily-report/components/GradePill.vue';
import { date as formatDate, datetime } from '@/composables/useFormat';
import { fields as reportFields, pillars } from '@/modules/daily-report/config/reportConfig';

const props = defineProps({
    report: { type: Object, required: true },
    showEmployee: { type: Boolean, default: true },
    defaultOpen: { type: Boolean, default: false },
});

const emit = defineEmits(['delete']);

const open = ref(props.defaultOpen);

const pillarColor = Object.fromEntries(pillars.map((p) => [p.key, p.color]));
const ACCENT = {
    brand: 'bg-brand', amber: 'bg-amber-400', emerald: 'bg-emerald-400', sky: 'bg-sky-400',
};

/** Strip HTML and entities to detect visually-empty rich-text (e.g. "<ul><li></li></ul>"). */
function hasText(html) {
    if (!html) return false;
    const text = String(html).replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim();
    return text.length > 0;
}

const sections = computed(() =>
    reportFields
        .map((f) => ({
            key: f.key,
            label: f.label,
            html: props.report[f.key],
            accent: ACCENT[pillarColor[f.pillar]] ?? 'bg-slate-300',
        }))
        .filter((s) => hasText(s.html)),
);

const linkedProjects = computed(() => props.report.projects ?? []);

const reportTime = computed(() => {
    if (props.report.submitted_at) return datetime(props.report.submitted_at);
    return formatDate(props.report.date);
});

const hasFeedback = computed(() => {
    const r = props.report;
    if (r.has_feedback) return true;
    if (r.status === 'draft' && (r.review_notes || '').trim()) return true;
    return Boolean((r.score?.notes || '').trim());
});
</script>

<template>
  <article
    class="flex flex-col rounded-card border border-slate-200 bg-white shadow-sm transition hover:border-brand/25 hover:shadow-md dark:border-slate-700 dark:bg-slate-900"
  >
    <!-- Header -->
    <div class="flex items-start gap-3 p-4">
      <Avatar
        v-if="showEmployee"
        :name="report.employee?.name ?? '?'"
        :src="report.employee?.avatar_path"
        :size="40"
      />
      <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
          <span
            v-if="showEmployee"
            class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100"
            :title="report.employee?.name"
          >
            {{ report.employee?.name ?? '—' }}
          </span>
          <span
            v-if="report.employee?.role_title"
            class="truncate rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-500 dark:bg-slate-800 dark:text-slate-400"
          >
            {{ report.employee.role_title }}
          </span>
        </div>
        <div class="mt-0.5 flex items-center gap-1.5 text-xs text-slate-400">
          <AppIcon
            name="clock"
            :size="12"
          />
          <span class="tabular-nums">{{ reportTime }}</span>
        </div>
      </div>
      <div class="flex shrink-0 flex-col items-end gap-1.5">
        <StatusBadge
          :label="report.status_label"
          :color="report.status_color"
        />
        <div class="flex items-center gap-1.5">
          <span
            v-if="report.is_late"
            class="rounded bg-rose-50 px-1.5 py-0.5 text-[10px] font-semibold text-danger dark:bg-rose-950/40"
          >Trễ</span>
          <span
            v-if="hasFeedback"
            class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-amber-50 text-amber-600 dark:bg-amber-950/40"
            title="Có phản hồi"
          >
            <AppIcon
              name="feedback"
              :size="11"
            />
          </span>
          <div
            v-if="report.score"
            class="inline-flex items-center gap-1"
          >
            <GradePill
              :grade="report.score.grade"
              :color="report.score.grade_color"
            />
            <span class="text-xs font-semibold tabular-nums text-slate-600 dark:text-slate-300">
              {{ Number(report.score.total_score ?? 0).toFixed(1) }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Title -->
    <div class="px-4 pb-2">
      <Link
        :href="`/daily-reports/${report.id}`"
        class="line-clamp-2 text-sm font-medium leading-snug text-slate-800 hover:text-brand dark:text-slate-100"
        :title="report.title"
      >
        {{ report.title }}
      </Link>
    </div>

    <!-- Linked projects + tasks (the only structured "checklist + status") -->
    <div
      v-if="linkedProjects.length"
      class="space-y-2 px-4 pb-2"
    >
      <div
        v-for="p in linkedProjects"
        :key="p.id"
        class="rounded-lg border border-slate-100 bg-slate-50/60 p-2 dark:border-slate-700 dark:bg-slate-800/40"
      >
        <div class="flex items-center gap-1.5">
          <span
            class="h-2.5 w-2.5 shrink-0 rounded-full"
            :style="{ backgroundColor: p.color || '#9A0036' }"
          />
          <span class="truncate text-xs font-semibold text-slate-700 dark:text-slate-200">
            {{ p.name || p.code }}
          </span>
        </div>
        <div
          v-if="p.tasks?.length"
          class="mt-1.5 flex flex-wrap gap-1"
        >
          <span
            v-for="t in p.tasks"
            :key="t.id"
            class="inline-flex items-center gap-1 rounded-full bg-white py-0.5 pl-2 pr-1 text-[11px] text-slate-600 ring-1 ring-slate-200 dark:bg-slate-900 dark:text-slate-300 dark:ring-slate-700"
            :title="t.title"
          >
            <span class="max-w-[9rem] truncate">{{ t.title }}</span>
            <TaskStatusBadge
              v-if="t.id"
              :task-id="t.id"
              :initial-status="t.status || 'todo'"
              :snapshot="report.task_status_snapshot ?? []"
            />
          </span>
        </div>
      </div>
    </div>

    <!-- HORENSO sections (collapsible) -->
    <div
      v-if="sections.length"
      class="px-4"
    >
      <button
        type="button"
        class="flex w-full items-center gap-1.5 border-t border-slate-100 py-2 text-left text-xs font-medium text-slate-500 transition hover:text-brand dark:border-slate-700"
        @click="open = !open"
      >
        <AppIcon
          :name="open ? 'chevron-down' : 'chevron-right'"
          :size="14"
        />
        {{ open ? 'Thu gọn nội dung' : `Xem nội dung báo cáo (${sections.length} mục)` }}
      </button>
      <div
        v-show="open"
        class="space-y-3 pb-2"
      >
        <section
          v-for="s in sections"
          :key="s.key"
        >
          <div class="mb-1 flex items-center gap-1.5">
            <span
              class="h-3 w-1 rounded-full"
              :class="s.accent"
            />
            <h4 class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
              {{ s.label }}
            </h4>
          </div>
          <div
            class="rich-content text-sm text-slate-600 dark:text-slate-300"
            v-html="s.html"
          />
        </section>
      </div>
    </div>

    <!-- Footer -->
    <div class="mt-auto flex items-center gap-1 border-t border-slate-100 px-4 py-2 dark:border-slate-700">
      <Link
        :href="`/daily-reports/${report.id}`"
        class="inline-flex h-8 flex-1 items-center justify-center rounded-btn text-xs font-medium text-brand hover:bg-brand/5"
      >
        Chi tiết
      </Link>
      <button
        v-if="report.can?.delete"
        type="button"
        class="inline-flex h-8 items-center rounded-btn px-2.5 text-xs font-medium text-danger hover:bg-rose-50 dark:hover:bg-rose-950/30"
        @click="emit('delete', report)"
      >
        Xoá
      </button>
    </div>
  </article>
</template>
