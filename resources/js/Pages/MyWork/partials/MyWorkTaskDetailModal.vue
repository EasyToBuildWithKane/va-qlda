<script setup>
/* eslint-disable vue/no-v-html -- mô tả task từ TipTap / markdown đã lưu */
import { computed, ref, watch } from 'vue';
import MarkdownIt from 'markdown-it';
import Modal from '@/Components/Ui/Modal.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import MyWorkToneLabel from './MyWorkToneLabel.vue';
import QuickWorklogPopover from './QuickWorklogPopover.vue';
import { useFixedDropdownAnchor, resolveAnchorElement } from '@/shared/composables/useFixedDropdownAnchor';
import { displayOrEmpty, EMPTY_LABELS } from '@/shared/utils/emptyDisplay';
import { overdueDays, isDueToday, hoursLabel, progressValue, toneDotClass } from '../utils/taskDisplay';

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
    return new Date(`${value}T00:00:00`).toLocaleDateString('vi-VN', {
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

const startLabel = computed(() => fmtDate(props.task?.start_date));
const dueLabel = computed(() => fmtDate(props.task?.due_date));
const workStartedLabel = computed(() => fmtDateTime(props.task?.work_started_at));
const completedLabel = computed(() => fmtDateTime(props.task?.completed_at));
const lateDays = computed(() => overdueDays(props.task));
const dueToday = computed(() => isDueToday(props.task));
const overdue = computed(() => Boolean(props.task?.is_late));
const progress = computed(() => progressValue(props.task));

const progressFillClass = computed(() => {
    if (overdue.value && progress.value < 100) return 'from-rose-500 to-rose-400';
    if (progress.value >= 100) return 'from-emerald-500 to-emerald-400';
    if (progress.value >= 60) return 'from-sky-500 to-brand';
    if (progress.value >= 30) return 'from-amber-400 to-amber-500';
    return 'from-brand to-rose-400';
});

const hoursCaption = computed(() => {
    const t = props.task;
    if (!t) return '';
    const parts = [];
    const actual = hoursLabel(t.actual_hours);
    const estimate = hoursLabel(t.estimate_hours);
    if (actual) parts.push(`${actual} thực tế`);
    if (estimate) parts.push(`${estimate} dự kiến`);
    if (t.logged_today > 0) parts.push(`${t.logged_today}h hôm nay`);
    return parts.join(' · ');
});

const dueCaption = computed(() => {
    if (!dueLabel.value) return 'Chưa chọn hạn';
    if (lateDays.value > 0) return `${dueLabel.value} · quá hạn ${lateDays.value} ngày`;
    if (dueToday.value) return `${dueLabel.value} · đến hạn hôm nay`;
    return dueLabel.value;
});

const descriptionHtml = computed(() => {
    const raw = props.task?.description?.trim();
    if (!raw) return '';
    if (/<[a-z][\s\S]*>/i.test(raw)) return raw;
    return md.render(raw.replace(/\n/g, '  \n'));
});

const labelRows = computed(() => {
    const t = props.task;
    if (!t) return [];
    return [
        { key: 'status', label: 'Trạng thái', value: t.status?.label, tone: t.status, empty: EMPTY_LABELS.notUpdated },
        { key: 'priority', label: 'Ưu tiên', value: t.priority?.label, tone: t.priority, empty: EMPTY_LABELS.notUpdated },
        { key: 'phase', label: 'Giai đoạn', value: t.phase?.label, tone: t.phase, empty: EMPTY_LABELS.notUpdated },
        { key: 'source', label: 'Nguồn', value: t.source?.label, empty: EMPTY_LABELS.notUpdated },
        { key: 'milestone', label: 'Mốc', value: t.is_milestone ? 'Có' : 'Không' },
        { key: 'sla', label: 'SLA', value: t.sla_result?.label, tone: t.sla_result, empty: EMPTY_LABELS.notUpdated },
        { key: 'start', label: 'Ngày bắt đầu', value: startLabel.value, empty: EMPTY_LABELS.notUpdated },
        {
            key: 'due',
            label: 'Hạn hoàn thành',
            value: dueCaption.value,
            empty: EMPTY_LABELS.period,
            late: overdue.value,
        },
        { key: 'estimate', label: 'Giờ dự kiến', value: hoursLabel(t.estimate_hours), empty: EMPTY_LABELS.notUpdated },
        { key: 'actual', label: 'Giờ thực tế', value: hoursLabel(t.actual_hours), empty: EMPTY_LABELS.notUpdated },
        {
            key: 'logged',
            label: 'Giờ hôm nay',
            value: t.logged_today > 0 ? `${t.logged_today}h` : null,
            empty: 'Chưa ghi giờ',
        },
        { key: 'sprint', label: 'Sprint', value: t.sprint?.name ?? null, empty: EMPTY_LABELS.notUpdated },
        { key: 'sp', label: 'Story points', value: t.story_points != null && t.story_points > 0 ? String(t.story_points) : null, empty: EMPTY_LABELS.notUpdated },
        { key: 'epic', label: 'Epic', value: t.epic?.name ?? null, empty: EMPTY_LABELS.notUpdated },
        { key: 'parent', label: 'Việc cha', value: t.parent?.title ?? null, empty: EMPTY_LABELS.notUpdated },
        { key: 'timing', label: 'Kế hoạch giờ', value: t.hours_timing?.label ?? null, tone: t.hours_timing, empty: EMPTY_LABELS.notUpdated },
        { key: 'started', label: 'Bắt tay làm', value: workStartedLabel.value, empty: EMPTY_LABELS.notUpdated },
        { key: 'completed', label: 'Hoàn thành lúc', value: completedLabel.value, empty: EMPTY_LABELS.notUpdated },
    ];
});

const people = computed(() => {
    const t = props.task;
    if (!t) return [];
    return [
        { key: 'assignee', label: 'Phụ trách', person: t.assignee },
        { key: 'reporter', label: 'Người giao', person: t.reporter },
        { key: 'reviewer', label: 'Người duyệt', person: t.reviewer },
    ];
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
      class="flex h-full min-h-0 flex-col gap-4 overflow-y-auto overscroll-contain lg:overflow-hidden"
    >
      <section
        class="relative shrink-0 overflow-hidden rounded-xl border"
        :class="overdue
          ? 'border-rose-200 bg-gradient-to-br from-rose-50 via-white to-white dark:border-rose-900/60 dark:from-rose-950/40 dark:via-slate-900 dark:to-slate-900'
          : 'border-slate-200 bg-gradient-to-br from-slate-50/90 to-white dark:border-slate-700 dark:from-slate-900 dark:to-slate-900'"
      >
        <div
          class="absolute inset-y-0 left-0 w-1.5"
          :style="{ backgroundColor: overdue ? '#e11d48' : projectColor }"
        />

        <div class="flex flex-wrap items-start justify-between gap-3 py-3 pl-5 pr-3 sm:pl-6">
          <div class="min-w-0 flex-1">
            <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">
              Dự án
            </p>
            <p
              class="mt-0.5 truncate text-sm font-semibold text-brand"
              :title="projectLabel || undefined"
            >
              {{ displayOrEmpty(projectLabel, 'Chưa gán dự án') }}
            </p>
            <p
              class="mt-1 text-xs"
              :class="overdue ? 'font-medium text-rose-600' : dueToday ? 'font-medium text-amber-700' : 'text-slate-500'"
            >
              {{ dueCaption }}
            </p>
          </div>

          <div class="flex shrink-0 flex-wrap items-center gap-1.5">
            <div class="relative">
              <button
                ref="statusTriggerRef"
                type="button"
                :disabled="!canChange"
                class="inline-flex max-w-full items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs transition disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-600 dark:bg-slate-800"
                :class="canChange ? 'hover:border-brand/40 hover:bg-brand/5' : ''"
                :title="canChange ? 'Đổi trạng thái' : 'Bạn không có quyền đổi trạng thái việc này'"
                aria-haspopup="listbox"
                :aria-expanded="statusOpen"
                @click="statusOpen = !statusOpen"
              >
                <MyWorkToneLabel
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
                          :class="toneDotClass(opt.color)"
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

        <div class="border-t border-slate-100/80 px-5 py-3 sm:px-6 dark:border-slate-800">
          <div class="flex items-end justify-between gap-3">
            <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">
              Tiến độ
            </p>
            <p
              class="font-display text-2xl leading-none tabular-nums"
              :class="overdue && progress < 100 ? 'text-rose-600' : 'text-slate-800 dark:text-slate-100'"
            >
              {{ progress }}<span class="text-sm font-semibold text-slate-400">%</span>
            </p>
          </div>
          <div
            class="mt-2 h-2.5 overflow-hidden rounded-full bg-slate-100 ring-1 ring-inset ring-slate-200/70 dark:bg-slate-800 dark:ring-slate-700"
            role="progressbar"
            :aria-valuenow="progress"
            aria-valuemin="0"
            aria-valuemax="100"
            :aria-label="`Tiến độ ${progress} phần trăm`"
          >
            <div
              class="h-full rounded-full bg-gradient-to-r transition-all duration-500"
              :class="progressFillClass"
              :style="{ width: progress + '%' }"
            />
          </div>
          <p
            v-if="hoursCaption"
            class="mt-1.5 text-[11px] text-slate-500"
          >
            {{ hoursCaption }}
          </p>
        </div>
      </section>

      <div class="grid min-h-0 flex-1 grid-cols-1 gap-4 lg:grid-cols-2 lg:overflow-hidden">
        <div class="flex min-h-0 flex-col gap-3 overflow-y-auto overscroll-contain pr-0.5">
          <section>
            <h3 class="mb-2 text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">
              Thông tin
            </h3>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-2.5">
              <div
                v-for="row in labelRows"
                :key="row.key"
                class="min-w-0"
              >
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">
                  {{ row.label }}
                </dt>
                <dd
                  class="mt-0.5 min-w-0 text-[13px] font-medium leading-snug"
                  :class="row.late ? 'text-rose-600' : 'text-slate-700 dark:text-slate-200'"
                >
                  <MyWorkToneLabel
                    v-if="row.tone && row.value"
                    :label="row.tone.label"
                    :color="row.tone.color"
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
          </section>

          <section class="rounded-xl border border-slate-100 px-3 py-2.5 dark:border-slate-800">
            <h3 class="mb-2 text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">
              Nhân sự
            </h3>
            <ul class="grid grid-cols-1 gap-2.5 sm:grid-cols-3">
              <li
                v-for="row in people"
                :key="row.key"
                class="flex min-w-0 items-center gap-2"
              >
                <Avatar
                  v-if="row.person"
                  :name="row.person.name"
                  :src="row.person.avatar_path"
                  :size="28"
                />
                <span
                  v-else
                  class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-slate-100 text-[10px] font-semibold text-slate-400 dark:bg-slate-800"
                >?</span>
                <div class="min-w-0">
                  <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">
                    {{ row.label }}
                  </p>
                  <p
                    class="truncate text-[13px] font-medium text-slate-700 dark:text-slate-200"
                    :title="row.person?.name || undefined"
                  >
                    {{ displayOrEmpty(row.person?.name, EMPTY_LABELS.notUpdated) }}
                  </p>
                </div>
              </li>
            </ul>
          </section>
        </div>

        <section class="flex min-h-0 flex-col gap-2">
          <h3 class="shrink-0 text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">
            Mô tả
          </h3>
          <div
            v-if="descriptionHtml"
            class="prose prose-sm min-h-0 max-w-none flex-1 overflow-y-auto overscroll-contain break-words rounded-xl border border-slate-100 bg-white px-3.5 py-3 text-[13px] leading-relaxed text-slate-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200"
            v-html="descriptionHtml"
          />
          <p
            v-else
            class="flex min-h-0 flex-1 items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50/60 px-3 py-6 text-center text-[13px] text-slate-400 dark:border-slate-700 dark:bg-slate-800/40"
          >
            Chưa cập nhật mô tả.
          </p>

          <div
            v-if="task.completion_note?.trim()"
            class="shrink-0 rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-2.5 dark:border-slate-800 dark:bg-slate-800/40"
          >
            <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">
              Ghi chú hoàn thành
            </p>
            <p class="mt-1 whitespace-pre-wrap break-words text-[13px] leading-snug text-slate-700 dark:text-slate-200">
              {{ task.completion_note }}
            </p>
          </div>
        </section>
      </div>
    </div>
  </Modal>
</template>
