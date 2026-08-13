<script setup>
import { ref, computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import Badge from '@/shared/ui/Badge.vue';
import ProgressBar from '@/shared/ui/ProgressBar.vue';
import { date, datetime } from '@/composables/useFormat';
import { taskDisplayId } from '@/composables/useTaskWorkspace';
import { displayOrEmpty, EMPTY_LABELS } from '@/shared/utils/emptyDisplay.js';

const props = defineProps({
    task: { type: Object, required: true },
    project: { type: Object, default: null },
    projectName: { type: String, default: '' },
    projectCode: { type: String, default: '' },
    assignees: { type: Array, default: () => [] },
    watchers: { type: Array, default: () => [] },
    progressPct: { type: Number, default: 0 },
    isOverdue: { type: Boolean, default: false },
    sprintLine: { type: String, default: '' },
    hoursLine: { type: String, default: '' },
});

const emit = defineEmits(['open-task']);

const expanded = ref(true);

const projectLine = computed(() => {
    const code = (props.projectCode || props.project?.code || '').trim();
    const name = (props.projectName || props.project?.name || '').trim();
    if (code && name) return `${code} · ${name}`;
    return name || code || '';
});

const expectedRange = computed(() => formatDateRange(props.task?.start_date, props.task?.due_date));

const actualRange = computed(() => {
    const start = props.task?.work_started_at || null;
    const end = props.task?.completed_at || null;
    if (!start && !end) return '';
    if (start && end) return `${formatAnyDate(start)} - ${formatAnyDate(end)}`;
    if (start) return `Từ ${formatAnyDate(start)}`;
    return `Đến ${formatAnyDate(end)}`;
});

const parentLine = computed(() => {
    const parent = props.task?.parent;
    if (!parent?.id) return '';
    return `${taskDisplayId(parent)} · ${parent.title || ''}`.trim();
});

function formatDateRange(from, to) {
    if (!from && !to) return '';
    if (from && to) return `${date(from)} - ${date(to)}`;
    if (from) return `Từ ${date(from)}`;
    return `Đến ${date(to)}`;
}

function formatAnyDate(value) {
    if (!value) return '';
    if (typeof value === 'string' && value.includes('T')) return datetime(value);
    return date(value);
}
</script>

<template>
  <section class="rounded-xl border border-slate-200/80 bg-white dark:border-slate-700 dark:bg-slate-950">
    <button
      type="button"
      class="flex w-full items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 text-left dark:border-slate-800"
      :aria-expanded="expanded"
      @click="expanded = !expanded"
    >
      <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">
        Thông tin chung
      </h3>
      <AppIcon
        name="chevron-down"
        :size="16"
        class="shrink-0 text-slate-400 transition-transform"
        :class="expanded ? 'rotate-180' : ''"
      />
    </button>

    <div
      v-show="expanded"
      class="px-4 py-4"
    >
      <div class="grid gap-x-10 gap-y-5 sm:grid-cols-2">
        <!-- Cột trái -->
        <div class="space-y-5">
          <div>
            <p class="text-[11px] font-medium text-slate-400">
              Tên công việc
            </p>
            <p class="mt-1 text-sm font-medium leading-snug text-slate-800 dark:text-slate-100">
              {{ displayOrEmpty(task.title, EMPTY_LABELS.notUpdated) }}
            </p>
          </div>

          <div>
            <p class="text-[11px] font-medium text-slate-400">
              Dự án
            </p>
            <p
              v-if="projectLine"
              class="mt-1 text-sm font-medium text-brand"
            >
              {{ projectLine }}
            </p>
            <p
              v-else
              class="mt-1 text-sm italic text-slate-400"
            >
              {{ EMPTY_LABELS.notUpdated }}
            </p>
          </div>

          <div>
            <p class="text-[11px] font-medium text-slate-400">
              Mã công việc
            </p>
            <p class="mt-1 font-mono text-sm font-semibold tracking-tight text-slate-800 dark:text-slate-100">
              {{ taskDisplayId(task) }}
            </p>
          </div>

          <div>
            <p class="text-[11px] font-medium text-slate-400">
              Tiến độ
            </p>
            <div class="mt-2 flex items-center gap-3">
              <div class="min-w-0 flex-1">
                <ProgressBar
                  :value="progressPct"
                  :show-label="false"
                  height="h-2"
                />
              </div>
              <span
                class="inline-flex h-8 min-w-[2.5rem] shrink-0 items-center justify-center rounded-full px-2 text-xs font-bold tabular-nums text-white shadow-sm"
                :class="progressPct >= 100 ? 'bg-emerald-500' : 'bg-sky-500'"
              >
                {{ progressPct }}%
              </span>
            </div>
          </div>

          <div>
            <p class="text-[11px] font-medium text-slate-400">
              Thời gian thực tế
            </p>
            <p
              class="mt-1 text-sm tabular-nums"
              :class="actualRange ? 'text-slate-800 dark:text-slate-100' : 'italic text-slate-400'"
            >
              {{ actualRange || 'Chưa ghi nhận' }}
            </p>
          </div>

          <div>
            <p class="text-[11px] font-medium text-slate-400">
              Người thực hiện
            </p>
            <div
              v-if="assignees.length"
              class="mt-1.5 flex flex-wrap gap-1.5"
            >
              <span
                v-for="a in assignees"
                :key="a.id"
                class="inline-flex max-w-full items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 py-0.5 pl-0.5 pr-2.5 text-sm text-slate-800 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
              >
                <Avatar
                  :name="a.name"
                  :src="a.avatar_path"
                  :size="22"
                />
                <span class="truncate">{{ a.name }}</span>
              </span>
            </div>
            <p
              v-else
              class="mt-1 text-sm italic text-slate-400"
            >
              Chưa gán
            </p>
          </div>

          <div>
            <p class="text-[11px] font-medium text-slate-400">
              Loại công việc
            </p>
            <p
              class="mt-1 text-sm"
              :class="task.phase?.label ? 'text-slate-800 dark:text-slate-100' : 'italic text-slate-400'"
            >
              {{ displayOrEmpty(task.phase?.label, 'Chưa phân loại') }}
            </p>
          </div>
        </div>

        <!-- Cột phải -->
        <div class="space-y-5">
          <div>
            <p class="text-[11px] font-medium text-slate-400">
              Công việc cha
            </p>
            <button
              v-if="task.parent?.id"
              type="button"
              class="mt-1 inline-flex max-w-full items-center gap-1.5 text-left text-sm font-medium text-brand hover:underline"
              @click="emit('open-task', task.parent)"
            >
              <span class="min-w-0 truncate">{{ parentLine }}</span>
              <AppIcon
                name="external-link"
                :size="13"
                class="shrink-0 opacity-70"
              />
            </button>
            <p
              v-else
              class="mt-1 text-sm italic text-slate-400"
            >
              Không có công việc cha
            </p>
          </div>

          <div>
            <p class="text-[11px] font-medium text-slate-400">
              Trạng thái
            </p>
            <div class="mt-1.5">
              <Badge
                v-if="task.status?.label"
                :label="task.status.label"
                :color="task.status.color || 'slate'"
              />
              <span
                v-else
                class="text-sm italic text-slate-400"
              >{{ EMPTY_LABELS.notUpdated }}</span>
            </div>
          </div>

          <div>
            <p class="text-[11px] font-medium text-slate-400">
              Thời gian dự kiến
            </p>
            <p
              class="mt-1 text-sm tabular-nums"
              :class="[
                expectedRange ? 'text-slate-800 dark:text-slate-100' : 'italic text-slate-400',
                isOverdue ? 'font-medium text-rose-600 dark:text-rose-400' : '',
              ]"
            >
              {{ expectedRange || 'Chưa chọn kỳ' }}
              <span
                v-if="expectedRange && isOverdue"
                class="ml-1 text-xs font-semibold"
              >· Quá hạn</span>
            </p>
          </div>

          <div>
            <p class="text-[11px] font-medium text-slate-400">
              Người giao việc
            </p>
            <div
              v-if="task.reporter"
              class="mt-1.5 flex items-center gap-2"
            >
              <Avatar
                :name="task.reporter.name"
                :src="task.reporter.avatar_path"
                :size="28"
              />
              <span class="min-w-0 truncate text-sm font-medium text-slate-800 dark:text-slate-100">
                {{ task.reporter.name }}
              </span>
            </div>
            <p
              v-else
              class="mt-1 text-sm italic text-slate-400"
            >
              Chưa cập nhật
            </p>
          </div>

          <div>
            <p class="text-[11px] font-medium text-slate-400">
              Theo dõi / phối hợp thực hiện
            </p>
            <div
              v-if="watchers.length"
              class="mt-1.5 flex flex-wrap gap-1.5"
            >
              <span
                v-for="w in watchers"
                :key="w.id"
                class="inline-flex max-w-full items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 py-0.5 pl-0.5 pr-2.5 text-sm text-slate-800 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
              >
                <Avatar
                  :name="w.name"
                  :src="w.avatar_path"
                  :size="22"
                />
                <span class="truncate">{{ w.name }}</span>
              </span>
            </div>
            <p
              v-else
              class="mt-1 text-sm italic text-slate-400"
            >
              Chưa có người theo dõi
            </p>
          </div>

          <div>
            <p class="text-[11px] font-medium text-slate-400">
              Ưu tiên
            </p>
            <div
              v-if="task.priority?.label"
              class="mt-1.5 inline-flex items-center gap-1.5 text-sm font-medium text-slate-800 dark:text-slate-100"
            >
              <AppIcon
                name="flag"
                :size="14"
                class="shrink-0"
                :class="{
                  'text-rose-500': task.priority?.color === 'rose',
                  'text-amber-500': task.priority?.color === 'amber',
                  'text-sky-500': task.priority?.color === 'sky',
                  'text-slate-400': !['rose', 'amber', 'sky'].includes(task.priority?.color),
                }"
              />
              {{ task.priority.label }}
            </div>
            <p
              v-else
              class="mt-1 text-sm italic text-slate-400"
            >
              {{ EMPTY_LABELS.notUpdated }}
            </p>
          </div>
        </div>
      </div>

      <!-- Meta phụ: sprint / epic / giờ -->
      <dl class="mt-5 grid gap-3 border-t border-slate-100 pt-4 text-xs sm:grid-cols-3 dark:border-slate-800">
        <div>
          <dt class="font-medium text-slate-400">
            Sprint
          </dt>
          <dd class="mt-0.5 text-slate-700 dark:text-slate-300">
            {{ displayOrEmpty(sprintLine || task.sprint?.name, 'Backlog') }}
          </dd>
        </div>
        <div>
          <dt class="font-medium text-slate-400">
            Epic
          </dt>
          <dd class="mt-0.5 text-slate-700 dark:text-slate-300">
            {{ displayOrEmpty(task.epic?.name, EMPTY_LABELS.notUpdated) }}
          </dd>
        </div>
        <div>
          <dt class="font-medium text-slate-400">
            Giờ làm
          </dt>
          <dd class="mt-0.5 tabular-nums text-slate-700 dark:text-slate-300">
            {{ displayOrEmpty(hoursLine, EMPTY_LABELS.notUpdated) }}
          </dd>
        </div>
      </dl>
    </div>
  </section>
</template>
