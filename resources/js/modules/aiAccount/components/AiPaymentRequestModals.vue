<script setup>
import { ref, watch } from 'vue';
import Modal from '@/Components/Ui/Modal.vue';

const props = defineProps({
    /** 'approve' | 'reject' | 'paid' | null */
    mode: { type: String, default: null },
    paymentRequest: { type: Object, default: null },
    loading: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'submit']);

const rejectionReason = ref('');
const paidAt = ref('');
const actualAmount = ref('');

watch(
    () => props.mode,
    (m) => {
        if (m) {
            rejectionReason.value = '';
            paidAt.value = new Date().toISOString().slice(0, 10);
            actualAmount.value = '';
        }
    },
);

const titleMap = {
    approve: 'Duyệt đề nghị thanh toán',
    reject: 'Từ chối đề nghị thanh toán',
    paid: 'Ghi nhận thanh toán',
};

function handleSubmit() {
    if (props.mode === 'reject' && !rejectionReason.value.trim()) return;
    const payload = {};
    if (props.mode === 'reject') {
        payload.rejection_reason = rejectionReason.value.trim();
    }
    if (props.mode === 'paid') {
        payload.paid_at = paidAt.value || null;
        if (actualAmount.value) payload.actual_amount = parseInt(actualAmount.value, 10);
    }
    emit('submit', { mode: props.mode, pr: props.paymentRequest, payload });
}
</script>

<template>
  <Modal
    :show="!!mode"
    :title="titleMap[mode] || ''"
    max-width="max-w-lg"
    @close="emit('close')"
  >
    <div class="space-y-4">
      <div
        v-if="paymentRequest"
        class="rounded-lg border border-slate-100 bg-slate-50 px-4 py-3 text-sm"
      >
        <div class="flex items-center justify-between">
          <span class="font-mono text-xs text-slate-500">{{ paymentRequest.payment_request_code }}</span>
          <span class="font-semibold tabular-nums text-slate-800">
            {{ Number(paymentRequest.amount).toLocaleString('vi-VN') }}&nbsp;₫
          </span>
        </div>
      </div>

      <!-- Approve -->
      <div
        v-if="mode === 'approve'"
        class="text-sm text-slate-600"
      >
        Xác nhận duyệt đề nghị thanh toán? Sau khi duyệt, người dùng có thể lập tài khoản AI từ phiếu này.
      </div>

      <!-- Reject -->
      <div
        v-if="mode === 'reject'"
        class="space-y-2"
      >
        <label
          for="rejection-reason"
          class="label mb-0"
        >
          Lý do từ chối <span class="text-danger">*</span>
        </label>
        <textarea
          id="rejection-reason"
          v-model="rejectionReason"
          rows="3"
          class="input w-full"
          placeholder="Nhập lý do từ chối đề nghị thanh toán…"
          required
          minlength="10"
        />
      </div>

      <!-- Mark paid -->
      <div
        v-if="mode === 'paid'"
        class="space-y-3"
      >
        <p class="text-sm text-slate-600">
          Ghi nhận đề nghị thanh toán này đã được chi trả thực tế.
        </p>
        <div class="space-y-1">
          <label
            for="paid-at"
            class="label mb-0"
          >
            Ngày thanh toán
          </label>
          <input
            id="paid-at"
            v-model="paidAt"
            type="date"
            class="input h-10 w-full"
          >
        </div>
        <div class="space-y-1">
          <label
            for="actual-amount"
            class="label mb-0"
          >
            Số tiền thực tế (₫)
            <span class="text-xs font-normal text-slate-400">— để trống nếu bằng số tiền ĐNTT</span>
          </label>
          <input
            id="actual-amount"
            v-model="actualAmount"
            type="number"
            min="1"
            class="input h-10 w-full"
            :placeholder="paymentRequest?.amount?.toLocaleString('vi-VN') ?? ''"
          >
        </div>
      </div>

      <div class="flex justify-end gap-3 pt-2">
        <button
          type="button"
          class="btn btn-ghost h-9"
          :disabled="loading"
          @click="emit('close')"
        >
          Huỷ
        </button>
        <button
          type="button"
          class="btn h-9"
          :class="mode === 'reject' ? 'btn-danger' : 'btn-primary'"
          :disabled="loading || (mode === 'reject' && !rejectionReason.trim())"
          @click="handleSubmit"
        >
          <span v-if="loading">Đang xử lý…</span>
          <span v-else-if="mode === 'approve'">Duyệt ĐNTT</span>
          <span v-else-if="mode === 'reject'">Từ chối</span>
          <span v-else-if="mode === 'paid'">Xác nhận đã thanh toán</span>
        </button>
      </div>
    </div>
  </Modal>
</template>
