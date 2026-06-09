<script setup>
import { ref, watch, computed, inject, onUnmounted, nextTick } from 'vue';
import SearchSelect from '@/shared/ui/SearchSelect.vue';
import { valueLabelOptions } from '@/shared/utils/selectOptions';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Ui/Modal.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PersonSelect from '@/modules/project/components/PersonSelect.vue';
import PersonMultiSelect from '@/modules/project/components/PersonMultiSelect.vue';
import TaskFormBulkPanel from '@/modules/project/components/TaskFormBulkPanel.vue';
import { useModalFormDraft, shallowPickDraft, draftHasMeaningfulContent } from '@/composables/useModalFormDraft';
import {
    buildDraftSaveMeta,
    restoreModalDraft,
} from '@/composables/useModalDraftHelpers';

const props = defineProps({
    show: { type: Boolean, default: false },
    projectId: { type: Number, required: true },
    task: { type: Object, default: null },
    sprints: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
    tasks: { type: Array, default: () => [] },
    statusOptions: { type: Array, default: () => [] },
    priorityOptions: { type: Array, default: () => [] },
    phaseOptions: { type: Array, default: () => [] },
    defaultStatus: { type: String, default: 'todo' },
});

const emit = defineEmits(['close', 'saved']);
const modalClose = inject('modalClose', () => emit('close'));

const createMode = ref('single'); // single | bulk
const bulkDirty = ref(false);
const bulkPanelRef = ref(null);

const modalDirty = computed(() => form.isDirty || (createMode.value === 'bulk' && bulkDirty.value));

const form = useForm({
    title: '',
    description: '',
    sprint_id: null,
    status: 'todo',
    priority: 'medium',
    assignee_id: null,
    assignee_ids: [],
    reporter_id: null,
    reviewer_id: null,
    start_date: null,
    due_date: null,
    estimate_hours: null,
    progress_percent: 0,
    phase: 'development',
    is_milestone: false,
    dependencies: [],
    epic_id: null,
    story_points: null,
});

const taskDraftScope = computed(() => (
    props.task ? `edit.${props.task.id}` : `create.${props.projectId}`
));

const TASK_DRAFT_FIELDS = [
    'title', 'description', 'sprint_id', 'status', 'priority',
    'assignee_id', 'assignee_ids', 'reporter_id', 'reviewer_id',
    'start_date', 'due_date', 'estimate_hours', 'progress_percent',
    'phase', 'is_milestone', 'dependencies', 'epic_id', 'story_points',
];

const formDraft = useModalFormDraft('task', {
    getScope: () => taskDraftScope.value,
    pick: () => {
        const base = shallowPickDraft(form.data(), TASK_DRAFT_FIELDS);
        return {
            ...base,
            createMode: createMode.value,
            bulk: createMode.value === 'bulk'
                ? bulkPanelRef.value?.getDraftSnapshot?.() ?? null
                : null,
        };
    },
    hasContent: (data) => draftHasMeaningfulContent(data, { signalKeys: ['title', 'description'] })
        || (data.bulk && (
            (data.bulk.bulkText ?? '').trim().length > 0
            || (data.bulk.bulkRows ?? []).length > 0
        )),
});

const applyFormDraft = async (data, meta) => {
    form.title = data.title ?? '';
    form.description = data.description ?? '';
    form.sprint_id = data.sprint_id ?? null;
    form.status = data.status ?? props.defaultStatus;
    form.priority = data.priority ?? 'medium';
    form.assignee_id = data.assignee_id ?? null;
    form.assignee_ids = [...(data.assignee_ids ?? [])];
    form.reporter_id = data.reporter_id ?? null;
    form.reviewer_id = data.reviewer_id ?? null;
    form.start_date = data.start_date ?? null;
    form.due_date = data.due_date ?? null;
    form.estimate_hours = data.estimate_hours ?? null;
    form.progress_percent = data.progress_percent ?? 0;
    form.phase = data.phase ?? 'development';
    form.is_milestone = !!data.is_milestone;
    form.dependencies = [...(data.dependencies ?? [])];
    form.epic_id = data.epic_id ?? null;
    form.story_points = data.story_points ?? null;
    const mode = meta?.createMode ?? data.createMode;
    if (mode) createMode.value = mode;
    await nextTick();
    const bulkSnap = meta?.bulk ?? data.bulk;
    if (bulkSnap && createMode.value === 'bulk') {
        bulkPanelRef.value?.applyDraftSnapshot?.(bulkSnap);
    }
};

