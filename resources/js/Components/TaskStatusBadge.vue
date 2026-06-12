<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    taskId: { type: Number, required: true },
    initialStatus: { type: String, required: true },
    snapshot: {
        type: Array,
        default: () => [],
    },
});

const STATUS_STYLES = {
    todo: 'bg-slate-100 text-slate-600',
    in_progress: 'bg-blue-100 text-blue-700',
    in_review: 'bg-violet-100 text-violet-700',
    done: 'bg-green-100 text-green-700',
    blocked: 'bg-red-100 text-red-700',
    cancelled: 'bg-red-100 text-red-700',
};

const STATUS_LABELS = {
    todo: 'Cần làm',
    in_progress: 'Đang làm',
    in_review: 'Đang review',
    done: 'Hoàn thành',
    blocked: 'Bị chặn',
    cancelled: 'Đã hủy',
};

const resolvedStatus = computed(() => {
    const entry = props.snapshot?.find(
        (item) => Number(item?.task_id) === Number(props.taskId),
    );
    if (entry?.status) {
        return String(entry.status);
    }

    return props.initialStatus;
});

const showSyncHint = computed(
    () => resolvedStatus.value !== props.initialStatus,
);

const badgeClass = computed(
    () => STATUS_STYLES[resolvedStatus.value] ?? STATUS_STYLES.todo,
);

const label = computed(
    () => STATUS_LABELS[resolvedStatus.value] ?? resolvedStatus.value,
);
</script>

<template>
  <span class="inline-flex items-center gap-1">
    <span
      class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium"
      :class="badgeClass"
    >
      <AppIcon
        v-if="resolvedStatus === 'done'"
        name="check"
        :size="14"
        class="shrink-0"
      />
      {{ label }}
    </span>
    <span
      v-if="showSyncHint"
      class="inline-flex text-slate-400"
      title="Đã cập nhật từ sprint"
    >
      <AppIcon
        name="refresh"
        :size="12"
        aria-label="Đã cập nhật từ sprint"
      />
    </span>
  </span>
</template>
