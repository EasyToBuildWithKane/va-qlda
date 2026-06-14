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

// Surface any server error not bound to a visible field (e.g. status, generic).
const generalError = computed(() => {
    const known = ['title', 'session_number', 'date', 'total_hours'];
    const extra = Object.keys(form.errors).filter((k) => !known.includes(k));
    return extra.length ? form.errors[extra[0]] : '';
});

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
      <p
        v-if="generalError"
        class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-600"
      >
        {{ generalError }}
      </p>

      <div>
        <ProposalFormLabel
          label="Tên buổi"
          required
        />
        <input
          v-model="form.title"
          type="text"
          class="input w-full"
          :class="form.errors.title ? 'border-danger focus:border-danger focus:ring-danger/30' : ''"
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
            :class="form.errors.session_number ? 'border-danger focus:border-danger focus:ring-danger/30' : ''"
            min="1"
            required
          >
          <p
            v-if="form.errors.session_number"
            class="mt-1 text-xs text-danger"
          >
            {{ form.errors.session_number }}
          </p>
        </div>
        <div>
          <ProposalFormLabel label="Ngày học" />
          <input
            v-model="form.date"
            type="date"
            class="input w-full"
            :class="form.errors.date ? 'border-danger focus:border-danger focus:ring-danger/30' : ''"
          >
          <p
            v-if="form.errors.date"
            class="mt-1 text-xs text-danger"
          >
            {{ form.errors.date }}
          </p>
        </div>
      </div>

      <div>
        <ProposalFormLabel label="Tổng giờ (dự kiến)" />
        <DecimalHoursInput
          v-model="form.total_hours"
          placeholder="2,5"
          :invalid="!!form.errors.total_hours"
        />
        <p
          v-if="form.errors.total_hours"
          class="mt-1 text-xs text-danger"
        >
          {{ form.errors.total_hours }}
        </p>
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
