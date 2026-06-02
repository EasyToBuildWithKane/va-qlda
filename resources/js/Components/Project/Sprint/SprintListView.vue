<script setup>
import { ref } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/Components/Project/Badge.vue';
import ProgressBar from '@/Components/Project/ProgressBar.vue';
import SprintTaskRows from '@/Components/Project/Sprint/SprintTaskRows.vue';
import { date } from '@/composables/useFormat';
import { groupTasksByPhase } from '@/composables/useTaskPhaseGroups';
import { countTaskTree } from '@/composables/useTaskHierarchy';

const props = defineProps({
    sprints: { type: Array, default: () => [] },
    tasksBySprint: { type: Object, default: () => ({}) },
    backlogTasks: { type: Array, default: () => [] },
    allTasks: { type: Array, default: () => [] },
    sprintMetrics: { type: Function, required: true },
    expandedIds: { type: Set, required: true },
    projectId: { type: Number, required: true },
    statusOptions: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
    canContribute: { type: Boolean, default: false },
});

const emit = defineEmits(['toggle-sprint', 'open-sprint', 'open-task', 'add-task', 'duplicate-sprint', 'close-sprint', 'delete-sprint']);

/** Thu gọn nhóm giai đoạn (mặc định mở hết). */
const collapsedPhases = ref(new Set());

const phaseKey = (scopeId, phaseId) => `${scopeId}:phase-${phaseId}`;

const isPhaseOpen = (key) => !collapsedPhases.value.has(key);

const togglePhase = (key) => {
    const s = new Set(collapsedPhases.value);
    if (s.has(key)) s.delete(key);
    else s.add(key);
    collapsedPhases.value = s;
};
</script>

