<script setup>
import { ref, watch, computed, inject, onUnmounted } from 'vue';
import SearchSelect from '@/shared/ui/SearchSelect.vue';
import { valueLabelOptions } from '@/shared/utils/selectOptions';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Ui/Modal.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PersonSelect from '@/modules/project/components/PersonSelect.vue';
import PersonMultiSelect from '@/modules/project/components/PersonMultiSelect.vue';
import TaskFormBulkPanel from '@/modules/project/components/TaskFormBulkPanel.vue';

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

watch(() => props.show, (open) => {
    if (open) {
        document.addEventListener('keydown', handleKeydown);
        createMode.value = 'single';
        bulkDirty.value = false;
        form.clearErrors();
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
        } else {
            form.reset();
            form.status = props.defaultStatus;
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
        onSuccess: () => { emit('saved'); emit('close'); },
    };
    if (props.task) {
        form.put(`/projects/${props.projectId}/tasks/${props.task.id}`, opts);
    } else {
        form.post(`/projects/${props.projectId}/tasks`, opts);
    }
};

const onBulkSaved = () => {
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
    max-width="max-w-[min(96rem,calc(100vw-2rem))]"
    @close="emit('close')"
  >
    <!-- Mode tabs (create only) -->
    <div
      v-if="!task"
      class="mb-5 flex rounded-lg border border-slate-200 bg-slate-50 p-1 dark:border-slate-700 dark:bg-slate-900/50"
    >
      <button
        type="button"
        class="flex flex-1 items-center justify-center gap-2 rounded-md px-3 py-2 text-sm font-semibold transition"
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
        class="flex flex-1 items-center justify-center gap-2 rounded-md px-3 py-2 text-sm font-semibold transition"
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
      class="space-y-5"
      @submit.prevent="submit"
    >
      <!-- Nội dung -->
      <section class="space-y-4">
        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
          Nội dung công việc
        </p>
        <div>
          <label class="label">Tiêu đề <span class="text-rose-500">*</span></label>
          <input
            v-model="form.title"
            type="text"
            class="input text-base"
            placeholder="Mô tả ngắn gọn công việc cần làm…"
          >
          <p
            v-if="form.errors.title"
            class="mt-1 text-xs text-danger"
          >
            {{ form.errors.title }}
          </p>
        </div>
        <div>
          <label class="label">Mô tả chi tiết</label>
          <textarea
            v-model="form.description"
            rows="4"
            class="input min-h-[6rem] resize-y"
            placeholder="Yêu cầu, tiêu chí hoàn thành, tài liệu tham khảo…"
          />
        </div>
      </section>

      <div class="grid grid-cols-1 gap-5 xl:grid-cols-2">
        <!-- Phân loại & thời gian -->
        <section class="space-y-4 rounded-xl border border-slate-200 bg-slate-50/60 p-4 dark:border-slate-700 dark:bg-slate-900/40">
          <p class="flex items-center gap-2 text-[10px] font-bold uppercase tracking-wider text-slate-400">
            <AppIcon
              name="settings"
              :size="14"
            />
            Phân loại & thời gian
          </p>
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label class="label">Trạng thái <span class="text-rose-500">*</span></label>
              <SearchSelect
                v-model="form.status"
                :options="statusSelectOptions"
                placeholder="Chọn trạng thái…"
                :clearable="false"
                @update:model-value="syncProgressFromStatus"
              />
            </div>
            <div>
              <label class="label">Ưu tiên <span class="text-rose-500">*</span></label>
              <SearchSelect
                v-model="form.priority"
                :options="prioritySelectOptions"
                placeholder="Chọn ưu tiên…"
                :clearable="false"
              />
            </div>
            <div>
              <label class="label">Sprint</label>
              <SearchSelect
                v-model="form.sprint_id"
                :options="sprints"
                placeholder="Tìm & chọn sprint…"
                search-placeholder="Tìm sprint…"
              />
            </div>
            <div>
              <label class="label">Giai đoạn (Phase)</label>
              <SearchSelect
                v-model="form.phase"
                :options="phaseSelectOptions"
                placeholder="Chọn giai đoạn…"
                :clearable="false"
              />
            </div>
          </div>
          <div class="grid grid-cols-1 gap-4 border-t border-slate-200/80 pt-4 sm:grid-cols-2 dark:border-slate-700">
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
            <div>
              <label class="label">Ước lượng (giờ)</label>
              <input
                v-model="form.estimate_hours"
                type="number"
                step="0.5"
                min="0"
                class="input"
                placeholder="8"
              >
            </div>
            <div>
              <label class="label">Tiến độ: <strong class="text-brand">{{ form.progress_percent }}%</strong></label>
              <input
                v-model.number="form.progress_percent"
                type="range"
                min="0"
                max="100"
                step="5"
                class="mt-3 w-full accent-brand"
              >
            </div>
          </div>
        </section>

        <!-- Nhân sự -->
        <section class="space-y-4 rounded-xl border border-slate-200 bg-slate-50/60 p-4 dark:border-slate-700 dark:bg-slate-900/40">
          <p class="flex items-center gap-2 text-[10px] font-bold uppercase tracking-wider text-slate-400">
            <AppIcon
              name="people"
              :size="14"
            />
            Nhân sự
          </p>
          <div>
            <label class="label">
              Người thực hiện
              <span
                class="ml-1 cursor-help text-slate-400"
                title="Có thể chọn nhiều người cùng thực hiện công việc này."
              >ⓘ</span>
            </label>
            <PersonMultiSelect
              v-model="form.assignee_ids"
              :options="employees"
              placeholder="Tìm & thêm người thực hiện…"
            />
          </div>
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label class="label">Người giao việc</label>
              <PersonSelect
                v-model="form.reporter_id"
                :options="employees"
                placeholder="Tìm & chọn người giao việc…"
              />
            </div>
            <div>
              <label class="label">Người duyệt / Kiểm tra</label>
              <PersonSelect
                v-model="form.reviewer_id"
                :options="employees"
                placeholder="Tìm & chọn người duyệt…"
              />
            </div>
          </div>
        </section>
      </div>

      <section
        v-if="dependencyOptions.length"
        class="rounded-xl border border-slate-200 p-4 dark:border-slate-700"
      >
        <label class="label">Phụ thuộc vào</label>
        <div class="mt-2 grid max-h-40 gap-2 overflow-y-auto sm:grid-cols-2 lg:grid-cols-3">
          <label
            v-for="t in dependencyOptions"
            :key="t.id"
            class="flex cursor-pointer items-start gap-2 rounded-lg border border-slate-100 bg-white px-3 py-2 text-sm text-slate-600 transition hover:border-brand/30 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-brand/40"
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
      </section>

      <div class="flex justify-end gap-2 border-t border-slate-100 pt-4 dark:border-slate-800">
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
