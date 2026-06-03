<script setup>
import { computed, reactive, ref, watch } from 'vue';
import Modal from '@/Components/Ui/Modal.vue';

const props = defineProps({
    show: Boolean,
    account: { type: Object, default: null },
    options: { type: Object, required: true },
});

const emit = defineEmits(['close', 'submit']);

const dirty = ref(false);
const form = reactive({
    tool_name: '',
    group_function: 'DEV',
    license_type: 'Pro',
    license_key: '',
    email_registered: '',
    purchase_date: '',
    expiry_date: '',
    cost_amount: '',
    cost_unit: 'monthly',
    seats: '',
    notify_before_days: 14,
    notes: '',
});

const isEdit = computed(() => !!props.account?.id);

const showSeats = computed(() => {
    const t = (form.license_type || '').toLowerCase();
    return t.includes('team') || t.includes('business');
});

watch(
    () => props.show,
    (open) => {
        if (!open) return;
        dirty.value = false;
        const a = props.account;
        if (a) {
            Object.assign(form, {
                tool_name: a.tool_name,
                group_function: a.group_function,
                license_type: a.license_type,
                license_key: a.license_key ?? '',
                email_registered: a.email_registered,
                purchase_date: a.purchase_date,
                expiry_date: a.expiry_date,
                cost_amount: String(a.cost_amount),
                cost_unit: a.cost_unit,
                seats: a.seats != null ? String(a.seats) : '',
                notify_before_days: a.notify_before_days ?? 14,
                notes: a.notes ?? '',
            });
        } else {
            Object.assign(form, {
                tool_name: '',
                group_function: 'DEV',
                license_type: 'Pro',
                license_key: '',
                email_registered: '',
                purchase_date: '',
                expiry_date: '',
                cost_amount: '',
                cost_unit: 'monthly',
                seats: '',
                notify_before_days: 14,
                notes: '',
            });
        }
    },
);

function onInput() {
    dirty.value = true;
}

function buildPayload() {
    const payload = {
        tool_name: form.tool_name.trim(),
        group_function: form.group_function,
        license_type: form.license_type.trim(),
        license_key: form.license_key.trim() || null,
        email_registered: form.email_registered.trim(),
        purchase_date: form.purchase_date,
        expiry_date: form.expiry_date,
        cost_amount: parseInt(form.cost_amount, 10),
        cost_unit: form.cost_unit,
        notify_before_days: parseInt(form.notify_before_days, 10) || 14,
        notes: form.notes.trim() || null,
    };
    if (showSeats.value && form.seats) {
        payload.seats = parseInt(form.seats, 10);
    } else {
        payload.seats = null;
    }
    return payload;
}

function handleSubmit() {
    emit('submit', buildPayload());
}
</script>

<template>
  <Modal
    :show="show"
    :title="isEdit ? 'Sửa tài khoản AI' : 'Thêm tài khoản AI'"
    max-width="max-w-xl"
    :dirty="dirty"
    @close="emit('close')"
  >
    <form
      class="space-y-4"
      @submit.prevent="handleSubmit"
    >
      <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Tên công cụ AI *</label>
        <input
          v-model="form.tool_name"
          type="text"
          required
          class="input w-full"
          @input="onInput"
        >
      </div>
      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label class="mb-1 block text-sm font-medium text-slate-700">Nhóm chức năng *</label>
          <select
            v-model="form.group_function"
            required
            class="input w-full"
            @change="onInput"
          >
            <option
              v-for="o in options.group_function"
              :key="o.value"
              :value="o.value"
            >
              {{ o.label }}
            </option>
          </select>
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-slate-700">Loại license *</label>
          <input
            v-model="form.license_type"
            type="text"
            required
            list="license-types"
            class="input w-full"
            @input="onInput"
          >
          <datalist id="license-types">
            <option
              v-for="t in options.license_types"
              :key="t"
              :value="t"
            />
          </datalist>
        </div>
      </div>
      <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Mã license</label>
        <input
          v-model="form.license_key"
          type="text"
          class="input w-full"
          @input="onInput"
        >
      </div>
      <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Email đăng ký *</label>
        <input
          v-model="form.email_registered"
          type="email"
          required
          class="input w-full"
          @input="onInput"
        >
      </div>
      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label class="mb-1 block text-sm font-medium text-slate-700">Ngày mua *</label>
          <input
            v-model="form.purchase_date"
            type="date"
            required
            class="input w-full"
            @input="onInput"
          >
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-slate-700">Ngày hết hạn *</label>
          <input
            v-model="form.expiry_date"
            type="date"
            required
            class="input w-full"
            @input="onInput"
          >
        </div>
      </div>
      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label class="mb-1 block text-sm font-medium text-slate-700">Chi phí (VNĐ) *</label>
          <input
            v-model="form.cost_amount"
            type="number"
            min="1"
            required
            class="input w-full"
            @input="onInput"
          >
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-slate-700">Chu kỳ thanh toán *</label>
          <select
            v-model="form.cost_unit"
            required
            class="input w-full"
            @change="onInput"
          >
            <option
              v-for="o in options.cost_unit"
              :key="o.value"
              :value="o.value"
            >
              {{ o.label }}
            </option>
          </select>
        </div>
      </div>
      <div
        v-if="showSeats"
        class="grid gap-4 sm:grid-cols-2"
      >
        <div>
          <label class="mb-1 block text-sm font-medium text-slate-700">Số lượng seat</label>
          <input
            v-model="form.seats"
            type="number"
            min="1"
            class="input w-full"
            @input="onInput"
          >
        </div>
      </div>
      <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Nhắc trước (ngày)</label>
        <input
          v-model="form.notify_before_days"
          type="number"
          min="1"
          max="365"
          class="input w-full max-w-[8rem]"
          @input="onInput"
        >
      </div>
      <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Ghi chú</label>
        <textarea
          v-model="form.notes"
          rows="3"
          class="input w-full"
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
          Lưu
        </button>
      </div>
    </form>
  </Modal>
</template>
