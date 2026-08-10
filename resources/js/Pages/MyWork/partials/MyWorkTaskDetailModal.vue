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

const startLabel = computed(() => fmtDate(props.task?.start_date));
const dueLabel = computed(() => fmtDate(props.task?.due_date));

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

const estimateLabel = computed(() => {
    const h = props.task?.estimate_hours;
    if (h == null || h <= 0) return null;
    return `${Number.isInteger(h) ? h : h.toFixed(1)}h`;
});

const actualLabel = computed(() => {
    const h = props.task?.actual_hours;
    if (h == null || h <= 0) return null;
    return `${Number.isInteger(h) ? h : h.toFixed(1)}h`;
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

const metaRows = computed(() => {
    const t = props.task;
    if (!t) return [];
    return [
        { key: 'start', label: 'Bắt đầu', value: startLabel.value, empty: 'Chưa cập nhật' },
        { key: 'due', label: 'Hạn hoàn thành', value: dueLabel.value, empty: 'Chưa chọn kỳ', late: t.is_late },
        { key: 'estimate', label: 'Giờ dự kiến', value: estimateLabel.value, empty: 'Chưa cập nhật' },
        { key: 'actual', label: 'Giờ thực tế', value: actualLabel.value, empty: 'Chưa cập nhật' },
        {
            key: 'logged',
            label: 'Giờ hôm nay',
            value: t.logged_today > 0 ? `${t.logged_today}h` : null,
            empty: 'Chưa ghi giờ',
        },
        {
            key: 'sprint',
            label: 'Sprint',
            value: t.sprint?.name ?? null,
            empty: 'Chưa cập nhật',
        },
        {
            key: 'phase',
            label: 'Giai đoạn',
            value: t.phase?.label ?? null,
            empty: 'Chưa cập nhật',
        },
        {
            key: 'priority',
            label: 'Ưu tiên',
            value: t.priority?.label ?? null,
            empty: 'Chưa cập nhật',
            badge: t.priority,
        },
        {
            key: 'milestone',
            label: 'Mốc',
            value: t.is_milestone ? 'Có' : 'Không',
        },
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
    @close="emit('close')"
  >
    <div
      v-if="task"
      class="-mt-1"
    >
      <!-- Accent + header -->
      <div class="relative overflow-hidden rounded-xl border border-slate-200 bg-gradient-to-br from-slate-50/90 to-white dark:border-slate-700 dark:from-slate-900 dark:to-slate-900">
        <div
          class="absolute inset-y-0 left-0 w-1.5"
          :style="{ backgroundColor: projectColor }"
        />
        <div class="px-4 py-4 pl-5 sm:px-6">
          <div class="flex flex-wrap items-start gap-4">
            <div class="min-w-0 flex-1">
              <p
                v-if="projectLabel"
                class="truncate text-[11px] font-semibold uppercase tracking-wide text-brand"
                :title="projectLabel"
              >
                {{ projectLabel }}
              </p>
              <div class="mt-2 flex flex-wrap items-center gap-2">
                <span
                  v-if="overdueDays > 0"
                  class="inline-flex items-center gap-1 rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-semibold text-rose-700 dark:bg-rose-950/50 dark:text-rose-300"
                >
                  <AppIcon
                    name="alert"
                    :size="12"
                  />
                  Quá hạn {{ overdueDays }} ngày
                </span>
                <span
                  v-else-if="isDueToday"
                  class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-950/50 dark:text-amber-300"
                >
                  <AppIcon
                    name="calendar-clock"
                    :size="12"
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
                  v-if="task.sprint"
                  :label="task.sprint.name"
                  color="slate"
                />
                <Badge
                  v-if="task.is_milestone"
                  label="Mốc"
                  color="amber"
                />
              </div>
            </div>

            <div class="flex shrink-0 flex-col items-stretch gap-2 sm:items-end">
              <div class="relative self-start sm:self-end">
                <button
                  ref="statusTriggerRef"
                  type="button"
                  :disabled="!canChange"
                  class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs transition disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-600 dark:bg-slate-800"
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
                    :size="13"
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
                          {{ opt.label }}
                        </button>
                      </li>
                    </ul>
                  </div>
                </Teleport>
              </div>

              <div class="flex flex-wrap items-center gap-1.5">
                <div
                  v-if="canLog"
                  class="relative"
                >
                  <button
                    ref="worklogTriggerRef"
                    type="button"
                    class="btn-ghost inline-flex h-9 items-center gap-1.5 px-3 text-xs"
                    title="Ghi giờ nhanh"
                    @click="worklogOpen = !worklogOpen"
                  >
                    <AppIcon
                      name="worklog"
                      :size="14"
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
                  class="btn-primary inline-flex h-9 items-center gap-1.5 px-3 text-xs"
                  @click="emit('open-project', task)"
                >
                  Mở trong dự án
                  <AppIcon
                    name="external-link"
                    :size="13"
                  />
                </button>
              </div>
            </div>
          </div>

          <!-- Progress -->
          <div class="mt-4">
            <div class="mb-1.5 flex items-center justify-between text-[11px]">
              <span class="font-medium uppercase tracking-wide text-slate-400">Tiến độ</span>
              <span class="font-semibold tabular-nums text-slate-600 dark:text-slate-300">{{ progress }}%</span>
            </div>
            <div class="h-2.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
              <div
                class="h-full rounded-full bg-brand transition-all"
                :style="{ width: progress + '%' }"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Body: meta + mô tả 2 cột trên màn rộng -->
      <div class="mt-5 grid grid-cols-1 gap-5 lg:grid-cols-12">
        <div class="space-y-4 lg:col-span-5">
          <dl class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-2 xl:grid-cols-3">
            <div
              v-for="row in metaRows"
              :key="row.key"
              class="rounded-xl border border-slate-100 bg-slate-50/70 px-3 py-2.5 dark:border-slate-800 dark:bg-slate-800/40"
            >
              <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">
                {{ row.label }}
              </dt>
              <dd
                class="mt-0.5 truncate text-sm font-medium"
                :class="row.late ? 'text-rose-600' : 'text-slate-700 dark:text-slate-200'"
              >
                <Badge
                  v-if="row.badge"
                  :label="row.badge.label"
                  :color="row.badge.color"
                />
                <template v-else>
                  {{ displayOrEmpty(row.value, row.empty || EMPTY_LABELS.notUpdated) }}
                </template>
              </dd>
            </div>
          </dl>

          <div
            v-if="task.assignee"
            class="flex items-center gap-3 rounded-xl border border-slate-100 px-3.5 py-3 dark:border-slate-800"
          >
            <Avatar
              :name="task.assignee.name"
              :src="task.assignee.avatar_path"
              :size="40"
            />
            <div class="min-w-0">
              <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">
                Người phụ trách
              </p>
              <p class="truncate text-sm font-medium text-slate-700 dark:text-slate-200">
                {{ task.assignee.name }}
              </p>
            </div>
          </div>
        </div>

        <section class="lg:col-span-7">
          <h3 class="mb-2 text-[10px] font-semibold uppercase tracking-wide text-slate-400">
            Mô tả
          </h3>
          <div
            v-if="descriptionHtml"
            class="prose prose-sm max-w-none rounded-xl border border-slate-100 bg-white px-4 py-3.5 text-slate-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200"
            v-html="descriptionHtml"
          />
          <p
            v-else
            class="rounded-xl border border-dashed border-slate-200 bg-slate-50/60 px-4 py-10 text-center text-sm text-slate-400 dark:border-slate-700 dark:bg-slate-800/40"
          >
            Chưa cập nhật mô tả.
          </p>
        </section>
      </div>
    </div>
  </Modal>
</template>
