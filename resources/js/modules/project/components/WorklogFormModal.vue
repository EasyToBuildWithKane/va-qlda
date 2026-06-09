<script setup>
import { inject, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Ui/Modal.vue';
import PersonSelect from '@/modules/project/components/PersonSelect.vue';
import { useModalFormDraft } from '@/composables/useModalFormDraft';
import { buildDraftSaveMeta, restoreModalDraft } from '@/composables/useModalDraftHelpers';

const props = defineProps({
    show: { type: Boolean, default: false },
    projectId: { type: Number, required: true },
    task: { type: Object, default: null },
    employees: { type: Array, default: () => [] },
    defaultEmployeeId: { type: Number, default: null },
});

const emit = defineEmits(['close', 'saved']);
const modalClose = inject('modalClose', () => emit('close'));

const today = new Date().toISOString().slice(0, 10);
const form = useForm({ employee_id: null, date: today, hours: 1, note: '' });

const formDraft = useModalFormDraft('worklog', {
    getScope: () => `${props.projectId}.${props.task?.id ?? 'none'}`,
    fields: ['employee_id', 'date', 'hours', 'note'],
});

const applyFormDraft = (data) => {
    form.employee_id = data.employee_id ?? props.defaultEmployeeId;
    form.date = data.date ?? today;
    form.hours = data.hours ?? 1;
    form.note = data.note ?? '';
};

const saveDraftOnClose = () => {
    formDraft.saveOnClose(form.data(), buildDraftSaveMeta(props.task));
};

watch(() => props.show, async (open) => {
    if (!open) return;
    form.clearErrors();
    form.reset();
    form.date = today;
    form.employee_id = props.defaultEmployeeId;
    const epoch = formDraft.bumpOpenEpoch();
    await restoreModalDraft(formDraft, {
        isActive: () => props.show,
        openEpoch: epoch,
        entity: props.task,
        applyDraft: applyFormDraft,
        form,
    });
});

const submit = () => {
    if (!props.task) return;
    form.post(`/projects/${props.projectId}/tasks/${props.task.id}/worklogs`, {
        preserveScroll: true,
        onSuccess: () => {
            formDraft.clear();
            emit('saved');
            emit('close');
        },
    });
};
</script>

<template>
  <Modal
    :show="show"
    :dirty="form.isDirty"
    :title="'Ghi nhận giờ làm' + (task ? ' — ' + task.title : '')"
    :on-save-draft="saveDraftOnClose"
    @close="emit('close')"
  >
    <form
      class="space-y-4"
      @submit.prevent="submit"
    >
      <div>
        <label class="label">Người thực hiện</label>
        <PersonSelect
          v-model="form.employee_id"
          :options="employees"
          placeholder="Tìm & chọn người thực hiện…"
        />
        <p
          v-if="form.errors.employee_id"
          class="mt-1 text-xs text-danger"
        >
          {{ form.errors.employee_id }}
        </p>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="label">Ngày</label>
          <input
            v-model="form.date"
            type="date"
            class="input"
          >
        </div>
        <div>
          <label class="label">Số giờ</label>
          <input
            v-model.number="form.hours"
            type="number"
            step="0.25"
            min="0.25"
            max="24"
            class="input"
          >
          <p
            v-if="form.errors.hours"
            class="mt-1 text-xs text-danger"
          >
            {{ form.errors.hours }}
          </p>
        </div>
      </div>
      <div>
        <label class="label">Ghi chú</label>
        <input
          v-model="form.note"
          type="text"
          class="input"
        >
      </div>
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
          Ghi nhận
        </button>
      </div>
    </form>
  </Modal>
</template>
