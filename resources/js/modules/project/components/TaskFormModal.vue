<script setup>
import { ref, watch, computed, inject, onUnmounted } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Ui/Modal.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import AppIcon from '@/Components/AppIcon.vue';
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

const toggleAssignee = (id) => {
    const idx = form.assignee_ids.indexOf(id);
    if (idx === -1) form.assignee_ids = [...form.assignee_ids, id];
    else form.assignee_ids = form.assignee_ids.filter((x) => x !== id);
};

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
    max-width="max-w-6xl"
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
      class="space-y-3"
      @submit.prevent="submit"
    >
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="space-y-3">
          <div>
            <label class="label">Tiêu đề <span class="text-rose-500">*</span></label>
            <input
              v-model="form.title"
              type="text"
              class="input"
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
              rows="3"
              class="input resize-none"
              placeholder="Yêu cầu, tiêu chí hoàn thành, tài liệu tham khảo…"
            />
          </div>

          <div class="rounded-card border border-slate-200 p-3 dark:border-slate-700">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
              Nhân sự
            </p>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div class="sm:col-span-2">
                <label class="label">
                  Người thực hiện
                  <span
                    class="ml-1 cursor-help text-slate-400"
                    title="Có thể chọn nhiều người cùng thực hiện công việc này."
                  >ⓘ</span>
                </label>
                <div class="flex flex-wrap gap-2">
                  <label
                    v-for="e in employees"
                    :key="e.id"
                    class="flex cursor-pointer items-center gap-1.5 rounded-full border px-2.5 py-1 text-sm transition
                                               has-[:checked]:border-brand has-[:checked]:bg-brand-50 has-[:checked]:text-brand-700
                                               border-slate-200 text-slate-600 hover:border-slate-300"
                  >
                    <input
                      type="checkbox"
                      class="sr-only"
                      :checked="form.assignee_ids.includes(e.id)"
                      @change="toggleAssignee(e.id)"
                    >
                    <Avatar
                      :name="e.name"
                      :src="e.avatar_path"
                      :size="20"
                    />
                    <span>{{ e.name }}</span>
                  </label>
                </div>
              </div>

              <div>
                <label class="label">Người giao việc</label>
                <select
                  v-model="form.reporter_id"
                  class="input"
                >
                  <option :value="null">
                    — Chưa chọn —
                  </option>
                  <option
                    v-for="e in employees"
                    :key="e.id"
                    :value="e.id"
                  >
                    {{ e.name }}
                  </option>
                </select>
              </div>

              <div>
                <label class="label">Người duyệt / Kiểm tra</label>
                <select
                  v-model="form.reviewer_id"
                  class="input"
                >
                  <option :value="null">
                    — Chưa chọn —
                  </option>
                  <option
                    v-for="e in employees"
                    :key="e.id"
                    :value="e.id"
                  >
                    {{ e.name }}
                  </option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <div class="space-y-3">
          <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div>
              <label class="label">Trạng thái <span class="text-rose-500">*</span></label>
              <select
                v-model="form.status"
                class="input"
                @change="syncProgressFromStatus"
              >
                <option
                  v-for="o in statusOptions"
                  :key="o.value"
                  :value="o.value"
                >
                  {{ o.label }}
                </option>
              </select>
            </div>
            <div>
              <label class="label">Ưu tiên <span class="text-rose-500">*</span></label>
              <select
                v-model="form.priority"
                class="input"
              >
                <option
                  v-for="o in priorityOptions"
                  :key="o.value"
                  :value="o.value"
                >
                  {{ o.label }}
                </option>
              </select>
            </div>
            <div>
              <label class="label">Sprint</label>
              <select
                v-model="form.sprint_id"
                class="input"
              >
                <option :value="null">
                  — Không gán —
                </option>
                <option
                  v-for="s in sprints"
                  :key="s.id"
                  :value="s.id"
                >
                  {{ s.name }}
                </option>
              </select>
            </div>
            <div>
              <label class="label">Giai đoạn (Phase)</label>
              <select
                v-model="form.phase"
                class="input"
              >
                <option
                  v-for="p in phaseOptions"
                  :key="p.value"
                  :value="p.value"
                >
                  {{ p.label }}
                </option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
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
                class="w-full accent-brand"
              >
            </div>
          </div>

          <div v-if="dependencyOptions.length">
            <label class="label">Phụ thuộc vào</label>
            <div class="max-h-32 space-y-1 overflow-y-auto rounded-input border border-slate-200 bg-white p-2 dark:border-slate-700 dark:bg-slate-900">
              <label
                v-for="t in dependencyOptions"
                :key="t.id"
                class="flex items-center gap-2 text-sm text-slate-600"
              >
                <input
                  v-model="form.dependencies"
                  type="checkbox"
                  :value="t.id"
                  class="rounded accent-brand"
                >
                {{ t.title }}
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
