<script setup>
import Badge from '@/Components/Project/Badge.vue';
import Avatar from '@/Components/Project/Avatar.vue';
import ProgressBar from '@/Components/Project/ProgressBar.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { date } from '@/composables/useFormat';

defineProps({
    task: { type: Object, required: true },
    draggable: { type: Boolean, default: false },
});

const emit = defineEmits(['click', 'dragstart']);
</script>

<template>
    <div
        class="card cursor-pointer p-3 transition hover:shadow-elevation-2"
        :draggable="draggable"
        @click="emit('click', task)"
        @dragstart="emit('dragstart', task)"
    >
        <div class="mb-1.5 flex items-start justify-between gap-2">
            <p class="text-sm font-medium leading-snug text-slate-800">{{ task.title }}</p>
            <Badge :label="task.priority.label" :color="task.priority.color" />
        </div>

        <ProgressBar :value="task.progress" :height="'h-1.5'" class="my-2" />

        <div class="mt-2 flex items-center justify-between text-xs text-slate-400">
            <span class="flex items-center gap-2">
                <span v-if="task.due_date" class="flex items-center gap-1">
                    <AppIcon name="calendar" :size="13" /> {{ date(task.due_date) }}
                </span>
                <span v-if="task.dependencies?.length" class="flex items-center gap-0.5">
                    <AppIcon name="dependency" :size="13" /> {{ task.dependencies.length }}
                </span>
            </span>
            <Avatar v-if="task.assignee" :name="task.assignee.name" :src="task.assignee.avatar_path" :size="24" />
        </div>
    </div>
</template>
