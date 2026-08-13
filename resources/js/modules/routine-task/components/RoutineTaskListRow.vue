<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import ProgressBar from '@/shared/ui/ProgressBar.vue';
import { displayOrEmpty, EMPTY_LABELS } from '@/shared/utils/emptyDisplay';
import { useConfirmDelete } from '@/composables/useConfirmClose';
import { hoursLabel, formatClock } from '@/modules/routine-task/composables/useRoutineTasks';

const props = defineProps({
    task: { type: Object, required: true },
    canEdit: { type: Boolean, default: false },
});

const emit = defineEmits(['toggle-status', 'open', 'delete']);

const confirmDelete = useConfirmDelete();

const statusTone = {
    todo: 'bg-slate-100 text-slate-700 ring-slate-200',
    in_progress: 'bg-sky-50 text-sky-800 ring-sky-200',
    done: 'bg-emerald-50 text-emerald-800 ring-emerald-200',
};

const timeRange = computed(() => {
    const start = formatClock(props.task.started_at) || props.task.start_time;
    const end = formatClock(props.task.ended_at) || props.task.end_time;
    if (start && end) return `${start}–${end}`;
    if (start) return `Từ ${start}`;
    return null;
});

const et = computed(() => hoursLabel(props.task.estimate_hours));
const actual = computed(() => hoursLabel(props.task.actual_hours));
const overEt = computed(() => {
    const e = Number(props.task.estimate_hours);
    const a = Number(props.task.actual_hours);
    return Number.isFinite(e) && e > 0 && Number.isFinite(a) && a > e;
});
const fileCount = computed(() => props.task.attachments_count ?? (props.task.attachments?.length ?? 0));
const hasIssue = computed(() => Boolean(props.task.blockers || props.task.risks));

function onDelete(e) {
    e.stopPropagation();
    confirmDelete(
        `Xoá việc «${props.task.title}»?`,
        () => emit('delete'),
        { title: 'Xoá công việc', confirmText: 'Xoá' },
    );
}

function onToggle(e) {
    e.stopPropagation();
    emit('toggle-status');
}

function onActivate() {
    emit('open');
}

function onKeydown(e) {
    if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        onActivate();
    }
}
</script>

<template>
  <article
    class="group flex cursor-pointer flex-wrap items-center gap-3 rounded-xl border border-slate-200/80 bg-white px-3.5 py-3 shadow-sm transition hover:border-brand/30 hover:shadow sm:flex-nowrap"
    :class="{ 'opacity-80': task.status?.value === 'done' }"
    tabindex="0"
    @click="onActivate"
    @keydown="onKeydown"
  >
    <button
      v-if="canEdit"
      type="button"
      class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg ring-1 transition hover:bg-slate-50"
      :class="statusTone[task.status?.value] ?? statusTone.todo"
      :title="`Chuyển trạng thái (${task.status?.label ?? ''})`"
      :aria-label="`Chuyển trạng thái ${task.title}`"
      @click="onToggle"
    >
      <AppIcon
        :name="task.status?.value === 'done' ? 'done' : (task.status?.value === 'in_progress' ? 'sprint' : 'task')"
        :size="15"
      />
    </button>
    <div
      v-else
      class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg ring-1"
      :class="statusTone[task.status?.value] ?? statusTone.todo"
    >
      <AppIcon
        :name="task.status?.value === 'done' ? 'done' : (task.status?.value === 'in_progress' ? 'sprint' : 'task')"
        :size="15"
      />
    </div>

    <div class="w-24 shrink-0">
      <p class="text-xs font-semibold tabular-nums text-slate-700">
        {{ displayOrEmpty(timeRange, 'Chưa ghi giờ') }}
      </p>
    </div>

    <div class="min-w-0 flex-1 basis-full sm:basis-auto">
      <p
        class="truncate text-sm font-semibold text-slate-800 group-hover:text-brand"
        :class="{ 'line-through text-slate-500': task.status?.value === 'done' }"
        :title="task.title"
      >
        {{ task.title }}
      </p>
      <p
        v-if="task.description"
        class="mt-0.5 line-clamp-1 text-xs text-slate-500"
      >
        {{ task.description }}
      </p>
      <div class="mt-1 flex flex-wrap items-center gap-1.5">
        <span
          class="inline-flex items-center rounded-md px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide ring-1"
          :class="statusTone[task.status?.value] ?? statusTone.todo"
        >
          {{ task.status?.label ?? 'Chưa cập nhật' }}
        </span>
        <span
          v-if="hasIssue"
          class="inline-flex items-center gap-0.5 rounded-md bg-amber-50 px-1.5 py-0.5 text-[10px] font-semibold text-amber-800 ring-1 ring-amber-200"
        >
          <AppIcon
            name="alert"
            :size="10"
          />
          Vướng / rủi ro
        </span>
        <span
          v-if="fileCount > 0"
          class="inline-flex items-center gap-0.5 rounded-md bg-slate-50 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600 ring-1 ring-slate-200"
        >
          <AppIcon
            name="documents"
            :size="10"
          />
          {{ fileCount }} tệp
        </span>
      </div>
    </div>

    <div class="w-full min-w-[8rem] sm:w-36 sm:shrink-0">
      <ProgressBar
        :value="task.progress_percent ?? 0"
        height="h-1.5"
      />
    </div>

    <div class="w-28 shrink-0 text-right">
      <p class="text-[11px] font-semibold tabular-nums text-slate-600">
        ET {{ displayOrEmpty(et, EMPTY_LABELS.notUpdated) }}
      </p>
      <p
        class="text-[11px] tabular-nums"
        :class="overEt ? 'font-semibold text-amber-700' : 'text-slate-500'"
      >
        TH {{ displayOrEmpty(actual, EMPTY_LABELS.notUpdated) }}
      </p>
    </div>

    <button
      v-if="canEdit"
      type="button"
      class="rounded-lg p-1.5 text-slate-400 opacity-0 transition hover:bg-rose-50 hover:text-rose-600 group-hover:opacity-100"
      :aria-label="`Xoá ${task.title}`"
      @click="onDelete"
    >
      <AppIcon
        name="trash"
        :size="14"
      />
    </button>
  </article>
</template>
