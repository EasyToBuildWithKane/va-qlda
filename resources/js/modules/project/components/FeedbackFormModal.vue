<script setup>
import { ref, computed, inject, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Ui/Modal.vue';
import PersonSelect from '@/modules/project/components/PersonSelect.vue';
import SearchSelect from '@/shared/ui/SearchSelect.vue';
import { valueLabelOptions } from '@/shared/utils/selectOptions';

const props = defineProps({
    show: { type: Boolean, default: false },
    feedback: { type: Object, default: null },
    projects: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
    categoryOptions: { type: Array, default: () => [] },
    statusOptions: { type: Array, default: () => [] },
    priorityOptions: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'saved']);
const modalClose = inject('modalClose', () => emit('close'));

const reporterType = ref('external');
const form = useForm({
    project_id: null, category: 'improvement', title: '', description: '', rating: null,
    priority: 'medium', status: 'new',
    reporter_employee_id: null, reporter_name: '', reporter_email: '', assignee_id: null,
});

watch(() => props.show, (open) => {
    if (!open) return;
    form.clearErrors();
    if (props.feedback) {
        form.project_id = props.feedback.project_id;
        form.category = props.feedback.category.value;
        form.title = props.feedback.title;
        form.description = props.feedback.description;
        form.rating = props.feedback.rating;
        form.priority = props.feedback.priority.value;
        form.status = props.feedback.status.value;
        form.assignee_id = props.feedback.assignee?.id ?? null;
    } else {
        form.reset();
        reporterType.value = 'external';
    }
});

watch(reporterType, (t) => {
    if (t === 'internal') { form.reporter_name = ''; form.reporter_email = ''; }
    else { form.reporter_employee_id = null; }
});

const categorySelectOptions = computed(() => valueLabelOptions(props.categoryOptions));
const statusSelectOptions = computed(() => valueLabelOptions(props.statusOptions));
const prioritySelectOptions = computed(() => valueLabelOptions(props.priorityOptions));
const ratingSelectOptions = computed(() =>
    [1, 2, 3, 4, 5].map((n) => ({ id: n, name: `${n} ★` })),
);

const submit = () => {
    const opts = { preserveScroll: true, onSuccess: () => { emit('saved'); emit('close'); } };
    if (props.feedback) form.put(`/feedback/${props.feedback.id}`, opts);
    else form.post('/feedback', opts);
};
</script>

<template>
  <Modal
    :show="show"
    :dirty="form.isDirty"
    :title="feedback ? `Chỉnh sửa ${feedback.code}` : 'Phản hồi mới'"
    max-width="max-w-2xl"
    @close="emit('close')"
  >
    <form
      class="space-y-4"
      @submit.prevent="submit"
    >
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="label">Dự án (tuỳ chọn)</label>
          <SearchSelect
            v-model="form.project_id"
            :options="projects"
            placeholder="Tìm & chọn dự án…"
            search-placeholder="Tìm dự án…"
          />
        </div>
        <div>
          <label class="label">Người xử lý</label>
          <PersonSelect
            v-model="form.assignee_id"
            :options="employees"
            placeholder="Tìm & chọn người xử lý…"
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
        <label class="label">Nội dung</label>
        <textarea
          v-model="form.description"
          rows="3"
          class="input resize-none"
        />
        <p
          v-if="form.errors.description"
          class="mt-1 text-xs text-danger"
        >
          {{ form.errors.description }}
        </p>
      </div>

      <div class="grid grid-cols-3 gap-4">
        <div>
          <label class="label">Phân loại</label>
          <SearchSelect
            v-model="form.category"
            :options="categorySelectOptions"
            placeholder="Chọn phân loại…"
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
        <label class="label">Đánh giá (1–5)</label>
        <div class="max-w-xs">
          <SearchSelect
            v-model="form.rating"
            :options="ratingSelectOptions"
            placeholder="Chọn điểm…"
          />
        </div>
      </div>

      <fieldset
        v-if="!feedback"
        class="rounded-card border border-slate-200 p-3"
      >
        <legend class="px-1 text-xs font-semibold text-slate-500">
          Người gửi phản hồi
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
          > Người dùng</label>
        </div>
        <PersonSelect
          v-if="reporterType === 'internal'"
          v-model="form.reporter_employee_id"
          :options="employees"
          placeholder="Tìm & chọn người gửi…"
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
          {{ feedback ? 'Lưu' : 'Gửi phản hồi' }}
        </button>
      </div>
    </form>
  </Modal>
</template>
