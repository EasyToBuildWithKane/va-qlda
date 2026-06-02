<script setup>
import { computed, ref } from 'vue';
import TaskCard from '@/Components/Project/TaskCard.vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    tasks: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] }, // [{value,label,color}]
    canEdit: { type: Boolean, default: false },
});

const emit = defineEmits(['move', 'edit', 'add']);

const dragId = ref(null);

const columns = computed(() =>
    props.statuses.map((s) => ({
        ...s,
        tasks: props.tasks.filter((t) => t.status.value === s.value),
    })),
);

const onDrop = (statusValue) => {
    if (dragId.value === null) return;
    const task = props.tasks.find((t) => t.id === dragId.value);
    if (task && task.status.value !== statusValue) {
        emit('move', { id: dragId.value, status: statusValue });
    }
    dragId.value = null;
};
</script>

<template>
    <div class="flex gap-4 overflow-x-auto pb-2">
        <div
            v-for="col in columns"
            :key="col.value"
            class="flex w-72 shrink-0 flex-col rounded-card bg-slate-100/70"
            @dragover.prevent
            @drop="onDrop(col.value)"
        >
            <div class="flex items-center justify-between px-3 py-2.5">
                <span class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                    <span class="h-2 w-2 rounded-full" :class="{
                        'bg-slate-400': col.color === 'slate',
                        'bg-sky-500': col.color === 'sky',
                        'bg-violet-500': col.color === 'violet',
                        'bg-emerald-500': col.color === 'emerald',
                        'bg-rose-500': col.color === 'rose',
                    }"></span>
                    {{ col.label }}
                    <span class="text-xs font-normal text-slate-400">{{ col.tasks.length }}</span>
                </span>
                <button
                    v-if="canEdit"
                    type="button"
                    class="grid h-6 w-6 place-items-center rounded text-slate-400 hover:bg-white hover:text-slate-600"
                    title="Thêm công việc"
                    @click="emit('add', col.value)"
                >
                    <AppIcon name="add" :size="15" />
                </button>
            </div>

            <div class="flex-1 space-y-2 px-2 pb-3">
                <TaskCard
                    v-for="task in col.tasks"
                    :key="task.id"
                    :task="task"
                    :draggable="canEdit"
                    @click="emit('edit', task)"
                    @dragstart="dragId = task.id"
                />
                <p v-if="col.tasks.length === 0" class="px-1 py-6 text-center text-xs text-slate-400">
                    Trống
                </p>
            </div>
        </div>
    </div>
</template>
