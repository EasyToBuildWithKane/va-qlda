<script setup>
import { computed, reactive, ref, watch } from 'vue';
import Modal from '@/Components/Ui/Modal.vue';
import FieldTooltip from '@/shared/ui/FieldTooltip.vue';
import VndAmount from '@/modules/aiAccount/components/VndAmount.vue';

const props = defineProps({
    show: Boolean,
    options: { type: Object, required: true },
    proposalDefaults: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['close', 'submit']);

const dirty = ref(false);
const copyRecipientFromProposer = ref(true);

const form = reactive({
    subject_about: '',
    send_to: '',
    tool_name: '',
    group_function: 'DEV',
    license_type: 'Pro',
    cost_amount: '',
    cost_unit: 'monthly',
    quantity: '1',
    seats: '',
    proposer_name: '',
    proposer_position: '',
    proposer_department: '',
    proposal_content: '',
    objectives: '',
    staff_count: '1',
    recipient_name: '',
    recipient_position: '',
    recipient_email: '',
    recipient_phone: '',
    purchase_type: 'new',
    registration_email: '',
    planned_use_date: '',
});

const costPreviewAmount = computed(() => {
    const n = parseInt(String(form.cost_amount).replace(/\D/g, ''), 10);
    return Number.isFinite(n) && n > 0 ? n : 0;
});

function defaultForm() {
    const d = props.proposalDefaults ?? {};
    return {
        subject_about: '',
        send_to: d.send_to ?? '',
        tool_name: '',
        group_function: 'DEV',
        license_type: 'Pro',
        cost_amount: '',
        cost_unit: 'monthly',
        quantity: '1',
        seats: '',
        proposer_name: d.proposer_name ?? '',
        proposer_position: d.proposer_position ?? '',
        proposer_department: d.proposer_department ?? '',
        proposal_content: '',
        objectives: d.objectives ?? '',
        staff_count: '1',
        recipient_name: d.proposer_name ?? '',
        recipient_position: d.proposer_position ?? '',
        recipient_email: d.recipient_email ?? '',
        recipient_phone: d.recipient_phone ?? '',
        purchase_type: 'new',
        registration_email: '',
        planned_use_date: '',
    };
}

watch(() => props.show, (open) => {
    if (!open) return;
    dirty.value = false;
    copyRecipientFromProposer.value = true;
    Object.assign(form, defaultForm());
});

watch(() => form.tool_name, (name) => {
    if (!form.subject_about && name?.trim()) {
        form.subject_about = `Đăng ký sử dụng ${name.trim()}`;
    }
});

watch(copyRecipientFromProposer, (on) => {
    if (!on) return;
    form.recipient_name = form.proposer_name;
    form.recipient_position = form.proposer_position;
    form.recipient_email = props.proposalDefaults?.recipient_email ?? form.recipient_email;
    form.recipient_phone = props.proposalDefaults?.recipient_phone ?? form.recipient_phone;
});

watch(
    () => [form.proposer_name, form.proposer_position],
    () => {
        if (!copyRecipientFromProposer.value) return;
        form.recipient_name = form.proposer_name;
        form.recipient_position = form.proposer_position;
    },
);

function onInput() {
    dirty.value = true;
}

function handleSubmit() {
    emit('submit', {
        subject_about: form.subject_about.trim(),
        send_to: form.send_to.trim() || undefined,
        tool_name: form.tool_name.trim(),
        group_function: form.group_function,
        license_type: form.license_type.trim(),
        cost_amount: parseInt(form.cost_amount, 10),
        cost_unit: form.cost_unit,
        quantity: form.quantity ? parseInt(form.quantity, 10) : 1,
        seats: form.seats ? parseInt(form.seats, 10) : null,
        proposer_name: form.proposer_name.trim(),
        proposer_position: form.proposer_position.trim() || undefined,
        proposer_department: form.proposer_department.trim() || undefined,
        proposal_content: form.proposal_content.trim(),
        objectives: form.objectives.trim() || undefined,
        staff_count: form.staff_count ? parseInt(form.staff_count, 10) : 1,
        recipient_name: form.recipient_name.trim() || undefined,
        recipient_position: form.recipient_position.trim() || undefined,
        recipient_email: form.recipient_email.trim() || undefined,
        recipient_phone: form.recipient_phone.trim() || undefined,
        purchase_type: form.purchase_type,
        registration_email: form.registration_email.trim() || undefined,
        planned_use_date: form.planned_use_date || undefined,
    });
}
</script>

<template>
  <Modal
    :show="show"
    title="Phiếu đề xuất mua tài khoản AI"
    max-width="max-w-4xl"
    :dirty="dirty"
    @close="emit('close')"
  >
    <form
      class="max-h-[70vh] overflow-y-auto pr-1"
      @submit.prevent="handleSubmit"
    >
      <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-400">
        Trích yếu &amp; kính gửi
      </p>
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="min-w-0 sm:col-span-2">
          <label class="label flex items-center gap-1">
            Về việc (trích yếu) <span class="text-danger">*</span>
            <FieldTooltip text="Dòng «Về việc» trên phiếu PDX — thường gắn với tên công cụ." />
          </label>
          <input
            v-model="form.subject_about"
            type="text"
            required
            class="input w-full"
            placeholder="Đăng ký sử dụng Cursor Pro"
            @input="onInput"
          >
        </div>
        <div class="min-w-0 sm:col-span-2">
          <label class="label">Kính gửi</label>
          <input
            v-model="form.send_to"
            type="text"
            class="input w-full"
            placeholder="Phòng Công nghệ & Phòng Kế Toán"
            @input="onInput"
          >
        </div>
      </div>

      <p class="mb-3 mt-6 text-xs font-semibold uppercase tracking-wide text-slate-400">
        Đại diện đề xuất
      </p>
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="min-w-0">
          <label class="label">Họ &amp; tên <span class="text-danger">*</span></label>
          <input
            v-model="form.proposer_name"
            type="text"
            required
            class="input w-full"
            @input="onInput"
          >
        </div>
        <div class="min-w-0">
          <label class="label">Chức vụ</label>
          <input
            v-model="form.proposer_position"
            type="text"
            class="input w-full"
            @input="onInput"
          >
        </div>
        <div class="min-w-0 sm:col-span-2">
          <label class="label">Phòng ban</label>
          <input
            v-model="form.proposer_department"
            type="text"
            class="input w-full"
            @input="onInput"
          >
        </div>
      </div>

      <p class="mb-3 mt-6 text-xs font-semibold uppercase tracking-wide text-slate-400">
        Công cụ &amp; ngân sách
      </p>
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="min-w-0 sm:col-span-2">
          <label class="label">Công cụ / sản phẩm <span class="text-danger">*</span></label>
          <input
            v-model="form.tool_name"
            type="text"
            required
            class="input w-full"
            placeholder="VD: Cursor, Midjourney"
            @input="onInput"
          >
        </div>
        <div class="min-w-0">
          <label class="label">Nhóm chức năng <span class="text-danger">*</span></label>
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
          <label class="label">Loại license <span class="text-danger">*</span></label>
          <input
            v-model="form.license_type"
            type="text"
            required
            list="proposal-license-types"
            class="input w-full"
            @input="onInput"
          >
          <datalist id="proposal-license-types">
            <option
              v-for="t in options.license_types"
              :key="t"
              :value="t"
            />
          </datalist>
        </div>
        <div class="min-w-0">
          <label class="label">Chi phí (VNĐ) <span class="text-danger">*</span></label>
          <input
            v-model="form.cost_amount"
            type="number"
            min="1"
            required
            class="input w-full"
            @input="onInput"
          >
          <p
            v-if="costPreviewAmount"
            class="mt-1 text-xs text-slate-600"
          >
            <VndAmount
              :amount="costPreviewAmount"
              inline
            />
          </p>
        </div>
        <div class="min-w-0">
          <label class="label">Chu kỳ <span class="text-danger">*</span></label>
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
        <div class="min-w-0">
          <label class="label">Số lượng (bảng ngân sách)</label>
          <input
            v-model="form.quantity"
            type="number"
            min="1"
            class="input w-full"
            @input="onInput"
          >
        </div>
        <div class="min-w-0">
          <label class="label">Số nhân sự sử dụng</label>
          <input
            v-model="form.staff_count"
            type="number"
            min="1"
            class="input w-full"
            @input="onInput"
          >
        </div>
        <div class="min-w-0">
          <label class="label">Tình trạng <span class="text-danger">*</span></label>
          <select
            v-model="form.purchase_type"
            required
            class="input w-full"
            @change="onInput"
          >
            <option
              v-for="o in options.purchase_type"
              :key="o.value"
              :value="o.value"
            >
              {{ o.label }}
            </option>
          </select>
        </div>
        <div class="min-w-0">
          <label class="label">Ngày đưa vào sử dụng (dự kiến)</label>
          <input
            v-model="form.planned_use_date"
            type="date"
            class="input w-full"
            @input="onInput"
          >
        </div>
      </div>

      <p class="mb-3 mt-6 text-xs font-semibold uppercase tracking-wide text-slate-400">
        Nội dung &amp; mục tiêu
      </p>
      <div class="space-y-4">
        <div>
          <label class="label">Nội dung đề xuất <span class="text-danger">*</span></label>
          <textarea
            v-model="form.proposal_content"
            rows="4"
            required
            minlength="20"
            class="input w-full"
            placeholder="Mô tả nhu cầu, phạm vi, lợi ích kỳ vọng…"
            @input="onInput"
          />
        </div>
        <div>
          <label class="label">Mục tiêu (mỗi dòng một ý)</label>
          <textarea
            v-model="form.objectives"
            rows="4"
            class="input w-full font-mono text-sm"
            @input="onInput"
          />
        </div>
      </div>

      <p class="mb-3 mt-6 text-xs font-semibold uppercase tracking-wide text-slate-400">
        Nhân sự tiếp nhận &amp; đăng ký
      </p>
      <label class="mb-3 flex items-center gap-2 text-sm text-slate-600">
        <input
          v-model="copyRecipientFromProposer"
          type="checkbox"
          class="rounded border-slate-300"
        >
        Trùng thông tin người đề xuất
      </label>
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="min-w-0">
          <label class="label">Họ &amp; tên tiếp nhận</label>
          <input
            v-model="form.recipient_name"
            type="text"
            class="input w-full"
            :disabled="copyRecipientFromProposer"
            @input="onInput"
          >
        </div>
        <div class="min-w-0">
          <label class="label">Chức vụ</label>
          <input
            v-model="form.recipient_position"
            type="text"
            class="input w-full"
            :disabled="copyRecipientFromProposer"
            @input="onInput"
          >
        </div>
        <div class="min-w-0">
          <label class="label">Email</label>
          <input
            v-model="form.recipient_email"
            type="email"
            class="input w-full"
            @input="onInput"
          >
        </div>
        <div class="min-w-0">
          <label class="label">Điện thoại</label>
          <input
            v-model="form.recipient_phone"
            type="text"
            class="input w-full"
            @input="onInput"
          >
        </div>
        <div class="min-w-0 sm:col-span-2">
          <label class="label">Email đăng ký tài khoản</label>
          <input
            v-model="form.registration_email"
            type="email"
            class="input w-full"
            placeholder="email dùng đăng ký dịch vụ AI"
            @input="onInput"
          >
        </div>
      </div>

      <div class="mt-6 flex justify-end gap-2 border-t border-slate-100 pt-4">
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
          Gửi đề xuất
        </button>
      </div>
    </form>
  </Modal>
</template>
