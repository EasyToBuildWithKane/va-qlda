<script setup>
/* eslint-disable vue/no-v-html -- task description HTML from TipTap editor */
import { ref, computed, watch, toRef, unref, isRef, nextTick, onBeforeUnmount } from 'vue';
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
import { useDialog } from '@/composables/useDialog';
import { useSprintTaskStatusPatch } from '@/composables/useSprintTaskStatusPatch';
import { getTaskCompletionBadge } from '@/composables/useTaskCompletion';
import { getTaskSlaToneClass } from '@/composables/useTaskTimeliness';
import { usePermission } from '@/shared/composables/usePermission';
import {
    taskDisplayId,
    useTaskWorkspace,
    useTaskPanelLayout,
} from '@/composables/useTaskWorkspace';
import { getDirectChildren } from '@/composables/useTaskHierarchy';
import { normalizeList, normalizeEntities, normalizeKeyed } from '@/composables/useNormalizeList';
import { matchesSearchKey } from '@/shared/utils/normalizeSearchKey';
import { taskProgressFromStatus } from '@/shared/utils/taskProgress';
import { useClientPagination } from '@/shared/composables/useClientPagination';
import DatagridPaginationFooter from '@/shared/ui/DatagridPaginationFooter.vue';

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
    canDelete: { type: Boolean, default: false },
    initialPanelTab: { type: String, default: 'overview' },
});

const page = usePage();
const currentEmployeeId = computed(() => page.props.auth?.user?.employee?.id ?? null);

const emit = defineEmits(['close', 'edit', 'open-task', 'updated', 'panel-tab-change', 'deleted']);

const toast = useToast();
const dialog = useDialog();
const tab = ref(props.initialPanelTab || 'overview');
const showActionsMenu = ref(false);
const actionsSubMenu = ref(null);
const assignMenuSearch = ref('');
const assignSearchRef = ref(null);
const actionsMenuRef = ref(null);
const actionsTriggerRef = ref(null);

const closeActionsMenu = () => {
    showActionsMenu.value = false;
    actionsSubMenu.value = null;
    assignMenuSearch.value = '';
};

const toggleActionsMenu = () => {
    if (showActionsMenu.value) {
        closeActionsMenu();
        return;
    }
    showActionsMenu.value = true;
    actionsSubMenu.value = null;
};

function onActionsPointerDownOutside(e) {
    const t = e.target;
    if (actionsMenuRef.value?.contains(t) || actionsTriggerRef.value?.contains(t)) return;
    closeActionsMenu();
}

watch(showActionsMenu, async (open) => {
    if (open) {
        document.addEventListener('mousedown', onActionsPointerDownOutside);
        return;
    }
    document.removeEventListener('mousedown', onActionsPointerDownOutside);
});

watch(actionsSubMenu, async (sub) => {
    if (sub !== 'assign') return;
    await nextTick();
    assignSearchRef.value?.focus({ preventScroll: true });
});

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onActionsPointerDownOutside);
});
const collaborationRef = ref(null);

const { isRole } = usePermission();
const { patchTaskStatus } = useSprintTaskStatusPatch(props.projectId, props.statusOptions);
const { fullscreen, resizing, startResize, toggleFullscreen, panelStyle } = useTaskPanelLayout();

const activeTask = computed(() => {
    let raw = unref(props.task);
    if (isRef(raw)) raw = raw.value;
    return raw && typeof raw === 'object' && raw.id != null ? raw : null;
});

const completionBadge = computed(() => getTaskCompletionBadge(activeTask.value));

const isSubtaskRow = computed(() => !!activeTask.value?.parent_id);

const canChangeStatus = computed(() => {
    if (!props.canEdit) return false;
    const t = activeTask.value;
    if (!t) return false;
    if (t.can_change_status === false) return false;
    if (t.status?.value === 'done' && t.status_locked && !isRole('admin', 'super_admin')) return false;
    return t.can_change_status !== false;
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

const progressPct = computed(() => taskProgressFromStatus(activeTask.value?.status));

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
    const q = new URLSearchParams({ tab: 'sprints', task: String(activeTask.value.id) });
    return `${window.location.origin}/projects/${props.projectId}?${q}`;
});

