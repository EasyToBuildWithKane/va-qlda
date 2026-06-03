<script setup>
/* eslint-disable vue/no-v-html -- task description HTML from TipTap editor */
import { ref, computed, watch, toRef, unref, isRef } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import MarkdownIt from 'markdown-it';
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import Badge from '@/shared/ui/Badge.vue';
import ProgressBar from '@/shared/ui/ProgressBar.vue';
import TaskDetailAttachments from '@/modules/project/components/TaskDetail/TaskDetailAttachments.vue';
import TaskDetailCollaboration from '@/modules/project/components/TaskDetail/TaskDetailCollaboration.vue';
import TaskDetailRichEditor from '@/modules/project/components/TaskDetail/TaskDetailRichEditor.vue';
import TaskDetailSubtasks from '@/modules/project/components/TaskDetail/TaskDetailSubtasks.vue';
import { date, datetime, hours } from '@/composables/useFormat';
import { useToast } from '@/shared/composables/useToast';
import { useSprintTaskStatusPatch } from '@/composables/useSprintTaskStatusPatch';
import {
    taskDisplayId,
    useTaskWorkspace,
    useTaskPanelLayout,
} from '@/composables/useTaskWorkspace';
import { normalizeList, normalizeEntities, normalizeKeyed } from '@/composables/useNormalizeList';
import { matchesSearchKey } from '@/shared/utils/normalizeSearchKey';

const props = defineProps({
    task: { type: Object, default: null },
    projectId: { type: Number, required: true },
    project: { type: Object, default: null },
    sprints: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
    statusOptions: { type: Array, default: () => [] },
    priorityOptions: { type: Array, default: () => [] },
    phaseOptions: { type: Array, default: () => [] },
    blockers: { type: Array, default: () => [] },
    attachments: { type: Array, default: () => [] },
    allTasks: { type: Array, default: () => [] },
    epics: { type: Array, default: () => [] },
    canEdit: { type: Boolean, default: false },
    canComment: { type: Boolean, default: false },
});

const page = usePage();
const currentEmployeeId = computed(() => page.props.auth?.user?.employee?.id ?? null);

const emit = defineEmits(['close', 'edit', 'open-task', 'updated']);

const toast = useToast();
const tab = ref('overview');
const showStatusMenu = ref(false);
const showAssignMenu = ref(false);
const assignMenuSearch = ref('');
const collaborationRef = ref(null);

const { patchTaskStatus } = useSprintTaskStatusPatch(props.projectId, props.statusOptions);
const { fullscreen, resizing, startResize, toggleFullscreen, panelStyle } = useTaskPanelLayout();

const activeTask = computed(() => {
    let raw = unref(props.task);
    if (isRef(raw)) raw = raw.value;
    return raw && typeof raw === 'object' && raw.id != null ? raw : null;
});

const ws = useTaskWorkspace(activeTask, {
    project: toRef(() => props.project),
    sprints: toRef(() => props.sprints),
    blockers: toRef(() => props.blockers),
    attachments: toRef(() => props.attachments),
    allTasks: toRef(() => props.allTasks),
    epics: toRef(() => props.epics),
    currentEmployeeId,
});

const progressPct = computed(() => {
    const n = Number(activeTask.value?.progress);
    return Number.isFinite(n) ? Math.min(100, Math.max(0, Math.round(n))) : 0;
});

const md = new MarkdownIt({ linkify: true, breaks: true });

const descriptionHtml = computed(() => {
    const raw = activeTask.value?.description?.trim();
    if (!raw) return '';
    if (/^#{1,6}\s|^\*\*|^-\s|^\d+\.\s/m.test(raw) || raw.includes('```')) {
        return md.render(raw);
    }
    return md.render(raw.replace(/\n/g, '  \n'));
});

const taskUrl = computed(() => {
    if (typeof window === 'undefined' || !activeTask.value) return '';
    return `${window.location.origin}/projects/${props.projectId}#task-${activeTask.value.id}`;
});

const copyLink = async () => {
    try {
        await navigator.clipboard.writeText(taskUrl.value);
        toast.success('Đã sao chép liên kết task');
    } catch {
        toast.error('Không sao chép được liên kết');
    }
};

