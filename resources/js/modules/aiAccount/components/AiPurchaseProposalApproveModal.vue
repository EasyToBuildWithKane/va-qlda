<script setup>
import { reactive, ref, watch } from 'vue';
import Modal from '@/Components/Ui/Modal.vue';
import FieldTooltip from '@/shared/ui/FieldTooltip.vue';

const props = defineProps({
    show: Boolean,
    proposal: { type: Object, default: null },
});

const emit = defineEmits(['close', 'submit']);

const dirty = ref(false);
const form = reactive({ review_notes: '' });

watch(() => props.show, (open) => {
    if (!open) return;
    dirty.value = false;
    form.review_notes = '';
});

function onInput() {
    dirty.value = true;
}

function handleSubmit() {
    emit('submit', { review_notes: form.review_notes.trim() || null });
}
</script>

<template>
  <Modal
    :show="show"
    title="Duyệt đề xuất mua"
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
        class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-900"
      >
        <span class="font-semibold">{{ proposal.tool_name }}</span>
        · {{ proposal.group_label || proposal.group_function }}
      </p>

      <div>
        <label class="label flex items-center gap-1">
          Ghi chú sau duyệt (tuỳ chọn)
          <FieldTooltip
            wide
            text="Hướng dẫn triển khai, mã đơn hàng, người phụ trách license — có thể bổ sung sau."
          />
        </label>
        <textarea
          v-model="form.review_notes"
          rows="3"
          class="input w-full"
          placeholder="VD: Đã chốt ngân sách Q2; IT tạo tài khoản trước 15/06…"
          @input="onInput"
        />
      </div>

      <div class="flex justify-end gap-2 pt-2">
        <button
          type="button"
          class="btn-secondary"
          @click="emit('close')"
        >
          Huỷ
        </button>
        <button
          type="submit"
          class="btn-primary"
        >
          Xác nhận duyệt
        </button>
      </div>
    </form>
  </Modal>
</template>
