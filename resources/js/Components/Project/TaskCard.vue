<script setup>
import Badge from '@/Components/Project/Badge.vue';
import Avatar from '@/Components/Project/Avatar.vue';
import ProgressBar from '@/Components/Project/ProgressBar.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { date } from '@/composables/useFormat';
import { computed } from 'vue';

const props = defineProps({
    task: { type: Object, required: true },
    draggable: { type: Boolean, default: false },
    /** { total, done, hours } — hiển thị gọn trên Kanban, không list con. */
    subtasks: { type: Object, default: null },
});

const emit = defineEmits(['click', 'dragstart']);

// Merge assignees: multi-assignees list takes precedence over single assignee_id
const allAssignees = computed(() => {
    if (props.task.assignees?.length) return props.task.assignees;
    if (props.task.assignee) return [props.task.assignee];
    return [];
});
</script>

<template>
    <div
        class="card cursor-pointer p-3 transition hover:shadow-elevation-2"
        :draggable="draggable"
        @click="emit('click', task)"
        @dragstart="emit('dragstart', task)"
    >
        <div class="mb-1.5 flex items-start justify-between gap-2">
            <p class="text-sm font-medium leading-snug text-slate-800 dark:text-slate-100">{{ task.title }}</p>
            <Badge :label="task.priority.label" :color="task.priority.color" />
        </div>

        <div
            v-if="subtasks?.total"
            class="mb-1.5 flex flex-wrap items-center gap-1.5 text-[10px] text-slate-500"
            title="Công việc con — mở chi tiết để xem"
        >
            <span class="inline-flex items-center gap-0.5 rounded-full bg-slate-100 px-1.5 py-0.5 dark:bg-slate-800">
                <AppIcon name="task" :size="10" />
                {{ subtasks.done }}/{{ subtasks.total }} con
            </span>
            <span v-if="subtasks.hours" class="text-brand">{{ subtasks.hours }}h</span>
        </div>

        <ProgressBar :value="task.progress" :height="'h-1.5'" class="my-2" />

        <!-- Reporter / reviewer labels -->
        <div v-if="task.reporter || task.reviewer" class="mb-2 flex flex-wrap gap-1.5 text-xs text-slate-400">
            <span v-if="task.reporter" class="flex items-center gap-1">
                <AppIcon name="user-check" :size="12" />
                <span>Giao: {{ task.reporter.name.split(' ').pop() }}</span>
            </span>
            <span v-if="task.reviewer" class="flex items-center gap-1 text-violet-500">
                <AppIcon name="check" :size="12" />
                <span>Duyệt: {{ task.reviewer.name.split(' ').pop() }}</span>
            </span>
        </div>

        <div class="mt-2 flex items-center justify-between text-xs text-slate-400">
            <span class="flex items-center gap-2">
                <span v-if="task.due_date" class="flex items-center gap-1">
                    <AppIcon name="calendar" :size="13" /> {{ date(task.due_date) }}
                </span>
                <span v-if="task.dependencies?.length" class="flex items-center gap-0.5">
                    <AppIcon name="dependency" :size="13" /> {{ task.dependencies.length }}
                </span>
            </span>

            <!-- Multiple assignee avatars -->
            <div v-if="allAssignees.length" class="flex items-center">
                <span
                    v-for="(a, i) in allAssignees.slice(0, 4)"
                    :key="a.id"
                    :style="{ marginLeft: i === 0 ? '0' : '-6px', zIndex: 10 - i }"
                    class="relative inline-block rounded-full ring-2 ring-white"
                    :title="a.name"
                >
                    <Avatar :name="a.name" :src="a.avatar_path" :size="24" />
                </span>
                <span
                    v-if="allAssignees.length > 4"
                    class="relative ml-[-6px] inline-flex h-6 w-6 items-center justify-center rounded-full bg-slate-200 text-[10px] font-semibold text-slate-600 ring-2 ring-white"
                >
                    +{{ allAssignees.length - 4 }}
                </span>
            </div>
        </div>
    </div>
</template>
