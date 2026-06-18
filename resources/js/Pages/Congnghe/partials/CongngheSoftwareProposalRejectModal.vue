<script setup>
import { reactive, ref, watch, inject } from 'vue';
import Modal from '@/Components/Ui/Modal.vue';
import FieldTooltip from '@/shared/ui/FieldTooltip.vue';

const props = defineProps({
    show: Boolean,
    proposal: { type: Object, default: null },
    loading: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'submit']);
const modalClose = inject('modalClose', () => emit('close'));

const dirty = ref(false);
const form = reactive({ rejection_reason: '' });

watch(() => props.show, (open) => {
    if (!open) return;
    dirty.value = false;
    form.rejection_reason = '';
});

function onInput() {
    dirty.value = true;
}

function handleSubmit() {
    emit('submit', { rejection_reason: form.rejection_reason.trim() });
}
</script>

<template>
  <Modal
    :show="show"
    title="Từ chối đề xuất phần mềm"
    max-width="max-w-lg"
    :dirty="dirty"
    @close="emit('close')"
  >
    <form
      class="space-y-4"
      @submit.prevent="handleSubmit"
    >
      <p
        v-if="proposal"
        class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900"
      >
        <span class="font-semibold">{{ proposal.title }}</span>
        <span
          v-if="proposal.reference_code"
          class="text-amber-800"
        > · {{ proposal.reference_code }}</span>
      </p>

      <div>
        <label class="label flex items-center gap-1">
          Lý do từ chối <span class="text-danger">*</span>
          <FieldTooltip
            wide
            text="Lý do sẽ được gửi email tới người đề xuất và hiển thị trên trang «Đề xuất đã gửi»."
          />
        </label>
        <textarea
          v-model="form.rejection_reason"
          rows="4"
          required
          minlength="10"
          maxlength="2000"
          class="input w-full"
          placeholder="VD: Nội dung chưa đủ thông tin kỹ thuật; vui lòng bổ sung quy mô người dùng và tích hợp hệ thống…"
          @input="onInput"
        />
      </div>

      <div class="flex justify-end gap-2 pt-2">
        <button
          type="button"
          class="btn-secondary"
          :disabled="loading"
          @click="modalClose()"
        >
          Huỷ
        </button>
        <button
          type="submit"
          class="btn-primary bg-rose-600 hover:bg-rose-700"
          :disabled="loading || form.rejection_reason.trim().length < 10"
        >
          Xác nhận từ chối và gửi email
        </button>
      </div>
    </form>
  </Modal>
</template>