<template>
    <div class="space-y-2">
        <div
            v-for="s in sprints"
            :key="s.id"
            class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900"
        >
            <div
                class="flex cursor-pointer items-center gap-3 px-4 py-3 transition hover:bg-slate-50/80 dark:hover:bg-slate-800/50"
                @click="emit('toggle-sprint', s.id)"
            >
                <AppIcon :name="expandedIds.has(s.id) ? 'chevron-down' : 'chevron-right'" :size="16" class="shrink-0 text-slate-400" />
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-display font-semibold text-slate-800 dark:text-slate-100">{{ s.name }}</span>
                        <Badge :label="s.status?.label" :color="s.status?.color" />
                        <span class="text-xs text-slate-400">{{ date(s.start_date) }} → {{ date(s.end_date) }}</span>
                        <span
                            v-if="sprintMetrics(s.id).lateCount"
                            class="rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-semibold text-rose-700 dark:bg-rose-950/50 dark:text-rose-300"
                        >
                            {{ sprintMetrics(s.id).lateCount }} trễ
                        </span>
                    </div>
                    <p v-if="s.goal" class="mt-0.5 truncate text-xs text-slate-500">{{ s.goal }}</p>
                </div>
                <div class="flex shrink-0 items-center gap-4 text-xs text-slate-500">
                    <span>{{ sprintMetrics(s.id).taskCount }} task</span>
                    <span>Vel {{ sprintMetrics(s.id).velocity }}h</span>
                    <div class="w-24">
                        <ProgressBar :value="sprintMetrics(s.id).progress" />
                    </div>
                </div>
                <span v-if="canManage" class="flex gap-0.5" @click.stop>
                    <button type="button" class="grid h-7 w-7 place-items-center rounded text-slate-400 hover:bg-slate-100" title="Sửa" @click="emit('open-sprint', s)"><AppIcon name="edit" :size="14" /></button>
                    <button type="button" class="grid h-7 w-7 place-items-center rounded text-slate-400 hover:bg-slate-100" title="Nhân bản" @click="emit('duplicate-sprint', s)"><AppIcon name="copy" :size="14" /></button>
                    <button type="button" class="grid h-7 w-7 place-items-center rounded text-slate-400 hover:bg-slate-100" title="Đóng sprint" @click="emit('close-sprint', s)"><AppIcon name="check" :size="14" /></button>
                    <button type="button" class="grid h-7 w-7 place-items-center rounded text-slate-400 hover:bg-rose-50 hover:text-rose-600" title="Xoá" @click="emit('delete-sprint', s)"><AppIcon name="delete" :size="14" /></button>
                </span>
            </div>
            <div v-if="expandedIds.has(s.id)" class="border-t border-slate-100 dark:border-slate-800">
                <div v-if="!(tasksBySprint[s.id] || []).length" class="px-2 py-4 text-center text-sm text-slate-400">
                    Chưa có task.
                    <button v-if="canManage" type="button" class="text-brand hover:underline" @click="emit('add-task', { sprintId: s.id })">Thêm task</button>
                </div>
                <div v-else class="space-y-1 px-1 py-2">
                    <div
                        v-for="ph in groupTasksByPhase(tasksBySprint[s.id])"
                        :key="ph.key"
                        class="overflow-hidden rounded-lg border border-violet-100/80 dark:border-violet-900/40"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center gap-2 bg-violet-50/80 px-3 py-2 text-left transition hover:bg-violet-50 dark:bg-violet-950/30 dark:hover:bg-violet-950/50"
                            @click="togglePhase(phaseKey(s.id, ph.key))"
                        >
                            <AppIcon
                                :name="isPhaseOpen(phaseKey(s.id, ph.key)) ? 'chevron-down' : 'chevron-right'"
                                :size="14"
                                class="shrink-0 text-violet-400"
                            />
                            <span class="min-w-0 flex-1 text-xs font-semibold text-violet-900 dark:text-violet-200">{{ ph.label }}</span>
                            <span class="shrink-0 rounded-full bg-violet-100 px-1.5 py-0.5 text-[10px] font-medium text-violet-700 dark:bg-violet-900/50 dark:text-violet-300">
                                {{ countTaskTree(ph.tasks, allTasks.length ? allTasks : tasksBySprint[s.id]) }}
                            </span>
                        </button>
                        <div v-if="isPhaseOpen(phaseKey(s.id, ph.key))" class="border-t border-violet-100/80 dark:border-violet-900/40">
                            <SprintTaskRows
                                :tasks="ph.tasks"
                                :all-tasks="allTasks"
                                :project-id="projectId"
                                :status-options="statusOptions"
                                :can-contribute="canContribute"
                                @open-task="emit('open-task', $event)"
                            />
                        </div>
                    </div>
                    <div v-if="canManage" class="px-2 pb-1">
                        <button type="button" class="text-xs text-brand hover:underline" @click="emit('add-task', { sprintId: s.id })">+ Thêm task</button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="backlogTasks.length" class="overflow-hidden rounded-xl border border-dashed border-slate-300 bg-slate-50/50 dark:border-slate-600 dark:bg-slate-900/50">
            <div class="flex cursor-pointer items-center gap-3 px-4 py-3" @click="emit('toggle-sprint', 'backlog')">
                <AppIcon :name="expandedIds.has('backlog') ? 'chevron-down' : 'chevron-right'" :size="16" class="text-slate-400" />
                <span class="font-semibold text-slate-600 dark:text-slate-300">Backlog</span>
                <span class="text-xs text-slate-400">{{ backlogTasks.length }} task</span>
            </div>
            <div v-if="expandedIds.has('backlog')" class="space-y-1 border-t border-slate-200 px-1 py-2 dark:border-slate-700">
                <div
                    v-for="ph in groupTasksByPhase(backlogTasks)"
                    :key="ph.key"
                    class="overflow-hidden rounded-lg border border-violet-100/80 dark:border-violet-900/40"
                >
                    <button
                        type="button"
                        class="flex w-full items-center gap-2 bg-violet-50/80 px-3 py-2 text-left transition hover:bg-violet-50 dark:bg-violet-950/30"
                        @click="togglePhase(phaseKey('backlog', ph.key))"
                    >
                        <AppIcon
                            :name="isPhaseOpen(phaseKey('backlog', ph.key)) ? 'chevron-down' : 'chevron-right'"
                            :size="14"
                            class="shrink-0 text-violet-400"
                        />
                        <span class="flex-1 text-xs font-semibold text-violet-900 dark:text-violet-200">{{ ph.label }}</span>
                        <span class="rounded-full bg-violet-100 px-1.5 py-0.5 text-[10px] font-medium text-violet-700 dark:bg-violet-900/50 dark:text-violet-300">
                            {{ countTaskTree(ph.tasks, allTasks.length ? allTasks : backlogTasks) }}
                        </span>
                    </button>
                    <div v-if="isPhaseOpen(phaseKey('backlog', ph.key))" class="border-t border-violet-100/80 dark:border-violet-900/40">
                        <SprintTaskRows
                            :tasks="ph.tasks"
                            :all-tasks="allTasks"
                            :project-id="projectId"
                            :status-options="statusOptions"
                            :can-contribute="canContribute"
                            compact
                            @open-task="emit('open-task', $event)"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
