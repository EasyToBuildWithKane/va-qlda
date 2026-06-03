<script setup>
import Avatar from '@/shared/ui/Avatar.vue';
import { date, datetime } from '@/composables/useFormat';

defineProps({
    blocker: { type: Object, required: true },
});

const SEVERITY_LABEL_CLASS = {
    critical: 'text-rose-700 font-semibold',
    high: 'text-rose-600 font-medium',
    medium: 'text-amber-700',
    low: 'text-slate-600',
};

function severityLabelClass(value) {
    return SEVERITY_LABEL_CLASS[value] ?? 'text-slate-600';
}

function textOrDash(value) {
    const t = (value ?? '').trim();
    return t || null;
}
</script>

<template>
  <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-4 dark:border-slate-700 dark:bg-slate-800/40">
    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200/80 pb-3 dark:border-slate-700">
      <div class="min-w-0 flex-1">
        <p class="font-mono text-xs font-semibold text-brand">
          {{ blocker.code }}
        </p>
        <p class="mt-0.5 font-display text-base font-semibold text-slate-800 dark:text-slate-100">
          {{ blocker.title }}
        </p>
      </div>
      <div class="flex flex-wrap gap-2 text-xs">
        <span
          v-if="blocker.severity"
          :class="severityLabelClass(blocker.severity.value)"
        >
          {{ blocker.severity.label }}
        </span>
        <span
          v-if="blocker.status"
          class="text-slate-500"
        >
          · {{ blocker.status.label }}
        </span>
      </div>
    </div>

    <dl class="mt-3 grid gap-x-4 gap-y-2 text-sm sm:grid-cols-2 lg:grid-cols-3">
      <div v-if="blocker.project?.name">
        <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-400">
          Dự án
        </dt>
        <dd class="mt-0.5 text-slate-700 dark:text-slate-200">
          {{ blocker.project.code ? `${blocker.project.name} (${blocker.project.code})` : blocker.project.name }}
        </dd>
      </div>
      <div v-else-if="!blocker.project_id">
        <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-400">
          Nhóm
        </dt>
        <dd class="mt-0.5 text-slate-600">
          Thắc mắc chung
        </dd>
      </div>
      <div v-if="blocker.task?.title">
        <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-400">
          Công việc
        </dt>
        <dd class="mt-0.5 text-slate-700 dark:text-slate-200">
          {{ blocker.task.title }}
        </dd>
      </div>
      <div>
        <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-400">
          Ngày báo
        </dt>
        <dd class="mt-0.5 tabular-nums text-slate-600">
          {{ blocker.raised_at ? date(blocker.raised_at) : '—' }}
          <span
            v-if="blocker.is_overdue"
            class="ml-1 font-medium text-rose-600"
          >· Quá hạn</span>
        </dd>
      </div>
      <div>
        <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-400">
          Hạn xử lý
        </dt>
        <dd
          class="mt-0.5 tabular-nums"
          :class="blocker.is_overdue ? 'font-semibold text-rose-600' : 'text-slate-600'"
        >
          {{ blocker.due_date ? date(blocker.due_date) : '—' }}
        </dd>
      </div>
      <div v-if="blocker.raised_by?.name">
        <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-400">
          Người báo
        </dt>
        <dd class="mt-0.5 flex items-center gap-1.5">
          <Avatar
            :name="blocker.raised_by.name"
            :src="blocker.raised_by.avatar_path"
            :size="20"
          />
          <span class="text-slate-700 dark:text-slate-200">{{ blocker.raised_by.name }}</span>
        </dd>
      </div>
      <div>
        <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-400">
          Người xử lý
        </dt>
        <dd class="mt-0.5 flex items-center gap-1.5">
          <template v-if="blocker.owner?.name">
            <Avatar
              :name="blocker.owner.name"
              :src="blocker.owner.avatar_path"
              :size="20"
            />
            <span class="text-slate-700 dark:text-slate-200">{{ blocker.owner.name }}</span>
          </template>
          <span
            v-else
            class="text-slate-400"
          >—</span>
        </dd>
      </div>
      <div v-if="blocker.resolved_at">
        <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-400">
          Đã xử lý xong
        </dt>
        <dd class="mt-0.5 tabular-nums text-slate-600">
          {{ datetime(blocker.resolved_at) }}
        </dd>
      </div>
    </dl>

    <div
      v-if="textOrDash(blocker.description)"
      class="mt-4"
    >
      <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
        Mô tả chi tiết
      </p>
      <p class="mt-1.5 whitespace-pre-wrap text-sm leading-relaxed text-slate-700 dark:text-slate-300">
        {{ blocker.description }}
      </p>
    </div>

    <div
      v-if="textOrDash(blocker.root_cause)"
      class="mt-4"
    >
      <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
        Nguyên nhân
      </p>
      <p class="mt-1.5 whitespace-pre-wrap text-sm leading-relaxed text-slate-700 dark:text-slate-300">
        {{ blocker.root_cause }}
      </p>
    </div>

    <div
      v-if="textOrDash(blocker.resolution)"
      class="mt-4 rounded-lg border border-dashed border-slate-200 bg-white/60 p-3 dark:border-slate-600 dark:bg-slate-900/40"
    >
      <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
        Hướng xử lý đã ghi
      </p>
      <p class="mt-1.5 whitespace-pre-wrap text-sm leading-relaxed text-slate-600 dark:text-slate-400">
        {{ blocker.resolution }}
      </p>
    </div>

    <p
      v-if="!textOrDash(blocker.description) && !textOrDash(blocker.root_cause)"
      class="mt-3 text-xs italic text-slate-400"
    >
      Chưa có mô tả hoặc nguyên nhân — dựa vào tiêu đề và bối cảnh dự án khi ghi hướng xử lý.
    </p>
  </div>
</template>