const saveDraftOnClose = () => {
    formDraft.saveOnClose({}, buildDraftSaveMeta(props.task, { createMode: createMode.value }));
};

const existingTitles = computed(() => props.tasks.map((t) => t.title).filter(Boolean));

const bulkInitialDefaults = computed(() => ({
    sprint_id: form.sprint_id,
    status: form.status,
    priority: form.priority,
    phase: form.phase,
    assignee_ids: [...form.assignee_ids],
    reviewer_id: form.reviewer_id,
    reporter_id: form.reporter_id,
}));

const modalTitle = computed(() => {
    if (props.task) return 'Chỉnh sửa công việc';
    return createMode.value === 'bulk' ? 'Thêm nhiều công việc' : 'Thêm công việc';
});

const modalMaxWidth = computed(() => {
    if (createMode.value === 'bulk' && !props.task) return 'max-w-5xl';
    return 'max-w-4xl';
});

const handleKeydown = (e) => {
    if (!props.show) return;
    if (e.key === 'Escape') {
        e.preventDefault();
        modalClose();
        return;
    }
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter' && createMode.value === 'single' && !props.task) {
        e.preventDefault();
        submit();
    }
};

watch(() => props.show, async (open) => {
    if (open) {
        document.addEventListener('keydown', handleKeydown);
        createMode.value = 'single';
        bulkDirty.value = false;
        form.clearErrors();
        const epoch = formDraft.bumpOpenEpoch();
        if (props.task) {
            form.title = props.task.title;
            form.description = props.task.description ?? '';
            form.sprint_id = props.task.sprint_id;
            form.status = props.task.status.value;
            form.priority = props.task.priority.value;
            form.assignee_id = props.task.assignee?.id ?? null;
            form.assignee_ids = (props.task.assignees ?? []).map((a) => a.id);
            form.reporter_id = props.task.reporter?.id ?? null;
            form.reviewer_id = props.task.reviewer?.id ?? null;
            form.start_date = props.task.start_date;
            form.due_date = props.task.due_date;
            form.estimate_hours = props.task.estimate_hours;
            form.progress_percent = props.task.progress;
            form.phase = props.task.phase?.value || props.task.phase || 'development';
            form.is_milestone = !!props.task.is_milestone;
            form.dependencies = (props.task.dependencies ?? []).map((d) => (typeof d === 'object' && d !== null ? d.id : d));
            form.epic_id = props.task.epic_id ?? props.task.epic?.id ?? null;
            form.story_points = props.task.story_points ?? null;
            await restoreModalDraft(formDraft, {
                isActive: () => props.show,
                openEpoch: epoch,
                entity: props.task,
                applyDraft: applyFormDraft,
                form,
            });
        } else {
            form.reset();
            form.status = props.defaultStatus;
            await restoreModalDraft(formDraft, {
                isActive: () => props.show,
                openEpoch: epoch,
                entity: null,
                applyDraft: applyFormDraft,
                form,
            });
        }
    } else {
        document.removeEventListener('keydown', handleKeydown);
        bulkPanelRef.value?.reset?.();
    }
});

const submit = () => {
    form.transform(({ progress_percent, ...data }) => ({
        ...data,
        progress: progress_percent,
    }));

    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            formDraft.clear();
            emit('saved');
            emit('close');
        },
    };
    if (props.task) {
        form.put(`/projects/${props.projectId}/tasks/${props.task.id}`, opts);
    } else {
        form.post(`/projects/${props.projectId}/tasks`, opts);
    }
};

const onBulkSaved = () => {
    formDraft.clear();
    emit('saved');
    emit('close');
};

const dependencyOptions = computed(() => props.tasks.filter((t) => !props.task || t.id !== props.task.id));

