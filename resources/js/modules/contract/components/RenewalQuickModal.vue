<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Ui/Modal.vue';
import { formatMoney, formatDate } from '../composables/useContractFormat.js';

const props = defineProps({
    show: { type: Boolean, default: false },
    contract: { type: Object, default: null },
});

const emit = defineEmits(['close', 'saved']);

const form = useForm({
    new_expiry: '',
    new_cost: null,
    note: '',
});

watch(() => props.show, (open) => {
    if (!open || !props.contract) return;
    form.clearErrors();
    form.defaults({
        new_expiry: suggestNextExpiry(props.contract),
        new_cost: props.contract.annual_cost ?? null,
        note: '',
    });
    form.reset();
});

// Gợi ý: hết hạn hiện tại + kỳ gia hạn (mặc định +1 năm).
function suggestNextExpiry(c) {
    const base = c.expiry_date ? new Date(`${c.expiry_date}T00:00:00`) : new Date();
    const months = c.renewal_term_months || 12;
    const d = new Date(base);
    d.setMonth(d.getMonth() + months);
    return d.toISOString().slice(0, 10);
}

const costDelta = computed(() => {
    const oldCost = Number(props.contract?.annual_cost || 0);
    const newCost = Number(form.new_cost || 0);
    if (!oldCost || !newCost) return null;
    const pct = ((newCost - oldCost) / oldCost) * 100;
    return { diff: newCost - oldCost, pct: Math.round(pct * 10) / 10 };
});

function submit() {
    form.post(`/contracts/${props.contract.id}/renewals`, {
        preserveScroll: true,
        onSuccess: () => { emit('saved'); emit('close'); },
    });
}
</script>

<template>
  <Modal
    :show="show"
    title="Gia hạn nhanh"
    max-width="max-w-lg"
    :dirty="form.isDirty"
    @close="emit('close')"
  >
    <div
      v-if="contract"
      class="space-y-4"
    >
      <div class="rounded-lg bg-slate-50 p-3 text-sm">
        <p class="font-semibold text-slate-800">
          {{ contract.name }}
        </p>
        <p class="text-xs text-slate-400">
          {{ contract.code }}
        </p>
        <dl class="mt-2 grid grid-cols-2 gap-2">
          <div>
            <dt class="text-xs text-slate-400">
              Hết hạn hiện tại
            </dt>
            <dd class="font-medium text-slate-700">
              {{ formatDate(contract.expiry_date) }}
            </dd>
          </div>
          <div>
            <dt class="text-xs text-slate-400">
              Chi phí năm hiện tại
            </dt>
            <dd class="font-medium text-slate-700">
              {{ formatMoney(contract.annual_cost, contract.currency) }}
            </dd>
          </div>
        </dl>
      </div>

      <form
        class="space-y-4"
        @submit.prevent="submit"
      >
        <div>
          <label class="label">Ngày hết hạn mới *</label>
          <input
            v-model="form.new_expiry"
            type="date"
            class="input"
          >
          <p
            v-if="form.errors.new_expiry"
            class="mt-1 text-xs text-rose-600"
          >
            {{ form.errors.new_expiry }}
          </p>
        </div>
        <div>
          <label class="label">Chi phí năm mới</label>
          <input
            v-model="form.new_cost"
            type="number"
            min="0"
            class="input"
          >
          <p
            v-if="costDelta"
            class="mt-1 text-xs"
            :class="costDelta.diff > 0 ? 'text-rose-600' : 'text-emerald-600'"
          >
            {{ costDelta.diff > 0 ? '▲' : '▼' }} {{ formatMoney(Math.abs(costDelta.diff), contract.currency) }}
            ({{ costDelta.pct > 0 ? '+' : '' }}{{ costDelta.pct }}%) so với hiện tại
          </p>
        </div>
        <div>
          <label class="label">Ghi chú</label>
          <textarea
            v-model="form.note"
            rows="2"
            class="input"
            placeholder="VD: gia hạn theo báo giá mới…"
          />
        </div>

        <div class="flex justify-end gap-2 pt-2">
          <button
            type="button"
            class="btn-ghost"
            @click="emit('close')"
          >
            Huỷ
          </button>
          <button
            type="submit"
            class="btn-primary"
            :disabled="form.processing"
          >
            {{ form.processing ? 'Đang lưu…' : 'Xác nhận gia hạn' }}
          </button>
        </div>
      </form>
    </div>
  </Modal>
</template>
