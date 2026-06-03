<script setup>
import { computed, reactive, ref, watch } from 'vue';
import Modal from '@/Components/Ui/Modal.vue';

const props = defineProps({
    show: Boolean,
    account: { type: Object, default: null },
});

const emit = defineEmits(['close', 'submit']);

const dirty = ref(false);
const form = reactive({
    period_months: 12,
    new_cost: '',
    start_date: '',
});

const periodOptions = [
    { value: 1, label: '1 tháng' },
    { value: 3, label: '3 tháng' },
    { value: 6, label: '6 tháng' },
    { value: 12, label: '1 năm' },
];

const title = computed(() => (props.account ? `Gia hạn: ${props.account.tool_name}` : 'Gia hạn'));

watch(
    () => props.show,
    (open) => {
        if (!open || !props.account) return;
        dirty.value = false;
        const a = props.account;
        const today = new Date().toISOString().slice(0, 10);
        const start = a.expiry_date >= today ? a.expiry_date : today;
        form.period_months = 12;
        form.new_cost = String(a.cost_amount);
        form.start_date = start;
    },
);

function onInput() {
    dirty.value = true;
}

function handleSubmit() {
    emit('submit', {
        period_months: form.period_months,
        new_cost: parseInt(form.new_cost, 10),
        start_date: form.start_date,
    });
}
</script>

<template>
  <Modal
    :show="show"
    :title="title"
    max-width="max-w-md"
    :dirty="dirty"
    @close="emit('close')"
  >
    <form
      v-if="account"
      class="space-y-4"
      @submit.prevent="handleSubmit"
    >
      <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Chu kỳ gia hạn</label>
        <select
          v-model="form.period_months"
          class="input w-full"
          @change="onInput"
        >
          <option
            v-for="o in periodOptions"
            :key="o.value"
            :value="o.value"
          >
            {{ o.label }}
          </option>
        </select>
      </div>
      <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Chi phí gia hạn (VNĐ)</label>
        <input
          v-model="form.new_cost"
          type="number"
          min="1"
          required
          class="input w-full"
          @input="onInput"
        >
      </div>
      <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Ngày bắt đầu</label>
        <input
          v-model="form.start_date"
          type="date"
          required
          class="input w-full"
          @input="onInput"
        >
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
          Xác nhận gia hạn
        </button>
      </div>
    </form>
  </Modal>
</template>
