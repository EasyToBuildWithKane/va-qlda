<script setup>
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/Components/Project/Badge.vue';
import Avatar from '@/Components/Project/Avatar.vue';
import ProgressBar from '@/Components/Project/ProgressBar.vue';
import { currency } from '@/composables/useFormat';

defineProps({
    project: { type: Object, required: true },
    draggable: { type: Boolean, default: false },
    showType: { type: Boolean, default: false },
    showDepartment: { type: Boolean, default: false },
});

const emit = defineEmits(['remove', 'dragstart']);

const stripe = {
    brand: 'bg-brand', sky: 'bg-sky-500', emerald: 'bg-emerald-500', violet: 'bg-violet-500',
    amber: 'bg-amber-500', rose: 'bg-rose-500', cyan: 'bg-cyan-500', slate: 'bg-slate-400',
};
</script>

<template>
    <div
        class="card overflow-hidden transition hover:shadow-elevation-2"
        :class="draggable ? 'cursor-grab active:cursor-grabbing' : ''"
        :draggable="draggable"
        @dragstart="emit('dragstart', project)"
    >
        <div class="h-1.5" :class="stripe[project.color] || stripe.slate"></div>
        <div class="p-4">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="font-mono text-xs text-slate-400">{{ project.code }}</p>
                    <Link :href="`/projects/${project.id}`" class="block truncate font-display font-semibold text-slate-800 hover:text-brand">{{ project.name }}</Link>
                </div>
                <Badge v-if="project.status" :label="project.status.label" :color="project.status.color" />
            </div>

            <div v-if="(showType && project.type) || (showDepartment && project.department)" class="mt-2 flex flex-wrap gap-1">
                <Badge v-if="showType && project.type" :label="project.type.label" :color="project.type.color" />
                <Badge v-if="showDepartment && project.department" :label="project.department.name" :color="project.department.color" />
            </div>

            <div class="my-3">
                <ProgressBar :value="project.progress" />
            </div>

            <div class="flex items-center justify-between text-xs text-slate-500">
                <span class="flex items-center gap-3">
                    <span class="flex items-center gap-1"><AppIcon name="members" :size="14" /> {{ project.member_count ?? 0 }}</span>
                    <span class="flex items-center gap-1"><AppIcon name="task" :size="14" /> {{ project.task_count ?? 0 }}</span>
                    <span v-if="project.open_blocker_count" class="flex items-center gap-1 text-rose-500"><AppIcon name="blockers" :size="14" /> {{ project.open_blocker_count }}</span>
                </span>
                <Avatar v-if="project.manager" :name="project.manager.name" :src="project.manager.avatar_path" :size="24" />
            </div>

            <div class="mt-3 flex items-center justify-between border-t border-slate-100 pt-3">
                <span class="text-xs text-slate-500">
                    <span class="font-medium text-slate-700">{{ currency(project.labor_cost) }}</span>
                    <span v-if="project.budget"> / {{ currency(project.budget) }}</span>
                </span>
                <span class="flex gap-1">
                    <Link v-if="project.can?.update" :href="`/projects/${project.id}/edit`" class="grid h-7 w-7 place-items-center rounded text-slate-400 hover:bg-slate-100 hover:text-slate-700" title="Sửa">
                        <AppIcon name="edit" :size="15" />
                    </Link>
                    <button v-if="project.can?.delete" class="grid h-7 w-7 place-items-center rounded text-slate-400 hover:bg-rose-50 hover:text-rose-600" title="Xoá" @click="emit('remove', project)">
                        <AppIcon name="delete" :size="15" />
                    </button>
                </span>
            </div>
        </div>
    </div>
</template>