const onStatusPick = (status) => {
    showStatusMenu.value = false;
    if (!activeTask.value) return;
    patchTaskStatus(activeTask.value, status, { onSuccess: () => emit('updated') });
};

const onAssignPick = (employeeId) => {
    showAssignMenu.value = false;
    assignMenuSearch.value = '';
    if (!activeTask.value?.id) return;
    router.patch(`/projects/${props.projectId}/tasks/${activeTask.value.id}`, {
        assignee_id: employeeId || null,
    }, {
        preserveScroll: true,
        only: ['tasks'],
        onSuccess: () => {
            toast.success('Đã cập nhật người thực hiện');
            emit('updated');
        },
    });
};

const goComment = () => {
    tab.value = 'collaboration';
    setTimeout(() => collaborationRef.value?.focusComposer(), 80);
};

const toggleWatch = () => {
    if (!activeTask.value?.id) return;
    router.post(`/projects/${props.projectId}/tasks/${activeTask.value.id}/watchers/toggle`, {}, {
        preserveScroll: true,
        only: ['tasks'],
        onSuccess: () => emit('updated'),
    });
};

const openSubtask = (st) => {
    const full = props.allTasks.find((t) => t.id === st.id) || st;
    emit('open-task', full);
};

const attachTab = () => { tab.value = 'overview'; };

const scheduleLine = computed(() => {
    const t = activeTask.value;
    if (!t) return '';
    const parts = [];
    if (t.start_date) parts.push(`Bắt đầu ${date(t.start_date)}`);
    if (t.due_date) parts.push(`Hạn ${date(t.due_date)}`);
    return parts.join(' · ');
});

const timeSummary = computed(() => {
    if (ws.estimateHours.value == null) {
        return ws.loggedHours.value > 0 ? `Đã ghi ${ws.loggedHours.value}h` : null;
    }
    return `Ước tính ${ws.estimateHours.value}h · Đã ghi ${ws.loggedHours.value}h`;
});

const toneClass = (tone) => ({
    brand: 'bg-brand/10 text-brand ring-brand/20',
    sky: 'bg-sky-50 text-sky-700 ring-sky-100 dark:bg-sky-950/40 dark:text-sky-300',
    violet: 'bg-violet-50 text-violet-700 ring-violet-100 dark:bg-violet-950/40 dark:text-violet-300',
    rose: 'bg-rose-50 text-rose-700 ring-rose-100 dark:bg-rose-950/40 dark:text-rose-300',
    emerald: 'bg-emerald-50 text-emerald-700 ring-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-300',
    slate: 'bg-slate-100 text-slate-600 ring-slate-200 dark:bg-slate-800 dark:text-slate-300',
}[tone] || 'bg-slate-100 text-slate-600');

watch(() => activeTask.value?.id, () => {
    tab.value = 'overview';
    showStatusMenu.value = false;
    showAssignMenu.value = false;
});

const PANEL_TABS = [
    { key: 'overview', label: 'Tổng quan', icon: 'template' },
    { key: 'collaboration', label: 'Trao đổi', icon: 'comment' },
    { key: 'activity', label: 'Hoạt động', icon: 'worklog' },
    { key: 'links', label: 'Tài liệu', icon: 'documents' },
];

const panelTabList = normalizeKeyed(PANEL_TABS);
const projectDocList = computed(() =>
    normalizeKeyed(unref(ws.projectDocs)).map((g) => ({
        ...g,
        files: normalizeEntities(g.files),
    })),
);

const employeeList = computed(() => normalizeEntities(props.employees));
const filteredAssignees = computed(() => {
    const q = assignMenuSearch.value.trim();
    if (!q) return employeeList.value;
    return employeeList.value.filter((e) => matchesSearchKey(e.name, q));
});
const statusOptionList = computed(() =>
    normalizeList(props.statusOptions).filter((o) => o?.value != null),
);
const assigneeList = computed(() => normalizeEntities(unref(ws.assignees)));
const blockerList = computed(() => normalizeEntities(unref(ws.taskBlockers)));
const relatedBlockedByList = computed(() => normalizeEntities(unref(ws.relatedBlockedBy)));
const relatedBlockingList = computed(() => normalizeEntities(unref(ws.relatedBlocking)));
const activityList = computed(() => normalizeEntities(unref(ws.activityTimeline)));
const commentList = computed(() => normalizeEntities(activeTask.value?.comments));
const subtaskList = computed(() => normalizeEntities(activeTask.value?.subtasks));
const attachmentList = computed(() => normalizeEntities(activeTask.value?.attachments));
const watcherList = computed(() => normalizeEntities(activeTask.value?.watchers));
const worklogList = computed(() => normalizeEntities(activeTask.value?.worklogs));
</script>