const statusSelectOptions = computed(() => valueLabelOptions(props.statusOptions));
const prioritySelectOptions = computed(() => valueLabelOptions(props.priorityOptions));
const phaseSelectOptions = computed(() => valueLabelOptions(props.phaseOptions));

const syncProgressFromStatus = () => {
    if (form.status === 'done') form.progress_percent = 100;
    else if (form.status === 'todo' || form.status === 'blocked') form.progress_percent = 0;
};

onUnmounted(() => document.removeEventListener('keydown', handleKeydown));
</script>

<template>
  <Modal
    :show="show"
    :dirty="modalDirty"
    :title="modalTitle"
    :max-width="modalMaxWidth"
    :on-save-draft="saveDraftOnClose"
    @close="emit('close')"
  >
    <!-- Mode tabs (create only) -->
    <div
      v-if="!task"
      class="mb-3 flex rounded-lg border border-slate-200 bg-slate-50 p-0.5 dark:border-slate-700 dark:bg-slate-900/50"
    >
      <button
        type="button"
        class="flex flex-1 items-center justify-center gap-1.5 rounded-md px-2 py-1.5 text-xs font-semibold transition"
        :class="createMode === 'single'
          ? 'bg-white text-brand shadow-sm dark:bg-slate-800'
          : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
        @click="createMode = 'single'"
      >
        <AppIcon
          name="task"
          :size="16"
        />
        Một công việc
      </button>
      <button
        type="button"
        class="flex flex-1 items-center justify-center gap-1.5 rounded-md px-2 py-1.5 text-xs font-semibold transition"
        :class="createMode === 'bulk'
          ? 'bg-white text-brand shadow-sm dark:bg-slate-800'
          : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
        @click="createMode = 'bulk'"
      >
        <AppIcon
          name="template"
          :size="16"
        />
        Nhiều công việc
        <span class="rounded-full bg-brand/10 px-1.5 py-0.5 text-[10px] font-bold uppercase text-brand">Nhanh</span>
      </button>
    </div>

    <TaskFormBulkPanel
      v-if="!task && createMode === 'bulk'"
      ref="bulkPanelRef"
      :project-id="projectId"
      :sprints="sprints"
      :employees="employees"
      :status-options="statusOptions"
      :priority-options="priorityOptions"
      :phase-options="phaseOptions"
      :existing-titles="existingTitles"
      :default-status="defaultStatus"
      :initial-defaults="bulkInitialDefaults"
      @saved="onBulkSaved"
      @dirty-change="bulkDirty = $event"
    />

    <!-- Single create / edit -->
    <form
      v-else
      class="space-y-3"
      @submit.prevent="submit"
    >
      <div>
        <label class="label">Tiêu đề <span class="text-rose-500">*</span></label>
        <input
          v-model="form.title"
          type="text"
          class="input"
          placeholder="Mô tả ngắn công việc…"
          autofocus
        >
        <p
          v-if="form.errors.title"
          class="mt-1 text-xs text-danger"
        >
          {{ form.errors.title }}
        </p>
      </div>
      <div class="grid gap-6 lg:grid-cols-2 lg:gap-x-8">
        <div class="space-y-3">
          <div>
            <label class="label">Mô tả</label>
            <textarea
              v-model="form.description"
              :rows="task ? 4 : 5"
              class="input min-h-[7.5rem] resize-y lg:min-h-[10rem]"
              placeholder="Yêu cầu, tiêu chí hoàn thành…"
            />
          </div>

          <div class="grid gap-3 sm:grid-cols-2">
            <div>
              <label class="label">Trạng thái <span class="text-rose-500">*</span></label>
              <SearchSelect
                v-model="form.status"
                :options="statusSelectOptions"
                placeholder="Trạng thái…"
                :clearable="false"
                @update:model-value="syncProgressFromStatus"
              />
            </div>
            <div>
              <label class="label">Ưu tiên <span class="text-rose-500">*</span></label>
              <SearchSelect
                v-model="form.priority"
                :options="prioritySelectOptions"
                placeholder="Ưu tiên…"
                :clearable="false"
              />
            </div>
            <div>
              <label class="label">Sprint</label>
              <SearchSelect
                v-model="form.sprint_id"
                :options="sprints"
                placeholder="Chọn sprint…"
                search-placeholder="Tìm sprint…"
              />
            </div>
            <div>
              <label class="label">Giai đoạn</label>
              <SearchSelect
                v-model="form.phase"
                :options="phaseSelectOptions"
                placeholder="Phase…"
                :clearable="false"
              />
            </div>
          </div>
        </div>

        <div class="space-y-3 lg:border-l lg:border-slate-100 lg:pl-8 dark:lg:border-slate-800">
          <div class="grid gap-3 sm:grid-cols-2">
            <div>
              <label class="label">Ngày bắt đầu</label>
              <input
                v-model="form.start_date"
                type="date"
                class="input"
              >
            </div>
            <div>
              <label class="label">Ngày kết thúc</label>
              <input
                v-model="form.due_date"
                type="date"
                class="input"
              >
              <p
                v-if="form.errors.due_date"
                class="mt-1 text-xs text-danger"
              >
                {{ form.errors.due_date }}
              </p>
            </div>
            <div class="sm:col-span-2">
              <label class="label">Ước lượng (giờ)</label>
              <input
                v-model="form.estimate_hours"
                type="number"
                step="0.5"
                min="0"
                class="input max-w-xs"
                placeholder="8"
              >
            </div>
            <div
              v-if="task"
              class="sm:col-span-2"
            >
              <label class="label">Tiến độ: <strong class="text-brand">{{ form.progress_percent }}%</strong></label>
              <input
                v-model.number="form.progress_percent"
                type="range"
                min="0"
                max="100"
                step="5"
                class="mt-1.5 w-full accent-brand"
              >
            </div>
          </div>

          <div class="space-y-3 border-t border-slate-100 pt-3 dark:border-slate-800">
            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
              Nhân sự
            </p>
            <div>
              <label class="label">
                Người thực hiện
                <span
                  class="ml-1 cursor-help text-slate-400"
                  title="Có thể chọn nhiều người cùng thực hiện."
                >ⓘ</span>
              </label>
              <PersonMultiSelect
                v-model="form.assignee_ids"
                :options="employees"
                placeholder="Thêm người thực hiện…"
              />
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
              <div>
                <label class="label">Người giao việc</label>
                <PersonSelect
                  v-model="form.reporter_id"
                  :options="employees"
                  placeholder="Người giao…"
                />
              </div>
              <div>
                <label class="label">Người duyệt</label>
                <PersonSelect
                  v-model="form.reviewer_id"
                  :options="employees"
                  placeholder="Người duyệt…"
                />
              </div>
            </div>
          </div>

          <div
            v-if="dependencyOptions.length"
            class="border-t border-slate-100 pt-3 dark:border-slate-800"
          >
            <label class="label">Phụ thuộc vào</label>
            <div class="mt-1.5 grid max-h-36 gap-1.5 overflow-y-auto">
              <label
                v-for="t in dependencyOptions"
                :key="t.id"
                class="flex cursor-pointer items-start gap-2 rounded-md border border-slate-100 px-2 py-1.5 text-xs text-slate-600 transition hover:border-brand/30 dark:border-slate-700 dark:hover:border-brand/40"
              >
                <input
                  v-model="form.dependencies"
                  type="checkbox"
                  :value="t.id"
                  class="mt-0.5 rounded accent-brand"
                >
                <span class="min-w-0 leading-snug">{{ t.title }}</span>
              </label>
            </div>
          </div>
        </div>
      </div>

      <div class="flex justify-end gap-2 border-t border-slate-100 pt-3 dark:border-slate-800">
        <button
          type="button"
          class="btn-ghost"
          @click="modalClose()"
        >
          Huỷ
        </button>
        <button
          type="submit"
          class="btn-primary"
          :disabled="form.processing"
        >
          {{ task ? 'Lưu thay đổi' : 'Thêm công việc' }}
        </button>
      </div>
    </form>
  </Modal>
</template>
