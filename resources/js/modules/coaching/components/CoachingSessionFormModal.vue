<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Ui/Modal.vue';
import ProposalFormLabel from '@/modules/aiAccount/components/ProposalFormLabel.vue';
import DecimalHoursInput from '@/shared/ui/DecimalHoursInput.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    courseId: { type: Number, required: true },
    nextSessionNumber: { type: Number, default: 1 },
});

const emit = defineEmits(['close']);

const form = useForm({
    title: '',
    session_number: props.nextSessionNumber,
    date: '',
    total_hours: null,
});

watch(() => props.nextSessionNumber, (n) => {
    form.session_number = n;
});

watch(() => props.show, (open) => {
    if (open) {
        form.session_number = props.nextSessionNumber;
    }
});

const dirty = computed(() => form.title !== '' || form.date !== '' || form.total_hours != null);

function close() {
    emit('close');
}

function submit() {
    form.post(route('coaching.courses.sessions.store', { course: props.courseId }), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('title', 'date', 'total_hours');
            form.session_number = props.nextSessionNumber;
            close();
        },
    });
}
</script>

<template>
  <Modal
    :show="show"
    title="Thêm buổi học"
    max-width="max-w-lg"
    :dirty="dirty"
    close-confirm-title="Đóng form?"
    close-confirm-message="Thông tin buổi học chưa lưu sẽ bị mất."
    @close="close"
  >
    <form
      class="space-y-4"
      @submit.prevent="submit"
    >
      <div>
        <ProposalFormLabel
          label="Tên buổi"
          required
        />
        <input
          v-model="form.title"
          type="text"
          class="input w-full"
          placeholder="Ví dụ: Buổi 1 — Giới thiệu Laravel"
          required
        >
        <p
          v-if="form.errors.title"
          class="mt-1 text-xs text-danger"
        >
          {{ form.errors.title }}
        </p>
      </div>

      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <ProposalFormLabel
            label="Số thứ tự"
            required
          />
          <input
            v-model.number="form.session_number"
            type="number"
            class="input w-full"
            min="1"
            required
          >
        </div>
        <div>
          <ProposalFormLabel label="Ngày học" />
          <input
            v-model="form.date"
            type="date"
            class="input w-full"
          >
        </div>
      </div>

      <div>
        <ProposalFormLabel label="Tổng giờ (dự kiến)" />
        <DecimalHoursInput
          v-model="form.total_hours"
          placeholder="2,5"
        />
      </div>

      <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
        <button
          type="button"
          class="btn-ghost h-9 px-4 text-sm"
          @click="close"
        >
          Huỷ
        </button>
        <button
          type="submit"
          class="btn-primary h-9 gap-1.5 px-4 text-sm"
          :disabled="form.processing"
        >
          Thêm buổi học
        </button>
      </div>
    </form>
  </Modal>
</template>
