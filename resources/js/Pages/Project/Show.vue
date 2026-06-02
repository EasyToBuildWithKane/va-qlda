<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/Components/Project/Badge.vue';
import Avatar from '@/Components/Project/Avatar.vue';
import ProgressBar from '@/Components/Project/ProgressBar.vue';
import GanttChart from '@/Components/Project/GanttChart.vue';
import TaskBoard from '@/Components/Project/TaskBoard.vue';
import CostSummary from '@/Components/Project/CostSummary.vue';
import TaskFormModal from '@/Components/Project/TaskFormModal.vue';
import SprintFormModal from '@/Components/Project/SprintFormModal.vue';
import MemberFormModal from '@/Components/Project/MemberFormModal.vue';
import WorklogFormModal from '@/Components/Project/WorklogFormModal.vue';
import BlockerFormModal from '@/Components/Project/BlockerFormModal.vue';
import { currency, date, hours } from '@/composables/useFormat';
import { useDialog } from '@/composables/useDialog';

const props = defineProps({
    project: { type: Object, required: true },
    sprints: { type: Array, default: () => [] },
    tasks: { type: Array, default: () => [] },
    blockers: { type: Array, default: () => [] },
    worklogs: { type: Array, default: () => [] },
    cost: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({ employees: [], enums: {} }) },
});

const dialog = useDialog();
const enums = computed(() => props.options.enums || {});
const canManage = computed(() => props.project.can?.manage);
const canContribute = computed(() => props.project.can?.contribute);

const tabs = [
    { key: 'overview', label: 'Tổng quan', icon: 'overview' },
    { key: 'timeline', label: 'Tiến độ (Gantt)', icon: 'timeline' },
    { key: 'board', label: 'Bảng công việc', icon: 'board' },
    { key: 'sprints', label: 'Sprint', icon: 'sprint' },
    { key: 'members', label: 'Thành viên & Lương', icon: 'members' },
    { key: 'cost', label: 'Chi phí', icon: 'cost' },
    { key: 'blockers', label: 'Vướng mắc', icon: 'blockers' },
];
const tab = ref('overview');

// --- Modal state ---
const taskModal = ref(false);
const editingTask = ref(null);
const taskDefaultStatus = ref('todo');
const sprintModal = ref(false);
const editingSprint = ref(null);
const memberModal = ref(false);
const editingMember = ref(null);
const worklogModal = ref(false);
const worklogTask = ref(null);
const blockerModal = ref(false);
const editingBlocker = ref(null);

const pid = props.project.id;

const openTask = (t = null, status = 'todo') => { editingTask.value = t; taskDefaultStatus.value = status; taskModal.value = true; };
const openSprint = (s = null) => { editingSprint.value = s; sprintModal.value = true; };
const openMember = (m = null) => { editingMember.value = m; memberModal.value = true; };
const openWorklog = (t) => { worklogTask.value = t; worklogModal.value = true; };
const openBlocker = (b = null) => { editingBlocker.value = b; blockerModal.value = true; };

// --- Gantt / board actions ---
const onGanttDate = ({ id, start, end }) => router.put(`/projects/${pid}/tasks/${id}`, { start_date: start, due_date: end }, { preserveScroll: true, preserveState: false });
const onGanttProgress = ({ id, progress }) => router.patch(`/projects/${pid}/tasks/${id}`, { progress }, { preserveScroll: true });
const onBoardMove = ({ id, status }) => router.patch(`/projects/${pid}/tasks/${id}`, { status }, { preserveScroll: true });

