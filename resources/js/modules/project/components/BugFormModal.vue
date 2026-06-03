<script setup>
import { ref, computed, inject, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Ui/Modal.vue';
import PersonSelect from '@/modules/project/components/PersonSelect.vue';
import SearchSelect from '@/shared/ui/SearchSelect.vue';
import { valueLabelOptions } from '@/shared/utils/selectOptions';

const props = defineProps({
    show: { type: Boolean, default: false },
    bug: { type: Object, default: null },
    projects: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
    severityOptions: { type: Array, default: () => [] },
    statusOptions: { type: Array, default: () => [] },
    priorityOptions: { type: Array, default: () => [] },
    defaultProjectId: { type: Number, default: null },
});

const emit = defineEmits(['close', 'saved']);
const modalClose = inject('modalClose', () => emit('close'));

const reporterType = ref('internal');
const form = useForm({
    project_id: null, title: '', description: '', steps_to_reproduce: '', expected: '', actual: '',
    environment: '', severity: 'major', priority: 'medium', status: 'open',
    reporter_employee_id: null, reporter_name: '', reporter_email: '', assignee_id: null,
});

watch(() => props.show, (open) => {
    if (!open) return;
    form.clearErrors();
    if (props.bug) {
        form.project_id = props.bug.project_id;
        form.title = props.bug.title;
        form.description = props.bug.description ?? '';
        form.steps_to_reproduce = props.bug.steps_to_reproduce ?? '';
        form.expected = props.bug.expected ?? '';
        form.actual = props.bug.actual ?? '';
        form.environment = props.bug.environment ?? '';
        form.severity = props.bug.severity.value;
        form.priority = props.bug.priority.value;
        form.status = props.bug.status.value;
        form.assignee_id = props.bug.assignee?.id ?? null;
    } else {
        form.reset();
        form.project_id = props.defaultProjectId;
        reporterType.value = 'internal';
    }
});

watch(reporterType, (t) => {
    if (t === 'internal') { form.reporter_name = ''; form.reporter_email = ''; }
    else { form.reporter_employee_id = null; }
});

const severitySelectOptions = computed(() => valueLabelOptions(props.severityOptions));
const statusSelectOptions = computed(() => valueLabelOptions(props.statusOptions));
const prioritySelectOptions = computed(() => valueLabelOptions(props.priorityOptions));

const submit = () => {
    const opts = { preserveScroll: true, onSuccess: () => { emit('saved'); emit('close'); } };
    if (props.bug) form.put(`/bugs/${props.bug.id}`, opts);
    else form.post('/bugs', opts);
};
</script>

<template>
  <Modal
    :show="show"
    :dirty="form.isDirty"
    :title="bug ? `Chỉnh sửa ${bug.code}` : 'Báo lỗi mới'"
    max-width="max-w-2xl"
    @close="emit('close')"
  >
    <form
      class="space-y-4"
      @submit.prevent="submit"
    >
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="label">Dự án</label>
          <SearchSelect
            v-model="form.project_id"
            :options="projects"
            placeholder="Tìm & chọn dự án…"
            search-placeholder="Tìm dự án…"
          />
          <p
            v-if="form.errors.project_id"
            class="mt-1 text-xs text-danger"
          >
            {{ form.errors.project_id }}
          </p>
        </div>
        <div>
          <label class="label">Người sửa</label>
          <PersonSelect
            v-model="form.assignee_id"
            :options="employees"
            placeholder="Tìm & chọn người sửa…"
          />
        </div>
      </div>

      <div>
        <label class="label">Tiêu đề</label>
        <input
          v-model="form.title"
          type="text"
          class="input"
        >
        <p
          v-if="form.errors.title"
          class="mt-1 text-xs text-danger"
        >
          {{ form.errors.title }}
        </p>
      </div>

      <div>
        <label class="label">Mô tả</label>
        <textarea
          v-model="form.description"
          rows="2"
          class="input resize-none"
        />
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="label">Các bước tái hiện</label>
          <textarea
            v-model="form.steps_to_reproduce"
            rows="2"
            class="input resize-none"
          />
        </div>
        <div class="space-y-2">
          <input
            v-model="form.expected"
            type="text"
            class="input"
            placeholder="Kết quả mong đợi"
          >
          <input
            v-model="form.actual"
            type="text"
            class="input"
            placeholder="Kết quả thực tế"
          >
        </div>
      </div>

      <div class="grid grid-cols-3 gap-4">
        <div>
          <label class="label">Mức độ</label>
          <SearchSelect
            v-model="form.severity"
            :options="severitySelectOptions"
            placeholder="Chọn mức độ…"
            :clearable="false"
          />
        </div>
        <div>
          <label class="label">Ưu tiên</label>
          <SearchSelect
            v-model="form.priority"
            :options="prioritySelectOptions"
            placeholder="Chọn ưu tiên…"
            :clearable="false"
          />
        </div>
        <div>
          <label class="label">Trạng thái</label>
          <SearchSelect
            v-model="form.status"
            :options="statusSelectOptions"
            placeholder="Chọn trạng thái…"
            :clearable="false"
          />
        </div>
      </div>

      <div>
        <label class="label">Môi trường</label>
        <input
          v-model="form.environment"
          type="text"
          class="input"
          placeholder="Chrome 120 / Windows 11 …"
        >
      </div>

      <fieldset
        v-if="!bug"
        class="rounded-card border border-slate-200 p-3"
      >
        <legend class="px-1 text-xs font-semibold text-slate-500">
          Người báo cáo
        </legend>
        <div class="mb-2 flex gap-4 text-sm">
          <label class="flex items-center gap-1.5"><input
            v-model="reporterType"
            type="radio"
            value="internal"
          > Nội bộ</label>
          <label class="flex items-center gap-1.5"><input
            v-model="reporterType"
            type="radio"
            value="external"
          > Bên ngoài</label>
        </div>
        <PersonSelect
          v-if="reporterType === 'internal'"
          v-model="form.reporter_employee_id"
          :options="employees"
          placeholder="Tìm & chọn người báo cáo…"
        />
        <div
          v-else
          class="grid grid-cols-2 gap-3"
        >
          <input
            v-model="form.reporter_name"
            type="text"
            class="input"
            placeholder="Họ tên"
          >
          <input
            v-model="form.reporter_email"
            type="email"
            class="input"
            placeholder="Email"
          >
        </div>
        <p
          v-if="form.errors.reporter_name"
          class="mt-1 text-xs text-danger"
        >
          {{ form.errors.reporter_name }}
        </p>
      </fieldset>

      <div class="flex justify-end gap-2 pt-2">
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
          {{ bug ? 'Lưu' : 'Tạo bug' }}
        </button>
      </div>
    </form>
  </Modal>
</template>