<template>
  <Teleport to="body">
    <Transition name="fade">
      <div
        v-if="activeTask"
        class="fixed inset-0 z-[180] flex justify-end"
      >
        <div
          class="absolute inset-0 bg-slate-900/45 backdrop-blur-[2px]"
          @click="emit('close')"
        />

        <aside
          class="relative flex h-full flex-col border-l border-slate-200/90 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-950"
          :class="[fullscreen ? 'w-full' : '', resizing ? 'select-none' : '']"
          :style="panelStyle"
        >
          <!-- Resize handle -->
          <div
            v-if="!fullscreen"
            class="absolute left-0 top-0 z-10 h-full w-1.5 cursor-ew-resize hover:bg-brand/30"
            title="Kéo để đổi độ rộng"
            @mousedown="startResize"
          />

          <!-- ── HEADER ── -->
          <header class="shrink-0 border-b border-slate-100 px-4 pb-3 pt-3 dark:border-slate-800">
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-1.5">
                  <span class="font-mono text-[10px] text-slate-400">{{ taskDisplayId(activeTask) }}</span>
                  <Badge
                    v-if="activeTask.status?.label"
                    :label="activeTask.status.label"
                    :color="activeTask.status.color"
                  />
                  <Badge
                    v-if="activeTask.priority?.label"
                    :label="activeTask.priority.label"
                    :color="activeTask.priority.color"
                  />
                  <Badge
                    v-if="activeTask.phase?.label"
                    :label="activeTask.phase.label"
                    color="violet"
                  />
                </div>
                <h1 class="mt-1.5 font-display text-lg font-bold leading-snug text-slate-900 dark:text-white">
                  {{ activeTask.title }}
                </h1>
                <p
                  v-if="ws.headerContext"
                  class="mt-1 text-xs text-slate-500"
                >
                  {{ ws.headerContext }}
                </p>
                <p
                  v-if="scheduleLine"
                  class="mt-0.5 text-xs text-slate-400"
                >
                  {{ scheduleLine }}
                </p>
                <p
                  v-if="activeTask.parent?.id"
                  class="mt-1 text-xs text-slate-500"
                >
                  Thuộc
                  <button
                    type="button"
                    class="font-medium text-brand hover:underline"
                    @click="openSubtask(activeTask.parent)"
                  >
                    #{{ activeTask.parent.id }} {{ activeTask.parent.title }}
                  </button>
                </p>
              </div>
              <div class="flex shrink-0 items-center gap-0.5">
                <button
                  type="button"
                  class="grid h-7 w-7 place-items-center rounded-md text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800"
                  :title="fullscreen ? 'Thu nhỏ' : 'Toàn màn hình'"
                  @click="toggleFullscreen"
                >
                  <AppIcon
                    :name="fullscreen ? 'chevron-right' : 'template'"
                    :size="15"
                  />
                </button>
                <button
                  type="button"
                  class="grid h-7 w-7 place-items-center rounded-md text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800"
                  title="Đóng"
                  @click="emit('close')"
                >
                  <AppIcon
                    name="close"
                    :size="16"
                  />
                </button>
              </div>
            </div>

            <div class="mt-2.5 flex flex-wrap items-center gap-1">
              <button
                v-if="canEdit"
                type="button"
                class="inline-flex items-center gap-1 rounded-md bg-brand px-2.5 py-1 text-xs font-semibold text-white hover:bg-brand/90"
                @click="emit('edit', activeTask)"
              >
                <AppIcon
                  name="edit"
                  :size="13"
                /> Sửa
              </button>

              <div
                v-if="canEdit"
                class="relative"
              >
                <button
                  type="button"
                  class="inline-flex items-center gap-1 rounded-md border border-slate-200 px-2 py-1 text-xs text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800"
                  title="Đổi trạng thái"
                  @click="showStatusMenu = !showStatusMenu"
                >
                  <AppIcon
                    name="task"
                    :size="13"
                  />
                  <span class="max-w-[5rem] truncate">{{ activeTask.status?.label || 'Trạng thái' }}</span>
                </button>
                <div
                  v-if="showStatusMenu"
                  class="absolute left-0 top-full z-20 mt-1 min-w-[9rem] rounded-lg border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-slate-900"
                >
                  <button
                    v-for="o in statusOptionList"
                    :key="o.value"
                    type="button"
                    class="flex w-full px-3 py-1.5 text-left text-xs hover:bg-slate-50 dark:hover:bg-slate-800"
                    :class="activeTask.status?.value === o.value ? 'font-semibold text-brand' : 'text-slate-600'"
                    @click="onStatusPick(o.value)"
                  >
                    {{ o.label }}
                  </button>
                </div>
              </div>

              <div
                v-if="canEdit"
                class="relative"
              >
                <button
                  type="button"
                  class="inline-flex items-center gap-1 rounded-md border border-slate-200 px-2 py-1 text-xs text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-800"
                  title="Giao việc"
                  @click="showAssignMenu = !showAssignMenu"
                >
                  <AppIcon
                    name="people"
                    :size="13"
                  />
                </button>
                <div
                  v-if="showAssignMenu"
                  class="absolute left-0 top-full z-20 mt-1 min-w-[14rem] overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-900"
                >
                  <div class="border-b border-slate-100 p-1.5 dark:border-slate-700">
                    <input
                      v-model="assignMenuSearch"
                      type="text"
                      class="input w-full py-1 text-xs"
                      placeholder="Tìm theo tên…"
                      autofocus
                    >
                  </div>
                  <div class="max-h-44 overflow-y-auto py-1">
                    <button
                      type="button"
                      class="w-full px-3 py-1.5 text-left text-xs text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-800"
                      @click="onAssignPick(null)"
                    >
                      — Chưa gán —
                    </button>
                    <button
                      v-for="e in filteredAssignees"
                      :key="e.id"
                      type="button"
                      class="w-full px-3 py-1.5 text-left text-xs hover:bg-slate-50 dark:hover:bg-slate-800"
                      @click="onAssignPick(e.id)"
                    >
                      {{ e.name }}
                    </button>
                    <p
                      v-if="!filteredAssignees.length"
                      class="px-3 py-2 text-center text-xs text-slate-400"
                    >
                      Không tìm thấy.
                    </p>
                  </div>
                </div>
              </div>

              <button
                type="button"
                class="grid h-7 w-7 place-items-center rounded-md border border-slate-200 text-slate-500 hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-800"
                title="Bình luận"
                @click="goComment"
              >
                <AppIcon
                  name="comment"
                  :size="14"
                />
              </button>
              <button
                type="button"
                class="grid h-7 w-7 place-items-center rounded-md border border-slate-200 text-slate-500 hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-800"
                title="Sao chép link"
                @click="copyLink"
              >
                <AppIcon
                  name="copy"
                  :size="14"
                />
              </button>
              <button
                type="button"
                class="grid h-7 w-7 place-items-center rounded-md border text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-800"
                :class="ws.isWatching ? 'border-brand bg-brand/10 text-brand' : 'border-slate-200 dark:border-slate-600'"
                :title="ws.isWatching ? 'Đang theo dõi' : 'Theo dõi'"
                @click="toggleWatch"
              >
                <AppIcon
                  name="eye"
                  :size="14"
                />
              </button>
              <button
                v-if="canEdit"
                type="button"
                class="grid h-7 w-7 place-items-center rounded-md border border-slate-200 text-slate-500 hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-800"
                title="Đính kèm"
                @click="attachTab"
              >
                <AppIcon
                  name="upload"
                  :size="14"
                />
              </button>
            </div>
          </header>

          <!-- Blockers alert -->
          <div
            v-if="blockerList.length"
            class="mx-4 mt-3 shrink-0 space-y-2"
          >
            <div
              v-for="b in blockerList"
              :key="b.id"
              class="rounded-lg border border-amber-200 bg-amber-50/90 px-3 py-2.5 dark:border-amber-900/50 dark:bg-amber-950/30"
            >
              <div class="flex items-start gap-2">
                <AppIcon
                  name="blockers"
                  :size="16"
                  class="mt-0.5 shrink-0 text-amber-600"
                />
                <div class="min-w-0">
                  <p class="text-xs font-semibold text-amber-900 dark:text-amber-100">
                    {{ b.code }} · {{ b.title }}
                  </p>
                  <p
                    v-if="b.root_cause"
                    class="mt-0.5 text-[11px] text-amber-800/90"
                  >
                    {{ b.root_cause }}
                  </p>
                  <p class="mt-1 text-[10px] text-amber-700">
                    {{ b.severity?.label }} · {{ b.owner?.name ? `Xử lý: ${b.owner.name}` : 'Chưa có owner' }}
                  </p>
                </div>
              </div>
            </div>
          </div>

          <!-- Tabs -->
          <nav class="mt-3 flex shrink-0 gap-0.5 overflow-x-auto border-b border-slate-100 px-4 dark:border-slate-800">
            <button
              v-for="(pt, ptIdx) in panelTabList"
              :key="pt.key ?? `tab-${ptIdx}`"
              type="button"
              class="flex shrink-0 items-center gap-1.5 border-b-2 px-3 py-2.5 text-xs font-semibold transition"
              :class="tab === pt.key ? 'border-brand text-brand' : 'border-transparent text-slate-500 hover:text-slate-700'"
              @click="tab = pt.key"
            >
              <AppIcon
                :name="pt.icon"
                :size="14"
              />
              {{ pt.label }}
              <span
                v-if="pt.key === 'collaboration' && commentList.length"
                class="rounded-full bg-slate-200 px-1.5 text-[10px] dark:bg-slate-700"
              >
                {{ commentList.length }}
              </span>
            </button>
          </nav>

          <!-- Body -->
          <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4">
            <!-- OVERVIEW -->
            <div
              v-show="tab === 'overview'"
              class="space-y-5"
            >
              <!-- Tiến độ -->
              <section class="rounded-lg border border-slate-200/80 p-3 dark:border-slate-700">
                <div class="mb-2 flex items-center justify-between gap-2">
                  <span class="text-xs font-medium text-slate-500">Tiến độ công việc</span>
                  <span class="text-base font-bold text-brand">{{ progressPct }}%</span>
                </div>
                <ProgressBar
                  :value="progressPct"
                  class="h-2"
                />
                <p
                  v-if="timeSummary"
                  class="mt-2 text-xs text-slate-500"
                >
                  {{ timeSummary }}
                </p>
                <ul
                  v-if="worklogList.length"
                  class="mt-2 space-y-1 border-t border-slate-100 pt-2 dark:border-slate-800"
                >
                  <li
                    v-for="w in worklogList.slice(0, 3)"
                    :key="w.id"
                    class="flex justify-between text-[11px] text-slate-500"
                  >
                    <span>{{ date(w.date) }}<template v-if="w.employee?.name"> · {{ w.employee.name }}</template></span>
                    <span class="font-medium text-slate-700 dark:text-slate-300">{{ hours(w.hours) }}</span>
                  </li>
                  <li
                    v-if="worklogList.length > 3"
                    class="text-[10px] text-slate-400"
                  >
                    +{{ worklogList.length - 3 }} bản ghi khác — xem tab Hoạt động
                  </li>
                </ul>
              </section>

              <!-- People -->
              <section class="rounded-lg border border-slate-200/80 bg-slate-50/50 p-3 dark:border-slate-700 dark:bg-slate-900/50">
                <h3 class="mb-2 text-xs font-semibold text-slate-500">
                  Phân công
                </h3>
                <div class="space-y-3">
                  <div
                    v-if="activeTask.reporter"
                    class="flex items-center gap-3"
                  >
                    <Avatar
                      :name="activeTask.reporter.name"
                      :src="activeTask.reporter.avatar_path"
                      :size="36"
                    />
                    <div>
                      <p class="text-sm font-medium text-slate-800 dark:text-slate-100">
                        {{ activeTask.reporter.name }}
                      </p>
                      <p class="text-[10px] uppercase text-slate-400">
                        Người giao
                      </p>
                    </div>
                  </div>
                  <div
                    v-for="a in assigneeList"
                    :key="a.id"
                    class="flex items-center gap-3"
                  >
                    <Avatar
                      :name="a.name"
                      :src="a.avatar_path"
                      :size="36"
                    />
                    <div>
                      <p class="text-sm font-medium text-slate-800 dark:text-slate-100">
                        {{ a.name }}
                      </p>
                      <p class="text-[10px] uppercase text-slate-400">
                        Người thực hiện
                      </p>
                    </div>
                  </div>
                  <p
                    v-if="!assigneeList.length && !activeTask.reporter"
                    class="text-sm text-slate-400"
                  >
                    Chưa phân công.
                  </p>
                  <div
                    v-if="activeTask.reviewer"
                    class="flex items-center gap-3 border-t border-slate-200/80 pt-3 dark:border-slate-700"
                  >
                    <Avatar
                      :name="activeTask.reviewer.name"
                      :src="activeTask.reviewer.avatar_path"
                      :size="36"
                    />
                    <div>
                      <p class="text-sm font-medium text-slate-800 dark:text-slate-100">
                        {{ activeTask.reviewer.name }}
                      </p>
                      <p class="text-[10px] uppercase text-slate-400">
                        Người duyệt
                      </p>
                    </div>
                  </div>
                  <div
                    v-if="watcherList.length"
                    class="border-t border-slate-200/80 pt-3 dark:border-slate-700"
                  >
                    <p class="mb-2 text-[10px] uppercase text-slate-400">
                      Người theo dõi
                    </p>
                    <div class="flex flex-wrap gap-1">
                      <span
                        v-for="w in watcherList"
                        :key="w.id"
                        class="inline-flex items-center gap-1 rounded-full bg-white px-2 py-0.5 text-xs shadow-sm dark:bg-slate-800"
                      >
                        <Avatar
                          :name="w.name"
                          :src="w.avatar_path"
                          :size="18"
                        />
                        {{ w.name }}
                      </span>
                    </div>
                  </div>
                </div>
                <button
                  v-if="canEdit"
                  type="button"
                  class="mt-3 text-xs font-medium text-brand hover:underline"
                  @click="emit('edit', activeTask)"
                >
                  Quản lý phân công đầy đủ →
                </button>
              </section>

              <TaskDetailSubtasks
                :task-id="activeTask.id"
                :project-id="projectId"
                :subtasks="subtaskList"
                :is-parent-subtask="!!activeTask.parent_id"
                :can-edit="canEdit"
                @open-task="openSubtask"
                @created="emit('updated')"
              />

              <TaskDetailRichEditor
                :task-id="activeTask.id"
                :project-id="projectId"
                :model-value="activeTask.description"
                :can-edit="canEdit"
                @saved="emit('updated')"
              >
                <div
                  v-if="descriptionHtml"
                  class="prose prose-sm max-w-none rounded-xl border border-slate-100 bg-white p-4 dark:prose-invert dark:border-slate-800 dark:bg-slate-900/50"
                  v-html="descriptionHtml"
                />
                <p
                  v-else
                  class="rounded-xl border border-dashed border-slate-200 py-8 text-center text-sm text-slate-400"
                >
                  Chưa có mô tả.
                </p>
              </TaskDetailRichEditor>

              <TaskDetailAttachments
                :task-id="activeTask.id"
                :project-id="projectId"
                :attachments="attachmentList"
                :can-edit="canEdit"
                @uploaded="emit('updated')"
              />

              <!-- Related -->
              <section
                v-if="relatedBlockedByList.length || relatedBlockingList.length"
                class="rounded-xl border border-slate-200/80 p-4 dark:border-slate-700"
              >
                <h3 class="mb-3 text-xs font-bold uppercase tracking-wide text-slate-500">
                  Liên kết công việc
                </h3>
                <div
                  v-if="relatedBlockedByList.length"
                  class="mb-3"
                >
                  <p class="mb-1.5 text-[10px] font-semibold uppercase text-slate-400">
                    Phụ thuộc (Blocked by)
                  </p>
                  <button
                    v-for="r in relatedBlockedByList"
                    :key="r.id"
                    type="button"
                    class="mb-1 flex w-full items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-left text-sm hover:bg-slate-100 dark:bg-slate-800/60"
                    @click="emit('open-task', r)"
                  >
                    <span class="truncate font-medium">#{{ r.id }} {{ r.title }}</span>
                    <Badge
                      v-if="r.status"
                      :label="r.status.label"
                      :color="r.status.color"
                    />
                  </button>
                </div>
                <div v-if="relatedBlockingList.length">
                  <p class="mb-1.5 text-[10px] font-semibold uppercase text-slate-400">
                    Đang chặn (Blocking)
                  </p>
                  <button
                    v-for="r in relatedBlockingList"
                    :key="r.id"
                    type="button"
                    class="mb-1 flex w-full items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-left text-sm hover:bg-slate-100 dark:bg-slate-800/60"
                    @click="emit('open-task', r)"
                  >
                    <span class="truncate font-medium">#{{ r.id }} {{ r.title }}</span>
                    <span class="text-xs text-slate-500">{{ r.progress }}%</span>
                  </button>
                </div>
              </section>
            </div>

            <!-- COLLABORATION -->
            <div v-show="tab === 'collaboration'">
              <TaskDetailCollaboration
                ref="collaborationRef"
                :comments="commentList"
                :commentable-id="activeTask.id"
                :can-comment="canComment"
              />
            </div>

            <!-- ACTIVITY -->
            <div
              v-show="tab === 'activity'"
              class="space-y-0"
            >
              <div
                v-for="ev in activityList"
                :key="ev.id"
                class="relative flex gap-3 pb-6 pl-1 last:pb-0"
              >
                <div class="relative flex flex-col items-center">
                  <span
                    class="grid h-8 w-8 shrink-0 place-items-center rounded-full ring-2"
                    :class="toneClass(ev.tone)"
                  >
                    <AppIcon
                      :name="ev.icon"
                      :size="14"
                    />
                  </span>
                  <span class="mt-1 w-px flex-1 bg-slate-200 dark:bg-slate-700" />
                </div>
                <div class="min-w-0 flex-1 pt-0.5">
                  <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                    {{ ev.title }}
                  </p>
                  <p class="mt-0.5 text-xs text-slate-500">
                    {{ datetime(ev.at) }}
                  </p>
                  <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                    {{ ev.detail }}
                  </p>
                </div>
              </div>
              <p
                v-if="!activityList.length"
                class="py-8 text-center text-sm text-slate-400"
              >
                Chưa có hoạt động ghi nhận.
              </p>
            </div>

            <!-- LINKS / DOCS -->
            <div
              v-show="tab === 'links'"
              class="space-y-4"
            >
              <p class="text-sm text-slate-500">
                Tài liệu dự án — truy cập nhanh từ workspace task.
              </p>
              <div
                v-for="(g, gIdx) in projectDocList"
                :key="g.key ?? `doc-${gIdx}`"
                class="rounded-xl border border-slate-200/80 dark:border-slate-700"
              >
                <div class="border-b border-slate-100 px-3 py-2 dark:border-slate-800">
                  <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                    {{ g.label }}
                  </p>
                  <p class="text-[10px] text-slate-400">
                    {{ g.hint }}
                  </p>
                </div>
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                  <li
                    v-for="f in g.files"
                    :key="f.id"
                  >
                    <a
                      :href="f.url"
                      target="_blank"
                      rel="noopener"
                      class="flex items-center gap-2 px-3 py-2.5 text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800/50"
                    >
                      <AppIcon
                        name="documents"
                        :size="16"
                        class="shrink-0 text-slate-400"
                      />
                      <span class="min-w-0 flex-1 truncate">{{ f.original_name }}</span>
                      <AppIcon
                        name="download"
                        :size="14"
                        class="shrink-0 text-slate-400"
                      />
                    </a>
                  </li>
                </ul>
              </div>
              <p
                v-if="!projectDocList.length"
                class="rounded-xl border border-dashed border-slate-200 py-10 text-center text-sm text-slate-400"
              >
                Chưa có tài liệu dự án. Thêm tại tab Tài liệu dự án.
              </p>
              <button
                v-if="canEdit"
                type="button"
                class="btn-ghost w-full border border-slate-200 text-sm"
                @click="emit('edit', activeTask)"
              >
                Đính kèm / quản lý qua form chỉnh sửa
              </button>
            </div>
          </div>
        </aside>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

:deep(.prose p) { margin-top: 0.5em; margin-bottom: 0.5em; }
:deep(.prose ul) { margin: 0.5em 0; padding-left: 1.25em; }
</style>