const removeTask = async (t) => {
    if (await dialog.confirm({ title: 'Xoá công việc', message: `Xoá "${t.title}"?`, tone: 'danger', confirmText: 'Xoá' }))
        router.delete(`/projects/${pid}/tasks/${t.id}`, { preserveScroll: true });
};
const removeSprint = async (s) => {
    if (await dialog.confirm({ title: 'Xoá sprint', message: `Xoá "${s.name}"?`, tone: 'danger', confirmText: 'Xoá' }))
        router.delete(`/projects/${pid}/sprints/${s.id}`, { preserveScroll: true });
};
const removeMember = async (m) => {
    if (await dialog.confirm({ title: 'Gỡ thành viên', message: `Gỡ ${m.name} khỏi dự án?`, tone: 'danger', confirmText: 'Gỡ' }))
        router.delete(`/projects/${pid}/members/${m.id}`, { preserveScroll: true });
};
const removeWorklog = async (w) => {
    if (await dialog.confirm({ title: 'Xoá giờ làm', message: 'Xoá bản ghi giờ làm này?', tone: 'danger', confirmText: 'Xoá' }))
        router.delete(`/projects/${pid}/tasks/${w.task_id}/worklogs/${w.id}`, { preserveScroll: true });
};
const removeBlocker = async (b) => {
    if (await dialog.confirm({ title: 'Xoá vướng mắc', message: `Xoá "${b.title}"?`, tone: 'danger', confirmText: 'Xoá' }))
        router.delete(`/blockers/${b.id}`, { preserveScroll: true });
};

const stripe = {
    brand: 'bg-brand', sky: 'bg-sky-500', emerald: 'bg-emerald-500', violet: 'bg-violet-500',
    amber: 'bg-amber-500', rose: 'bg-rose-500', cyan: 'bg-cyan-500', slate: 'bg-slate-400',
};
</script>