const copyLink = async () => {
    try {
        await navigator.clipboard.writeText(taskUrl.value);
        toast.success('Đã sao chép liên kết task');
    } catch {
        toast.error('Không sao chép được liên kết');
    }
};

const removeTask = async () => {
    const t = activeTask.value;
    if (!t?.id || !props.canDelete) return;
    const childCount = getDirectChildren(t.id, props.allTasks).length;
    let message = `Xoá công việc «${t.title}»? Hành động không thể hoàn tác.`;
    if (childCount) {
        message += ` ${childCount} công việc con sẽ tách khỏi công việc cha.`;
    }
    if (!await dialog.confirm({
        title: 'Xoá công việc',
        message,
        tone: 'danger',
        confirmText: 'Xoá',
    })) {
        return;
    }
    router.delete(`/projects/${props.projectId}/tasks/${t.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Đã xoá công việc');
            emit('deleted');
        },
        onError: () => toast.error('Không thể xoá công việc'),
    });
};

const onStatusPick = (status) => {
    closeActionsMenu();
    if (!activeTask.value) return;
    patchTaskStatus(activeTask.value, status, { onSuccess: () => emit('updated') });
};

const onAssignPick = (employeeId) => {
    closeActionsMenu();
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

const openSubtask = (st) => {
    const full = props.allTasks.find((t) => t.id === st.id) || st;
    emit('open-task', full);
};

const scheduleLine = computed(() => {
    const t = activeTask.value;
    if (!t) return '';
    const parts = [];
    if (t.start_date) parts.push(`Bắt đầu ${date(t.start_date)}`);
    if (t.due_date) parts.push(`Hạn ${date(t.due_date)}`);
    return parts.join(' · ');
});

const epicDisplay = computed(() => {
    const name = activeTask.value?.epic?.name;
    if (typeof name !== 'string') return null;
    const trimmed = name.trim();
    return trimmed || null;
});

const assigneeSummary = computed(() => {
    const list = unref(ws.assignees) ?? [];
    if (!list.length) return null;
    const names = list.map((a) => a?.name).filter(Boolean);
    return names.length ? names.join(', ') : null;
});

const statusStripeBorder = computed(() => {
    const color = activeTask.value?.status?.color || 'slate';
    const borders = {
        brand: 'border-l-brand',
        slate: 'border-l-slate-400',
        sky: 'border-l-sky-500',
        violet: 'border-l-violet-500',
        emerald: 'border-l-emerald-500',
        rose: 'border-l-rose-500',
        amber: 'border-l-amber-500',
    };
    return borders[color] || borders.slate;
});

const hasHeaderMeta = computed(() => Boolean(
    unref(ws.sprintLine)?.trim() || epicDisplay.value || scheduleLine.value || estimateLine.value,
));

const estimateLine = computed(() => {
    if (unref(ws.estimateHours) == null) return null;
    const prefix = unref(ws.estimateFromSubtasksOnly) ? 'Tổng công việc con' : 'Ước tính';
    return `${prefix} ${unref(ws.estimateHours)}h`;
});

const loggedHoursLine = computed(() => {
    const h = unref(ws.loggedHours);
    return h > 0 ? `Đã ghi ${h}h` : null;
});

const toneClass = (tone) => ({
    brand: 'bg-brand/10 text-brand ring-brand/20',
    sky: 'bg-sky-50 text-sky-700 ring-sky-100 dark:bg-sky-950/40 dark:text-sky-300',
    violet: 'bg-violet-50 text-violet-700 ring-violet-100 dark:bg-violet-950/40 dark:text-violet-300',
    rose: 'bg-rose-50 text-rose-700 ring-rose-100 dark:bg-rose-950/40 dark:text-rose-300',
    emerald: 'bg-emerald-50 text-emerald-700 ring-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-300',
    slate: 'bg-slate-100 text-slate-600 ring-slate-200 dark:bg-slate-800 dark:text-slate-300',
}[tone] || 'bg-slate-100 text-slate-600');

const onActionEdit = () => {
    closeActionsMenu();
    emit('edit', activeTask.value);
};

const onActionComment = () => {
    closeActionsMenu();
    goComment();
};

const onActionCopyLink = () => {
    closeActionsMenu();
    copyLink();
};

const onActionDelete = () => {
    closeActionsMenu();
    removeTask();
};

watch(() => activeTask.value?.id, (id, prev) => {
    if (id != null && id !== prev) {
        tab.value = props.initialPanelTab || 'overview';
    }
    closeActionsMenu();
});

watch(tab, (key) => {
    emit('panel-tab-change', key);
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
const activityItems = computed(() => normalizeEntities(unref(ws.activityTimeline)));
const {
    paginatedItems: activityList,
    meta: activityPaginationMeta,
    perPage: activityPerPage,
    setPerPage: setActivityPerPage,
    goToPage: goActivityPage,
    PER_PAGE_OPTIONS: ACTIVITY_PER_PAGE_OPTIONS,
} = useClientPagination(activityItems, 'va-qlda.task.activity.perPage', 10);
const commentList = computed(() => normalizeEntities(activeTask.value?.comments));
const subtaskList = computed(() =>
    normalizeEntities(getDirectChildren(activeTask.value, props.allTasks)),
);
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
          <header
            class="shrink-0 border-b border-slate-200/80 bg-gradient-to-b from-slate-50/90 to-white dark:border-slate-800 dark:from-slate-900/50 dark:to-slate-950"
            :class="['border-l-4', statusStripeBorder]"
          >
            <div class="px-4 pb-3.5 pt-3">
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-400 dark:text-slate-500">
                    Chi tiết công việc
                  </p>
                  <p class="mt-0.5 font-mono text-xs font-semibold tracking-tight text-brand">
                    {{ taskDisplayId(activeTask) }}
                  </p>
                </div>
                <div class="flex shrink-0 items-center gap-0.5 rounded-lg border border-slate-200/90 bg-white/90 p-0.5 shadow-sm dark:border-slate-600 dark:bg-slate-800/90">
                  <div
                    ref="actionsTriggerRef"
                    class="relative"
                  >
                    <button
                      type="button"
                      class="grid h-8 w-8 place-items-center rounded-md text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-700 dark:hover:text-slate-200"
                      :class="showActionsMenu ? 'bg-slate-100 text-brand dark:bg-slate-700' : ''"
                      title="Thao tác"
                      aria-haspopup="menu"
                      :aria-expanded="showActionsMenu"
                      @click.stop="toggleActionsMenu"
                    >
                      <AppIcon
                        name="more-vertical"
                        :size="16"
                      />
                    </button>
                    <div
                      v-if="showActionsMenu"
                      ref="actionsMenuRef"
                      class="absolute right-0 top-full z-30 mt-1 w-56 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-slate-900"
                      role="menu"
                      @click.stop
                    >
                      <template v-if="!actionsSubMenu">
                        <button
                          v-if="canEdit"
                          type="button"
                          class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800"
                          role="menuitem"
                          @click="onActionEdit"
                        >
                          <AppIcon
                            name="edit"
                            :size="14"
                          />
                          Chỉnh sửa
                        </button>
                        <button
                          v-if="canChangeStatus"
                          type="button"
                          class="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800"
                          role="menuitem"
                          @click="actionsSubMenu = 'status'"
                        >
                          <span class="flex min-w-0 items-center gap-2">
                            <AppIcon
                              name="task"
                              :size="14"
                            />
                            <span class="truncate">Đổi trạng thái</span>
                          </span>
                          <span class="shrink-0 text-[10px] text-slate-400">{{ activeTask.status?.label }}</span>
                        </button>
                        <button
                          v-if="canEdit && !isSubtaskRow"
                          type="button"
                          class="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800"
                          role="menuitem"
                          @click="actionsSubMenu = 'assign'"
                        >
                          <span class="flex min-w-0 items-center gap-2">
                            <AppIcon
                              name="people"
                              :size="14"
                            />
                            <span class="truncate">Giao việc</span>
                          </span>
                          <span class="max-w-[7rem] shrink-0 truncate text-[10px] text-slate-400">{{ assigneeSummary || 'Chưa gán' }}</span>
                        </button>
                        <button
                          type="button"
                          class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800"
                          role="menuitem"
                          @click="onActionComment"
                        >
                          <AppIcon
                            name="comment"
                            :size="14"
                          />
                          Trao đổi
                        </button>
                        <button
                          type="button"
                          class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800"
                          role="menuitem"
                          @click="onActionCopyLink"
                        >
                          <AppIcon
                            name="copy"
                            :size="14"
                          />
                          Sao chép liên kết
                        </button>
                        <div
                          v-if="canDelete"
                          class="my-1 border-t border-slate-100 dark:border-slate-800"
                        />
                        <button
                          v-if="canDelete"
                          type="button"
                          class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/40"
                          role="menuitem"
                          @click="onActionDelete"
                        >
                          <AppIcon
                            name="delete"
                            :size="14"
                          />
                          Xoá công việc
                        </button>
                      </template>
                      <template v-else-if="actionsSubMenu === 'status'">
                        <button
                          type="button"
                          class="flex w-full items-center gap-1.5 border-b border-slate-100 px-3 py-2 text-left text-[11px] font-medium text-slate-500 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800"
                          @click="actionsSubMenu = null"
                        >
                          <AppIcon
                            name="chevron-left"
                            :size="14"
                          />
                          Trạng thái
                        </button>
                        <button
                          v-for="o in statusOptionList"
                          :key="o.value"
                          type="button"
                          class="flex w-full px-3 py-1.5 text-left text-xs hover:bg-slate-50 dark:hover:bg-slate-800"
                          :class="activeTask.status?.value === o.value ? 'font-semibold text-brand' : 'text-slate-600 dark:text-slate-300'"
                          @click="onStatusPick(o.value)"
                        >
                          {{ o.label }}
                        </button>
                      </template>
                      <template v-else-if="actionsSubMenu === 'assign'">
                        <button
                          type="button"
                          class="flex w-full items-center gap-1.5 border-b border-slate-100 px-3 py-2 text-left text-[11px] font-medium text-slate-500 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800"
                          @click="actionsSubMenu = null"
                        >
                          <AppIcon
                            name="chevron-left"
                            :size="14"
                          />
                          Người thực hiện
                        </button>
                        <div class="border-b border-slate-100 p-1.5 dark:border-slate-800">
                          <input
                            ref="assignSearchRef"
                            v-model="assignMenuSearch"
                            type="text"
                            class="input w-full py-1 text-xs"
                            placeholder="Tìm theo tên…"
                          >
                        </div>
                        <div class="max-h-44 overflow-y-auto py-1">
                          <button
                            type="button"
                            class="w-full px-3 py-1.5 text-left text-xs text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-800"
                            @click="onAssignPick(null)"
                          >
                            Chưa gán
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
                      </template>
                    </div>
                  </div>
                  <button
                    type="button"
                    class="grid h-8 w-8 place-items-center rounded-md text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-700 dark:hover:text-slate-200"
                    :title="fullscreen ? 'Thu nhỏ panel' : 'Mở rộng toàn màn hình'"
                    @click="toggleFullscreen"
                  >
                    <AppIcon
                      :name="fullscreen ? 'collapse-left' : 'expand-left'"
                      :size="16"
                    />
                  </button>
                  <button
                    type="button"
                    class="grid h-8 w-8 place-items-center rounded-md text-slate-500 transition hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-700 dark:hover:text-rose-400"
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

              <h1 class="mt-3 font-display text-base font-semibold leading-snug tracking-tight text-slate-900 dark:text-slate-50">
                {{ activeTask.title }}
              </h1>

              <div
                v-if="progressPct > 0 && progressPct < 100"
                class="mt-2.5"
              >
                <div class="mb-1 flex items-center justify-between text-[10px] font-medium text-slate-500">
                  <span>Tiến độ</span>
                  <span class="tabular-nums">{{ progressPct }}%</span>
                </div>
                <ProgressBar
                  :value="progressPct"
                  :show-label="false"
                  height="h-1.5"
                />
              </div>

              <div
                v-if="activeTask.status?.label || activeTask.priority?.label || activeTask.phase?.label || completionBadge || ws.isOverdue"
                class="mt-3 flex flex-wrap items-center gap-2"
              >
                <Badge
                  v-if="activeTask.status?.label"
                  :label="activeTask.status.label"
                  :color="activeTask.status.color || 'slate'"
                />
                <Badge
                  v-if="activeTask.priority?.label"
                  :label="activeTask.priority.label"
                  :color="activeTask.priority.color || 'sky'"
                />
                <Badge
                  v-if="activeTask.phase?.label"
                  :label="activeTask.phase.label"
                  color="violet"
                />
                <span
                  v-if="completionBadge"
                  class="inline-flex rounded-full px-2.5 py-0.5 text-[10px] font-semibold"
                  :class="getTaskSlaToneClass(completionBadge.tone)"
                  :title="completionBadge.detail"
                >
                  {{ completionBadge.label }}
                </span>
                <span
                  v-if="ws.isOverdue"
                  class="inline-flex items-center gap-1 rounded-full bg-rose-100 px-2.5 py-0.5 text-[10px] font-semibold text-rose-700 dark:bg-rose-950/50 dark:text-rose-300"
                >
                  <AppIcon
                    name="alert"
                    :size="11"
                  />
                  Quá hạn
                </span>
              </div>

              <dl
                v-if="hasHeaderMeta"
                class="mt-2.5 space-y-1 text-[11px] leading-snug text-slate-600 dark:text-slate-400"
              >
                <div
                  v-if="unref(ws.sprintLine)"
                  class="flex min-w-0 items-start gap-1.5"
                >
                  <AppIcon
                    name="sprint"
                    :size="12"
                    class="mt-0.5 shrink-0 text-violet-500"
                  />
                  <span class="min-w-0 break-words">{{ unref(ws.sprintLine) }}</span>
                </div>
                <div
                  v-if="epicDisplay"
                  class="flex min-w-0 items-start gap-1.5"
                >
                  <AppIcon
                    name="flag"
                    :size="12"
                    class="mt-0.5 shrink-0 text-amber-500"
                  />
                  <span class="min-w-0 break-words">{{ epicDisplay }}</span>
                </div>
                <div
                  v-if="scheduleLine || estimateLine || loggedHoursLine"
                  class="flex flex-wrap items-center gap-x-3 gap-y-0.5 tabular-nums"
                >
                  <span
                    v-if="scheduleLine"
                    class="inline-flex items-center gap-1"
                    :class="ws.isOverdue ? 'font-semibold text-rose-600 dark:text-rose-400' : ''"
                  >
                    <AppIcon
                      name="calendar"
                      :size="12"
                      class="shrink-0 text-slate-400"
                    />
                    {{ scheduleLine }}
                  </span>
                  <span
                    v-if="estimateLine"
                    class="inline-flex items-center gap-1"
                  >
                    <AppIcon
                      name="worklog"
                      :size="12"
                      class="shrink-0 text-slate-400"
                    />
                    {{ estimateLine }}
                  </span>
                  <span v-if="loggedHoursLine">{{ loggedHoursLine }}</span>
                </div>
              </dl>

              <p
                v-if="activeTask.parent?.id"
                class="mt-2 rounded-lg border border-dashed border-slate-200/90 bg-slate-50/60 px-2.5 py-1.5 text-[11px] text-slate-600 dark:border-slate-700 dark:bg-slate-900/30 dark:text-slate-400"
              >
                Công việc con của
                <button
                  type="button"
                  class="font-semibold text-brand hover:underline"
                  @click="openSubtask(activeTask.parent)"
                >
                  #{{ activeTask.parent.id }} · {{ activeTask.parent.title }}
                </button>
              </p>
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
              <section
                v-if="progressPct > 0 || worklogList.length"
                class="rounded-lg border border-slate-200/80 p-3 dark:border-slate-700"
              >
                <div class="mb-2 flex items-center justify-between gap-2">
                  <span class="text-xs font-medium text-slate-500">Tiến độ</span>
                  <span class="text-sm font-semibold tabular-nums text-brand">{{ progressPct }}%</span>
                </div>
                <ProgressBar
                  :value="progressPct"
                  :show-label="false"
                  class="h-2"
                />
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
              <section
                v-if="activeTask.reporter || assigneeList.length || activeTask.reviewer || watcherList.length"
                class="rounded-lg border border-slate-200/80 p-3 dark:border-slate-700"
              >
                <div class="mb-2 flex items-center justify-between gap-2">
                  <h3 class="text-xs font-semibold text-slate-500">
                    Phân công
                  </h3>
                  <button
                    v-if="canEdit"
                    type="button"
                    class="text-[11px] font-medium text-brand hover:underline"
                    @click="emit('edit', activeTask)"
                  >
                    Quản lý
                  </button>
                </div>
                <div class="space-y-2">
                  <div
                    v-if="activeTask.reporter"
                    class="flex items-center gap-2.5"
                  >
                    <Avatar
                      :name="activeTask.reporter.name"
                      :src="activeTask.reporter.avatar_path"
                      :size="28"
                    />
                    <div class="min-w-0">
                      <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-100">
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
                    class="flex items-center gap-2.5"
                  >
                    <Avatar
                      :name="a.name"
                      :src="a.avatar_path"
                      :size="28"
                    />
                    <div class="min-w-0">
                      <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-100">
                        {{ a.name }}
                      </p>
                      <p class="text-[10px] uppercase text-slate-400">
                        Người thực hiện
                      </p>
                    </div>
                  </div>
                  <div
                    v-if="activeTask.reviewer"
                    class="flex items-center gap-2.5"
                  >
                    <Avatar
                      :name="activeTask.reviewer.name"
                      :src="activeTask.reviewer.avatar_path"
                      :size="28"
                    />
                    <div class="min-w-0">
                      <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-100">
                        {{ activeTask.reviewer.name }}
                      </p>
                      <p class="text-[10px] uppercase text-slate-400">
                        Người duyệt
                      </p>
                    </div>
                  </div>
                  <div
                    v-if="watcherList.length"
                    class="border-t border-slate-100 pt-2 dark:border-slate-800"
                  >
                    <p class="mb-1.5 text-[10px] uppercase text-slate-400">
                      Người theo dõi
                    </p>
                    <div class="flex flex-wrap gap-1">
                      <span
                        v-for="w in watcherList"
                        :key="w.id"
                        class="inline-flex items-center gap-1 rounded-full bg-slate-50 px-2 py-0.5 text-[11px] dark:bg-slate-800"
                      >
                        <Avatar
                          :name="w.name"
                          :src="w.avatar_path"
                          :size="16"
                        />
                        {{ w.name }}
                      </span>
                    </div>
                  </div>
                </div>
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
                :poll-active="tab === 'collaboration'"
              />
            </div>

            <!-- ACTIVITY -->
            <div v-show="tab === 'activity'">
              <div class="space-y-0">
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
                    <p
                      v-if="ev.type === 'completed' && ev.meta"
                      class="mt-1 text-xs text-slate-500"
                    >
                      ƯT {{ ev.meta.estimate_hours ?? '—' }}h · TT {{ ev.meta.actual_hours ?? '—' }}h
                      <span v-if="ev.meta.sla_result"> · SLA {{ ev.meta.sla_result === 'met' ? 'đạt' : 'vượt' }}</span>
                    </p>
                  </div>
                </div>
                <p
                  v-if="!activityItems.length"
                  class="py-8 text-center text-sm text-slate-400"
                >
                  Chưa có hoạt động ghi nhận.
                </p>
              </div>
              <DatagridPaginationFooter
                v-if="activityPaginationMeta.total"
                variant="bar"
                client
                :meta="activityPaginationMeta"
                :per-page="activityPerPage"
                :per-page-options="ACTIVITY_PER_PAGE_OPTIONS"
                @update:per-page="setActivityPerPage"
                @page-change="goActivityPage"
              />
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
