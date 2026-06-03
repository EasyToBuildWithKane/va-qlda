<script setup>
import { computed, reactive, ref, watch } from 'vue';
import Modal from '@/Components/Ui/Modal.vue';
import FieldTooltip from '@/shared/ui/FieldTooltip.vue';

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
    max-width="max-w-4xl"
    :dirty="dirty"
    @close="emit('close')"
  >
    <form
      class="grid grid-cols-1 gap-5 sm:grid-cols-2"
      @submit.prevent="handleSubmit"
    >
      <div class="min-w-0 sm:col-span-2">
        <label class="label flex items-center gap-1">
          Tên công cụ AI <span class="text-danger">*</span>
          <FieldTooltip
            wide
            text="Tên gói hoặc công cụ AI cần theo dõi (hiển thị trên danh sách và nhắc hết hạn)."
          />
        </label>
        <input
          v-model="form.tool_name"
          type="text"
          required
          class="input w-full"
          placeholder="VD: ChatGPT Plus, Claude Pro, GitHub Copilot Business"
          autocomplete="off"
          @input="onInput"
        >
      </div>

      <div class="min-w-0">
        <label class="label flex items-center gap-1">
          Nhóm chức năng <span class="text-danger">*</span>
          <FieldTooltip
            wide
            text="Phân loại theo mảng sử dụng (DEV, Marketing, …) để gom nhóm và báo cáo chi phí."
          />
        </label>
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

      <div class="min-w-0">
        <label class="label flex items-center gap-1">
          Loại license <span class="text-danger">*</span>
          <FieldTooltip
            wide
            text="Tên gói từ nhà cung cấp. Gõ Team hoặc Business nếu license theo số seat."
          />
        </label>
        <input
          v-model="form.license_type"
          type="text"
          required
          list="license-types"
          class="input w-full"
          placeholder="VD: Pro, Team, Business, Enterprise"
          autocomplete="off"
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

      <div class="min-w-0">
        <label class="label flex items-center gap-1">
          Mã license
          <FieldTooltip
            wide
            text="Mã kích hoạt hoặc ID subscription (tùy chọn). Chỉ lưu nội bộ, không chia sẻ công khai."
          />
        </label>
        <input
          v-model="form.license_key"
          type="text"
          class="input w-full font-mono text-sm"
          placeholder="Khóa license — để trống nếu không có"
          autocomplete="off"
          @input="onInput"
        >
      </div>

      <div class="min-w-0">
        <label class="label flex items-center gap-1">
          Email đăng ký <span class="text-danger">*</span>
          <FieldTooltip
            wide
            text="Email dùng đăng ký với nhà cung cấp; dùng tra cứu và gửi nhắc hết hạn (nếu có trong hệ thống)."
          />
        </label>
        <input
          v-model="form.email_registered"
          type="email"
          required
          class="input w-full"
          placeholder="ten.ban@hcm.vaschools.edu.vn"
          autocomplete="email"
          @input="onInput"
        >
      </div>

      <div class="min-w-0">
        <label class="label flex items-center gap-1">
          Ngày mua <span class="text-danger">*</span>
          <FieldTooltip
            wide
            text="Ngày bắt đầu chu kỳ thanh toán hoặc ngày kích hoạt gói."
          />
        </label>
        <input
          v-model="form.purchase_date"
          type="date"
          required
          class="input w-full"
          @input="onInput"
        >
      </div>

      <div class="min-w-0">
        <label class="label flex items-center gap-1">
          Ngày hết hạn <span class="text-danger">*</span>
          <FieldTooltip
            wide
            text="Ngày license hết hiệu lực; hệ thống nhắc trước theo số ngày cấu hình bên cạnh."
          />
        </label>
        <input
          v-model="form.expiry_date"
          type="date"
          required
          class="input w-full"
          @input="onInput"
        >
      </div>

      <div class="min-w-0">
        <label class="label flex items-center gap-1">
          Chi phí (VNĐ) <span class="text-danger">*</span>
          <FieldTooltip
            wide
            text="Số tiền một chu kỳ (tháng hoặc năm). Nhập số nguyên, không dấu chấm phân cách."
          />
        </label>
        <input
          v-model="form.cost_amount"
          type="number"
          min="1"
          required
          class="input w-full"
          placeholder="VD: 500000"
          @input="onInput"
        >
      </div>

      <div class="min-w-0">
        <label class="label flex items-center gap-1">
          Chu kỳ thanh toán <span class="text-danger">*</span>
          <FieldTooltip
            wide
            text="Chi phí áp dụng theo tháng hay năm; dùng quy đổi trong báo cáo chi phí."
          />
        </label>
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

      <div
        v-if="showSeats"
        class="min-w-0"
      >
        <label class="label flex items-center gap-1">
          Số lượng seat
          <FieldTooltip
            wide
            text="Số người dùng trên gói Team/Business. Để trống nếu không áp dụng."
          />
        </label>
        <input
          v-model="form.seats"
          type="number"
          min="1"
          class="input w-full"
          placeholder="VD: 10"
          @input="onInput"
        >
      </div>

      <div class="min-w-0">
        <label class="label flex items-center gap-1">
          Nhắc trước (ngày)
          <FieldTooltip
            wide
            text="Số ngày trước ngày hết hạn để gửi email và thông báo (mặc định 14)."
          />
        </label>
        <input
          v-model="form.notify_before_days"
          type="number"
          min="1"
          max="365"
          class="input w-full sm:max-w-xs"
          placeholder="14"
          @input="onInput"
        >
      </div>

      <div class="min-w-0 sm:col-span-2">
        <label class="label flex items-center gap-1">
          Ghi chú
          <FieldTooltip
            wide
            text="Thông tin bổ sung: người quản lý license, mã hóa đơn, link portal nhà cung cấp, …"
          />
        </label>
        <textarea
          v-model="form.notes"
          rows="3"
          class="input w-full"
          placeholder="VD: Thanh toán qua thẻ công ty; liên hệ IT khi gia hạn"
          @input="onInput"
        />
      </div>

      <div class="flex justify-end gap-2 border-t border-slate-100 pt-4 sm:col-span-2">
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
