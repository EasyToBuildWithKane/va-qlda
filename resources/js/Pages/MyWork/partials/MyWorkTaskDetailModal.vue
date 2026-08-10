<script setup>
/* eslint-disable vue/no-v-html -- mô tả task từ TipTap / markdown đã lưu */
import { computed, ref, watch } from 'vue';
import MarkdownIt from 'markdown-it';
import Modal from '@/Components/Ui/Modal.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import QuickWorklogPopover from './QuickWorklogPopover.vue';
import { useFixedDropdownAnchor, resolveAnchorElement } from '@/shared/composables/useFixedDropdownAnchor';
import { displayOrEmpty, EMPTY_LABELS } from '@/shared/utils/emptyDisplay';

const props = defineProps({
    open: { type: Boolean, default: false },
    task: { type: Object, default: null },
    mode: { type: String, default: 'self' },
    statusOptions: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'change-status', 'log-work', 'open-project']);

const statusOpen = ref(false);
const worklogOpen = ref(false);
const statusTriggerRef = ref(null);
const worklogTriggerRef = ref(null);

const { panelStyle: statusPanelStyle } = useFixedDropdownAnchor(
    () => resolveAnchorElement(statusTriggerRef),
    statusOpen,
    { width: 168, zIndex: 130, preferDown: true, maxHeight: 280 },
);

const md = new MarkdownIt({ linkify: true, breaks: true });

const dotClass = {
    slate: 'bg-slate-400',
    sky: 'bg-sky-500',
    violet: 'bg-violet-500',
    emerald: 'bg-emerald-500',
    rose: 'bg-rose-500',
    amber: 'bg-amber-500',
};

watch(() => props.open, (v) => {
    if (!v) {
        statusOpen.value = false;
        worklogOpen.value = false;
    }
});

const canChange = computed(
    () => (props.task?.can?.contribute || props.task?.can?.act_team) && props.task?.can_change_status,
);
const canLog = computed(() => props.mode === 'self' && props.task?.can?.contribute);

const projectLabel = computed(() => {
    const p = props.task?.project;
    if (!p) return null;
    if (p.code && p.name) return `${p.code} · ${p.name}`;
    return p.name || p.code || null;
});

const projectColor = computed(() => props.task?.project?.color || '#9A0036');

function fmtDate(value) {
    if (!value) return null;
    return new Date(value + 'T00:00:00').toLocaleDateString('vi-VN', {
        day: '2-digit', month: '2-digit', year: 'numeric',
    });
}