<template>
    <Head :title="project.name" />
    <AppLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <Link href="/projects" class="grid h-8 w-8 place-items-center rounded-btn text-slate-400 hover:bg-slate-100">
                    <AppIcon name="back" :size="18" />
                </Link>
                <span class="h-3 w-3 rounded-full" :class="stripe[project.color] || stripe.slate"></span>
                <h1 class="font-display font-semibold text-slate-800">{{ project.name }}</h1>
                <Badge v-if="project.department" :label="project.department.name" :color="project.department.color" />
                <Badge v-if="project.type" :label="project.type.label" :color="project.type.color" />
                <Badge v-if="project.status" :label="project.status.label" :color="project.status.color" />
                <Link v-if="project.can?.update" :href="`/projects/${project.id}/edit`" class="btn-ghost ml-2 text-sm">
                    <AppIcon name="edit" :size="15" /> Sửa
                </Link>
            </div>
        </template>

        <!-- Tabs -->
        <div class="mb-5 flex flex-wrap gap-1 border-b border-slate-200">
            <button
                v-for="t in tabs"
                :key="t.key"
                class="flex items-center gap-1.5 border-b-2 px-3 py-2 text-sm font-medium transition"
                :class="tab === t.key ? 'border-brand text-brand' : 'border-transparent text-slate-500 hover:text-slate-700'"
                @click="tab = t.key"
            >
                <AppIcon :name="t.icon" :size="16" /> {{ t.label }}
            </button>
        </div>

        <!-- ===== Overview ===== -->
        <div v-show="tab === 'overview'" class="space-y-5">
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <div class="card p-4">
                    <p class="text-sm text-slate-500">Tiến độ</p>
                    <ProgressBar :value="project.progress" class="mt-2" />
                </div>
                <div class="card p-4"><p class="text-sm text-slate-500">Thành viên</p><p class="mt-1 font-display text-2xl font-bold text-brand">{{ project.members?.length ?? 0 }}</p></div>
                <div class="card p-4"><p class="text-sm text-slate-500">Công việc</p><p class="mt-1 font-display text-2xl font-bold text-slate-800">{{ tasks.length }}</p></div>
                <div class="card p-4"><p class="text-sm text-slate-500">Vướng mắc mở</p><p class="mt-1 font-display text-2xl font-bold text-rose-500">{{ blockers.filter(b => b.status.value !== 'resolved').length }}</p></div>
            </div>

            <div class="grid gap-5 lg:grid-cols-3">
                <div class="card p-5 lg:col-span-2">
                    <h2 class="mb-2 font-display font-semibold text-slate-800">Mô tả</h2>
                    <p class="whitespace-pre-wrap text-sm leading-relaxed text-slate-600">{{ project.description || 'Chưa có mô tả.' }}</p>
                    <div class="mt-4 grid grid-cols-2 gap-4 border-t border-slate-100 pt-4 text-sm sm:grid-cols-4">
                        <div><p class="text-slate-400">Bắt đầu</p><p class="font-medium text-slate-700">{{ date(project.start_date) }}</p></div>
                        <div><p class="text-slate-400">Hạn</p><p class="font-medium text-slate-700">{{ date(project.due_date) }}</p></div>
                        <div><p class="text-slate-400">Quản lý</p><p class="font-medium text-slate-700">{{ project.manager?.name || '—' }}</p></div>
                        <div><p class="text-slate-400">Phòng ban</p><p class="font-medium text-slate-700">{{ project.department?.name || '—' }}</p></div>
                        <div><p class="text-slate-400">Mã</p><p class="font-mono text-slate-700">{{ project.code }}</p></div>
                    </div>
                </div>
                <div class="card p-5">
                    <h2 class="mb-3 font-display font-semibold text-slate-800">Đội ngũ</h2>
                    <ul class="space-y-2">
                        <li v-for="m in project.members" :key="m.id" class="flex items-center gap-2">
                            <Avatar :name="m.name" :src="m.avatar_path" :size="30" />
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-slate-700">{{ m.name }}</p>
                                <p class="text-xs text-slate-400">{{ m.project_role }}</p>
                            </div>
                        </li>
                        <li v-if="!project.members?.length" class="text-sm text-slate-400">Chưa có thành viên.</li>
                    </ul>
                </div>
            </div>

            <div class="card p-5">
                <h2 class="mb-4 font-display font-semibold text-slate-800">Ngân sách & Chi phí</h2>
                <CostSummary :cost="cost" />
            </div>
        </div>

        <!-- ===== Timeline (Gantt) ===== -->
        <div v-show="tab === 'timeline'" class="card p-5">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="font-display font-semibold text-slate-800">Lộ trình công việc</h2>
                <button v-if="canManage" class="btn-primary text-sm" @click="openTask()"><AppIcon name="add" :size="15" /> Công việc</button>
            </div>
            <GanttChart
                :tasks="tasks"
                :editable="canContribute"
                @date-change="onGanttDate"
                @progress-change="onGanttProgress"
                @select="(id) => openTask(tasks.find(t => t.id === id))"
            />
        </div>

        <!-- ===== Board ===== -->
        <div v-show="tab === 'board'">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="font-display font-semibold text-slate-800">Bảng Kanban</h2>
                <button v-if="canManage" class="btn-primary text-sm" @click="openTask()"><AppIcon name="add" :size="15" /> Công việc</button>
            </div>
            <TaskBoard
                :tasks="tasks"
                :statuses="enums.taskStatus || []"
                :can-edit="canContribute"
                @move="onBoardMove"
                @edit="(t) => openTask(t)"
                @add="(status) => openTask(null, status)"
            />
        </div>

        <!-- ===== Sprints ===== -->
        <div v-show="tab === 'sprints'" class="space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="font-display font-semibold text-slate-800">Sprint</h2>
                <button v-if="canManage" class="btn-primary text-sm" @click="openSprint()"><AppIcon name="add" :size="15" /> Sprint</button>
            </div>
            <div v-for="s in sprints" :key="s.id" class="card p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-display font-semibold text-slate-800">{{ s.name }}</span>
                            <Badge :label="s.status.label" :color="s.status.color" />
                        </div>
                        <p v-if="s.goal" class="mt-0.5 text-sm text-slate-500">{{ s.goal }}</p>
                        <p class="mt-1 text-xs text-slate-400">{{ date(s.start_date) }} → {{ date(s.end_date) }} · {{ s.task_count ?? 0 }} công việc</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="w-32"><ProgressBar :value="s.progress" /></div>
                        <span v-if="canManage" class="flex gap-1">
                            <button class="grid h-7 w-7 place-items-center rounded text-slate-400 hover:bg-slate-100 hover:text-slate-700" @click="openSprint(s)"><AppIcon name="edit" :size="15" /></button>
                            <button class="grid h-7 w-7 place-items-center rounded text-slate-400 hover:bg-rose-50 hover:text-rose-600" @click="removeSprint(s)"><AppIcon name="delete" :size="15" /></button>
                        </span>
                    </div>
                </div>
            </div>
            <p v-if="!sprints.length" class="rounded-card border border-dashed border-slate-200 py-12 text-center text-sm text-slate-400">Chưa có sprint nào.</p>
        </div>

        <!-- ===== Members & rates ===== -->
        <div v-show="tab === 'members'" class="card overflow-hidden">
            <div class="flex items-center justify-between p-4">
                <h2 class="font-display font-semibold text-slate-800">Thành viên & mức lương</h2>
                <button v-if="canManage" class="btn-primary text-sm" @click="openMember()"><AppIcon name="add" :size="15" /> Thành viên</button>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-2 font-medium">Thành viên</th>
                        <th class="px-4 py-2 font-medium">Vai trò</th>
                        <th class="px-4 py-2 font-medium">Loại lương</th>
                        <th class="px-4 py-2 font-medium text-right">Mức lương</th>
                        <th class="px-4 py-2 font-medium text-right">Phân bổ</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="m in project.members" :key="m.id" class="hover:bg-slate-50">
                        <td class="px-4 py-2.5">
                            <div class="flex items-center gap-2">
                                <Avatar :name="m.name" :src="m.avatar_path" :size="28" />
                                <span class="font-medium text-slate-700">{{ m.name }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-2.5 text-slate-600">{{ m.project_role }}</td>
                        <td class="px-4 py-2.5 text-slate-600">{{ m.rate_type_label }}</td>
                        <td class="px-4 py-2.5 text-right font-medium text-slate-700">{{ currency(m.rate) }}</td>
                        <td class="px-4 py-2.5 text-right text-slate-600">{{ m.allocation != null ? m.allocation + '%' : '—' }}</td>
                        <td class="px-4 py-2.5 text-right">
                            <span v-if="canManage" class="flex justify-end gap-1">
                                <button class="grid h-7 w-7 place-items-center rounded text-slate-400 hover:bg-slate-100 hover:text-slate-700" @click="openMember(m)"><AppIcon name="edit" :size="15" /></button>
                                <button class="grid h-7 w-7 place-items-center rounded text-slate-400 hover:bg-rose-50 hover:text-rose-600" @click="removeMember(m)"><AppIcon name="delete" :size="15" /></button>
                            </span>
                        </td>
                    </tr>
                    <tr v-if="!project.members?.length"><td colspan="6" class="px-4 py-10 text-center text-slate-400">Chưa có thành viên.</td></tr>
                </tbody>
            </table>
        </div>

        <!-- ===== Cost ===== -->
        <div v-show="tab === 'cost'" class="space-y-5">
            <div class="card p-5">
                <h2 class="mb-4 font-display font-semibold text-slate-800">Ngân sách & chi phí nhân công</h2>
                <CostSummary :cost="cost" />
            </div>

            <div class="card overflow-hidden">
                <div class="flex items-center justify-between p-4">
                    <h2 class="font-display font-semibold text-slate-800">Ghi nhận giờ làm theo công việc</h2>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-2 font-medium">Công việc</th>
                            <th class="px-4 py-2 font-medium text-right">Giờ đã ghi</th>
                            <th class="px-4 py-2 font-medium text-right">Chi phí</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="t in tasks" :key="t.id" class="hover:bg-slate-50">
                            <td class="px-4 py-2.5 text-slate-700">{{ t.title }}</td>
                            <td class="px-4 py-2.5 text-right text-slate-600">{{ hours(t.logged_hours) }}</td>
                            <td class="px-4 py-2.5 text-right font-medium text-slate-700">{{ currency(t.labor_cost) }}</td>
                            <td class="px-4 py-2.5 text-right">
                                <button v-if="canContribute" class="btn-ghost text-xs" @click="openWorklog(t)"><AppIcon name="worklog" :size="14" /> Ghi giờ</button>
                            </td>
                        </tr>
                        <tr v-if="!tasks.length"><td colspan="4" class="px-4 py-10 text-center text-slate-400">Chưa có công việc.</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="card overflow-hidden">
                <div class="p-4"><h2 class="font-display font-semibold text-slate-800">Nhật ký giờ làm gần đây</h2></div>
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-2 font-medium">Ngày</th>
                            <th class="px-4 py-2 font-medium">Người làm</th>
                            <th class="px-4 py-2 font-medium">Công việc</th>
                            <th class="px-4 py-2 font-medium text-right">Giờ</th>
                            <th class="px-4 py-2 font-medium text-right">Chi phí</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="w in worklogs" :key="w.id" class="hover:bg-slate-50">
                            <td class="px-4 py-2.5 text-slate-600">{{ date(w.date) }}</td>
                            <td class="px-4 py-2.5 text-slate-700">{{ w.employee?.name }}</td>
                            <td class="px-4 py-2.5 text-slate-600">{{ w.task_title }}</td>
                            <td class="px-4 py-2.5 text-right text-slate-600">{{ hours(w.hours) }}</td>
                            <td class="px-4 py-2.5 text-right font-medium text-slate-700">{{ currency(w.cost) }}</td>
                            <td class="px-4 py-2.5 text-right">
                                <button v-if="canManage" class="grid h-7 w-7 place-items-center rounded text-slate-400 hover:bg-rose-50 hover:text-rose-600" @click="removeWorklog(w)"><AppIcon name="delete" :size="14" /></button>
                            </td>
                        </tr>
                        <tr v-if="!worklogs.length"><td colspan="6" class="px-4 py-10 text-center text-slate-400">Chưa có giờ làm nào.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ===== Blockers ===== -->
        <div v-show="tab === 'blockers'" class="space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="font-display font-semibold text-slate-800">Vướng mắc</h2>
                <button class="btn-primary text-sm" @click="openBlocker()"><AppIcon name="add" :size="15" /> Vướng mắc</button>
            </div>
            <div v-for="b in blockers" :key="b.id" class="card p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <Badge :label="b.severity.label" :color="b.severity.color" />
                            <Badge :label="b.status.label" :color="b.status.color" />
                            <span class="font-medium text-slate-800">{{ b.title }}</span>
                        </div>
                        <p v-if="b.description" class="mt-1 text-sm text-slate-500">{{ b.description }}</p>
                        <p class="mt-1 text-xs text-slate-400">
                            Báo bởi {{ b.raised_by?.name || '—' }}
                            <span v-if="b.owner"> · Xử lý: {{ b.owner.name }}</span>
                        </p>
                    </div>
                    <span v-if="b.can?.update || b.can?.delete" class="flex gap-1">
                        <button v-if="b.can?.update" class="grid h-7 w-7 place-items-center rounded text-slate-400 hover:bg-slate-100 hover:text-slate-700" @click="openBlocker(b)"><AppIcon name="edit" :size="15" /></button>
                        <button v-if="b.can?.delete" class="grid h-7 w-7 place-items-center rounded text-slate-400 hover:bg-rose-50 hover:text-rose-600" @click="removeBlocker(b)"><AppIcon name="delete" :size="15" /></button>
                    </span>
                </div>
            </div>
            <p v-if="!blockers.length" class="rounded-card border border-dashed border-slate-200 py-12 text-center text-sm text-slate-400">Không có vướng mắc nào.</p>
        </div>

        <!-- ===== Modals ===== -->
        <TaskFormModal
            :show="taskModal" :project-id="project.id" :task="editingTask"
            :sprints="sprints" :employees="options.employees" :tasks="tasks"
            :status-options="enums.taskStatus || []" :priority-options="enums.taskPriority || []"
            :default-status="taskDefaultStatus"
            @close="taskModal = false"
        />
        <SprintFormModal
            :show="sprintModal" :project-id="project.id" :sprint="editingSprint"
            :status-options="enums.sprintStatus || []"
            @close="sprintModal = false"
        />
        <MemberFormModal
            :show="memberModal" :project-id="project.id" :member="editingMember"
            :employees="options.employees" :rate-type-options="enums.rateType || []"
            @close="memberModal = false"
        />
        <WorklogFormModal
            :show="worklogModal" :project-id="project.id" :task="worklogTask"
            :employees="options.employees"
            @close="worklogModal = false"
        />
        <BlockerFormModal
            :show="blockerModal" :blocker="editingBlocker"
            :projects="[{ id: project.id, name: project.name }]" :employees="options.employees"
            :severity-options="enums.blockerSeverity || []" :status-options="enums.blockerStatus || []"
            :default-project-id="project.id" :lock-project="true"
            @close="blockerModal = false"
        />
    </AppLayout>
</template>