function fmtDateTime(value) {
    if (!value) return null;
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return null;
    return d.toLocaleString('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function fmtHours(h) {
    if (h == null || h <= 0) return null;
    return `${Number.isInteger(h) ? h : Number(h).toFixed(1)}h`;
}

const startLabel = computed(() => fmtDate(props.task?.start_date));
const dueLabel = computed(() => fmtDate(props.task?.due_date));
const workStartedLabel = computed(() => fmtDateTime(props.task?.work_started_at));
const completedLabel = computed(() => fmtDateTime(props.task?.completed_at));

const overdueDays = computed(() => {
    if (!props.task?.is_late || !props.task?.due_date) return 0;
    const due = new Date(props.task.due_date + 'T00:00:00');
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return Math.max(0, Math.round((today - due) / 86400000));
});

const isDueToday = computed(() => {
    if (!props.task?.due_date) return false;
    const due = new Date(props.task.due_date + 'T00:00:00');
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return due.getTime() === today.getTime();
});

const progress = computed(() => {
    const p = Number(props.task?.progress ?? 0);
    return Number.isFinite(p) ? Math.min(100, Math.max(0, p)) : 0;
});

const descriptionHtml = computed(() => {
    const raw = props.task?.description?.trim();
    if (!raw) return '';
    if (/<[a-z][\s\S]*>/i.test(raw)) return raw;
    if (/^#{1,6}\s|^\*\*|^-\s|^\d+\.\s/m.test(raw) || raw.includes('```')) {
        return md.render(raw);
    }
    return md.render(raw.replace(/\n/g, '  \n'));
});

/** Meta 2 cột trái — denser chip grid */
const metaRows = computed(() => {
    const t = props.task;
    if (!t) return [];
    const sp = t.story_points;
    return [
        { key: 'start', label: 'Bắt đầu', value: startLabel.value, empty: EMPTY_LABELS.notUpdated },
        {
            key: 'due',
            label: 'Hạn hoàn thành',
            value: dueLabel.value,
            empty: EMPTY_LABELS.periodUnset,
            late: t.is_late,
        },
        { key: 'started', label: 'Bắt tay làm', value: workStartedLabel.value, empty: EMPTY_LABELS.notUpdated },
        { key: 'completed', label: 'Hoàn thành', value: completedLabel.value, empty: EMPTY_LABELS.notUpdated },
        { key: 'estimate', label: 'Giờ dự kiến', value: fmtHours(t.estimate_hours), empty: EMPTY_LABELS.notUpdated },
        { key: 'actual', label: 'Giờ thực tế', value: fmtHours(t.actual_hours), empty: EMPTY_LABELS.notUpdated },
        {
            key: 'logged',
            label: 'Giờ hôm nay',
            value: t.logged_today > 0 ? `${t.logged_today}h` : null,
            empty: 'Chưa ghi giờ',
        },
        {
            key: 'sp',
            label: 'Story points',
            value: sp != null && sp > 0 ? String(sp) : null,
            empty: EMPTY_LABELS.notUpdated,
        },
        { key: 'sprint', label: 'Sprint', value: t.sprint?.name ?? null, empty: EMPTY_LABELS.notUpdated },
        { key: 'epic', label: 'Epic', value: t.epic?.name ?? null, empty: EMPTY_LABELS.notUpdated },
        { key: 'phase', label: 'Giai đoạn', value: t.phase?.label ?? null, empty: EMPTY_LABELS.notUpdated, badge: t.phase },
        { key: 'priority', label: 'Ưu tiên', value: t.priority?.label ?? null, empty: EMPTY_LABELS.notUpdated, badge: t.priority },
        { key: 'parent', label: 'Việc cha', value: t.parent?.title ?? null, empty: EMPTY_LABELS.notUpdated },
        { key: 'source', label: 'Nguồn', value: t.source?.label ?? null, empty: EMPTY_LABELS.notUpdated },
        { key: 'milestone', label: 'Mốc', value: t.is_milestone ? 'Có' : 'Không' },
        {
            key: 'sla',
            label: 'SLA',
            value: t.sla_result?.label ?? null,
            empty: EMPTY_LABELS.notUpdated,
            badge: t.sla_result,
        },
        {
            key: 'timing',
            label: 'Kế hoạch giờ',
            value: t.hours_timing?.label ?? null,
            empty: EMPTY_LABELS.notUpdated,
            badge: t.hours_timing,
        },
    ];
});

const people = computed(() => {
    const t = props.task;
    if (!t) return [];
    return [
        { key: 'assignee', label: 'Phụ trách', person: t.assignee },
        { key: 'reporter', label: 'Người giao', person: t.reporter },
        { key: 'reviewer', label: 'Reviewer', person: t.reviewer },
    ].filter((row) => row.person);
});

function pickStatus(value) {
    statusOpen.value = false;
    if (!props.task || value === props.task.status?.value) return;
    emit('change-status', props.task, value);
}

function onLog(payload) {
    worklogOpen.value = false;
    if (!props.task) return;
    emit('log-work', props.task, payload);
}
</script>

<template>
  <Modal
    :show="open && !!task"
    :title="task?.title || 'Chi tiết công việc'"
    max-width="max-w-5xl"
    fit-viewport
    @close="emit('close')"
  >
    <div
      v-if="task"
      class="flex h-full min-h-0 flex-col gap-3"
    >
      <!-- Header compact: dự án + badge + actions -->
      <div
        class="relative shrink-0 overflow-hidden rounded-lg border border-slate-200 bg-gradient-to-br from-slate-50/90 to-white dark:border-slate-700 dark:from-slate-900 dark:to-slate-900"
      >
        <div
          class="absolute inset-y-0 left-0 w-1"
          :style="{ backgroundColor: projectColor }"
        />
        <div class="flex flex-wrap items-center gap-x-3 gap-y-2 py-2.5 pl-3.5 pr-3 sm:pl-4">
          <div class="min-w-0 flex-1">
            <p
              v-if="projectLabel"
              class="truncate text-[12px] font-semibold text-brand"
              :title="projectLabel"
            >
              {{ projectLabel }}
            </p>
            <div class="mt-1 flex flex-wrap items-center gap-1.5">
              <span
                v-if="overdueDays > 0"
                class="inline-flex items-center gap-1 rounded-full bg-rose-100 px-2 py-0.5 text-[11px] font-semibold text-rose-700 dark:bg-rose-950/50 dark:text-rose-300"
              >
                <AppIcon
                  name="alert"
                  :size="11"
                  class="shrink-0"
                />
                Quá hạn {{ overdueDays }} ngày
              </span>
              <span
                v-else-if="isDueToday"
                class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-700 dark:bg-amber-950/50 dark:text-amber-300"
              >
                <AppIcon
                  name="calendar-clock"
                  :size="11"
                  class="shrink-0"
                />
                Đến hạn hôm nay
              </span>
              <Badge
                v-if="task.priority"
                :label="task.priority.label"
                :color="task.priority.color"
              />
              <Badge
                v-if="task.phase"
                :label="task.phase.label"
                :color="task.phase.color"
              />
              <Badge
                v-if="task.is_milestone"
                label="Mốc"
                color="amber"
              />
              <Badge
                v-if="task.sla_result"
                :label="task.sla_result.label"
                :color="task.sla_result.color"
              />
            </div>
          </div>

          <div class="flex shrink-0 flex-wrap items-center gap-1.5">
            <div class="relative">
              <button
                ref="statusTriggerRef"
                type="button"
                :disabled="!canChange"
                class="inline-flex max-w-full items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs transition disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-600 dark:bg-slate-800"
                :class="canChange ? 'hover:border-brand/40 hover:bg-brand/5' : ''"
                :title="canChange ? 'Đổi trạng thái' : 'Bạn không có quyền đổi trạng thái việc này'"
                aria-haspopup="listbox"
                :aria-expanded="statusOpen"
                @click="statusOpen = !statusOpen"
              >
                <Badge
                  v-if="task.status"
                  :label="task.status.label"
                  :color="task.status.color"
                />
                <AppIcon
                  v-if="canChange"
                  name="chevron-down"
                  :size="12"
                  class="shrink-0"
                />
              </button>
              <Teleport to="body">
                <button
                  v-if="statusOpen"
                  type="button"
                  class="fixed inset-0 z-[120] cursor-default bg-transparent"
                  aria-label="Đóng"
                  @click="statusOpen = false"
                />
                <div
                  v-if="statusOpen"
                  :style="statusPanelStyle"
                  class="rounded-xl border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-slate-900"
                  role="listbox"
                >
                  <ul class="max-h-[inherit] overflow-y-auto">
                    <li
                      v-for="opt in statusOptions"
                      :key="opt.value"
                    >
                      <button
                        type="button"
                        class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-xs hover:bg-slate-50 dark:hover:bg-slate-800"
                        :class="opt.value === task.status?.value ? 'font-semibold' : ''"
                        role="option"
                        :aria-selected="opt.value === task.status?.value"
                        @click="pickStatus(opt.value)"
                      >
                        <span
                          class="h-2 w-2 shrink-0 rounded-full"
                          :class="dotClass[opt.color] || dotClass.slate"
                        />
                        <span class="min-w-0 break-words">{{ opt.label }}</span>
                      </button>
                    </li>
                  </ul>
                </div>
              </Teleport>
            </div>

            <div
              v-if="canLog"
              class="relative"
            >
              <button
                ref="worklogTriggerRef"
                type="button"
                class="btn-ghost inline-flex h-8 items-center gap-1 px-2.5 text-xs"
                title="Ghi giờ nhanh"
                @click="worklogOpen = !worklogOpen"
              >
                <AppIcon
                  name="worklog"
                  :size="13"
                />
                Ghi giờ
              </button>
              <QuickWorklogPopover
                :open="worklogOpen"
                :anchor-ref="worklogTriggerRef"
                :task-title="task.title"
                @submit="onLog"
                @close="worklogOpen = false"
              />
            </div>
            <button
              type="button"
              class="btn-primary inline-flex h-8 items-center gap-1 px-2.5 text-xs"
              @click="emit('open-project', task)"
            >
              Mở dự án
              <AppIcon
                name="external-link"
                :size="12"
              />
            </button>
          </div>
        </div>

        <div class="flex items-center gap-2 border-t border-slate-100 px-3.5 py-1.5 sm:px-4 dark:border-slate-800">
          <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Tiến độ</span>
          <div class="h-1.5 min-w-0 flex-1 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
            <div
              class="h-full rounded-full bg-brand transition-all"
              :style="{ width: progress + '%' }"
            />
          </div>
          <span class="shrink-0 text-[11px] font-semibold tabular-nums text-slate-600 dark:text-slate-300">{{ progress }}%</span>
        </div>
      </div>

      <!-- Body 5–5: meta | mô tả — chỉ mô tả dài mới cuộn nội bộ -->
      <div class="grid min-h-0 flex-1 grid-cols-1 gap-3 lg:grid-cols-2 lg:gap-4">
        <!-- Cột trái -->
        <div class="flex min-h-0 flex-col gap-2.5 overflow-y-auto overscroll-contain pr-0.5 lg:overflow-hidden">
          <dl class="grid shrink-0 grid-cols-2 gap-1.5">
            <div
              v-for="row in metaRows"
              :key="row.key"
              class="rounded-lg border border-slate-100 bg-slate-50/80 px-2.5 py-1.5 dark:border-slate-800 dark:bg-slate-800/40"
            >
              <dt class="text-[9px] font-semibold uppercase tracking-wide text-slate-400">
                {{ row.label }}
              </dt>
              <dd
                class="mt-0.5 min-w-0 text-[12px] font-medium leading-snug"
                :class="row.late ? 'text-rose-600' : 'text-slate-700 dark:text-slate-200'"
              >
                <Badge
                  v-if="row.badge && row.value"
                  :label="row.badge.label"
                  :color="row.badge.color"
                />
                <span
                  v-else
                  class="line-clamp-2 break-words"
                  :title="row.value || undefined"
                >
                  {{ displayOrEmpty(row.value, row.empty || EMPTY_LABELS.notUpdated) }}
                </span>
              </dd>
            </div>
          </dl>

          <div
            v-if="people.length"
            class="shrink-0 rounded-lg border border-slate-100 px-2.5 py-2 dark:border-slate-800"
          >
            <p class="mb-1.5 text-[9px] font-semibold uppercase tracking-wide text-slate-400">
              Nhân sự
            </p>
            <ul class="flex flex-wrap gap-x-3 gap-y-1.5">
              <li
                v-for="row in people"
                :key="row.key"
                class="flex min-w-0 items-center gap-1.5"
              >
                <Avatar
                  :name="row.person.name"
                  :src="row.person.avatar_path"
                  :size="24"
                />
                <div class="min-w-0">
                  <p class="text-[9px] font-semibold uppercase tracking-wide text-slate-400">
                    {{ row.label }}
                  </p>
                  <p
                    class="truncate text-[12px] font-medium text-slate-700 dark:text-slate-200"
                    :title="row.person.name"
                  >
                    {{ row.person.name }}
                  </p>
                </div>
              </li>
            </ul>
          </div>
          <div
            v-else
            class="shrink-0 rounded-lg border border-dashed border-slate-200 px-2.5 py-2 text-[12px] text-slate-400 dark:border-slate-700"
          >
            {{ displayOrEmpty(null, 'Chưa gán đơn vị') }}
          </div>
        </div>

        <!-- Cột phải -->
        <section class="flex min-h-0 flex-col gap-2">
          <div class="flex shrink-0 items-center justify-between gap-2">
            <h3 class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">
              Mô tả
            </h3>
            <span
              v-if="task.source?.label"
              class="truncate text-[11px] text-slate-400"
            >{{ task.source.label }}</span>
          </div>
          <div
            v-if="descriptionHtml"
            class="prose prose-sm min-h-0 max-w-none flex-1 overflow-y-auto overscroll-contain break-words rounded-lg border border-slate-100 bg-white px-3 py-2.5 text-[13px] leading-relaxed text-slate-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200"
            v-html="descriptionHtml"
          />
          <p
            v-else
            class="flex min-h-0 flex-1 items-center justify-center rounded-lg border border-dashed border-slate-200 bg-slate-50/60 px-3 py-6 text-center text-[13px] text-slate-400 dark:border-slate-700 dark:bg-slate-800/40"
          >
            Chưa cập nhật mô tả.
          </p>

          <div
            v-if="task.completion_note?.trim()"
            class="shrink-0 rounded-lg border border-slate-100 bg-slate-50/80 px-2.5 py-2 dark:border-slate-800 dark:bg-slate-800/40"
          >
            <p class="text-[9px] font-semibold uppercase tracking-wide text-slate-400">
              Ghi chú hoàn thành
            </p>
            <p class="mt-0.5 line-clamp-3 whitespace-pre-wrap break-words text-[12px] leading-snug text-slate-700 dark:text-slate-200">
              {{ task.completion_note }}
            </p>
          </div>
        </section>
      </div>
    </div>
  </Modal>
</template>
