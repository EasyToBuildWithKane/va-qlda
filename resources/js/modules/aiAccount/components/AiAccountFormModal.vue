<script setup>
import { computed, reactive, ref, watch } from 'vue';
import Modal from '@/Components/Ui/Modal.vue';
import FieldTooltip from '@/shared/ui/FieldTooltip.vue';
import PasswordInput from '@/shared/ui/form/PasswordInput.vue';
import { useModalFormDraft } from '@/composables/useModalFormDraft';
import { buildDraftSaveMeta } from '@/composables/useModalDraftHelpers';

const props = defineProps({
    show: Boolean,
    account: { type: Object, default: null },
    can: { type: Object, default: () => ({}) },
    formHints: { type: Object, default: () => ({}) },
    reminderSchedule: { type: Array, default: () => ['08:00', '14:00'] },
    statusOptions: { type: Array, default: () => [] },
    options: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['close', 'submit']);

const dirty = ref(false);
const proposalFiles = ref([]);
const paymentFiles = ref([]);

const form = reactive({
    tool_name: '',
    group_function: 'DEV',
    email_registered: '',
    password: '',
    purchase_date: '',
    expiry_date: '',
    cost_amount: 0,
    cost_unit: 'monthly',
    notify_before_days: 14,
    proposal_sent_at: '',
    payment_request_sent_at: '',
    notes: '',
    status: 'active',
});

const isEdit = computed(() => !!props.account?.id);
const canViewPassword = computed(() => props.can?.view_password || props.account?.can_view_password);

const accountDraftScope = computed(() => (
    props.account?.id ? `edit.${props.account.id}` : 'create'
));

const formDraft = useModalFormDraft('ai-account', {
    getScope: () => accountDraftScope.value,
    pick: () => ({ ...form }),
});

watch(() => props.show, (open) => {
    if (!open) return;
    dirty.value = false;
    proposalFiles.value = [];
    paymentFiles.value = [];
    if (props.account) {
        form.tool_name = props.account.tool_name ?? '';
        form.group_function = props.account.group_function ?? 'DEV';
        form.email_registered = props.account.email_registered ?? '';
        form.password = '';
        form.purchase_date = props.account.purchase_date ?? '';
        form.expiry_date = props.account.expiry_date ?? '';
        form.cost_amount = props.account.cost_amount ?? 0;
        form.cost_unit = props.account.cost_unit ?? 'monthly';
        form.notify_before_days = props.account.notify_before_days ?? 14;
        form.proposal_sent_at = props.account.proposal_sent_at ?? '';
        form.payment_request_sent_at = props.account.payment_request_sent_at ?? '';
        form.notes = props.account.notes ?? '';
        form.status = props.account.status ?? 'active';
    } else {
        form.tool_name = '';
        form.group_function = props.options?.group_function?.[0]?.value ?? 'DEV';
        form.email_registered = '';
        form.password = '';
        form.purchase_date = '';
        form.expiry_date = '';
        form.cost_amount = 0;
        form.cost_unit = 'monthly';
        form.notify_before_days = 14;
        form.proposal_sent_at = '';
        form.payment_request_sent_at = '';
        form.notes = '';
        form.status = 'active';
        const draft = formDraft.load();
        if (draft?.data) {
            Object.assign(form, draft.data);
            dirty.value = true;
        }
    }
});

function markDirty() {
    dirty.value = true;
}

function onClose() {
    formDraft.saveOnClose({}, buildDraftSaveMeta(props.account));
    emit('close');
}

function onSubmit() {
    const payload = {
        tool_name: form.tool_name.trim(),
        group_function: form.group_function,
        email_registered: form.email_registered.trim(),
        purchase_date: form.purchase_date,
        expiry_date: form.expiry_date,
        cost_amount: Number(form.cost_amount) || 0,
        cost_unit: form.cost_unit,
        notify_before_days: Number(form.notify_before_days) || 14,
        proposal_sent_at: form.proposal_sent_at || null,
        payment_request_sent_at: form.payment_request_sent_at || null,
        notes: form.notes || null,
    };
    if (form.password) payload.password = form.password;
    if (isEdit.value && props.account?.can_update_status) {
        payload.status = form.status;
    }
    if (proposalFiles.value.length) {
        payload.proposal_documents = [...proposalFiles.value];
    }
    if (paymentFiles.value.length) {
        payload.payment_request_documents = [...paymentFiles.value];
    }
    emit('submit', payload);
}

function onProposalFiles(e) {
    proposalFiles.value = [...(e.target.files || [])];
    markDirty();
}

function onPaymentFiles(e) {
    paymentFiles.value = [...(e.target.files || [])];
    markDirty();
}

const existingProposalDocs = computed(() => props.account?.proposal_documents ?? []);
const existingPaymentDocs = computed(() => props.account?.payment_request_documents ?? []);
</script>

<template>
  <Modal
    :show="show"
    :title="isEdit ? 'Sửa tài khoản AI' : 'Tạo tài khoản AI'"
    :dirty="dirty"
    max-width="2xl"
    @close="onClose"
  >
    <form
      class="space-y-4"
      @submit.prevent="onSubmit"
    >
      <div class="grid gap-4 sm:grid-cols-2">
        <label class="block sm:col-span-2">
          <span class="mb-1 block text-xs font-medium text-slate-600">Tên công cụ AI</span>
          <input
            v-model="form.tool_name"
            type="text"
            required
            class="input h-10 w-full"
            @input="markDirty"
          >
        </label>

        <label class="block">
          <span class="mb-1 block text-xs font-medium text-slate-600">Nhóm chức năng</span>
          <select
            v-model="form.group_function"
            required
            class="input h-10 w-full"
            @change="markDirty"
          >
            <option
              v-for="opt in (options.group_function || [])"
              :key="opt.value"
              :value="opt.value"
            >
              {{ opt.label }}
            </option>
          </select>
        </label>

        <label class="block">
          <span class="mb-1 flex items-center gap-1 text-xs font-medium text-slate-600">
            Email đăng ký
            <FieldTooltip :text="formHints.notify" />
          </span>
          <input
            v-model="form.email_registered"
            type="email"
            required
            class="input h-10 w-full"
            @input="markDirty"
          >
        </label>

        <label
          v-if="canViewPassword"
          class="block sm:col-span-2"
        >
          <span class="mb-1 block text-xs font-medium text-slate-600">
            Mật khẩu đăng nhập
            <span class="font-normal text-slate-400">({{ isEdit ? 'để trống nếu không đổi' : 'tuỳ chọn' }})</span>
          </span>
          <PasswordInput
            v-model="form.password"
            class="w-full"
            @update:model-value="markDirty"
          />
        </label>

        <label class="block">
          <span class="mb-1 block text-xs font-medium text-slate-600">Ngày mua</span>
          <input
            v-model="form.purchase_date"
            type="date"
            required
            class="input h-10 w-full"
            @change="markDirty"
          >
        </label>

        <label class="block">
          <span class="mb-1 block text-xs font-medium text-slate-600">Ngày hết hạn</span>
          <input
            v-model="form.expiry_date"
            type="date"
            required
            class="input h-10 w-full"
            @change="markDirty"
          >
        </label>

        <label class="block">
          <span class="mb-1 block text-xs font-medium text-slate-600">Chi phí (VNĐ)</span>
          <input
            v-model.number="form.cost_amount"
            type="number"
            min="0"
            required
            class="input h-10 w-full"
            @input="markDirty"
          >
        </label>

        <label class="block">
          <span class="mb-1 block text-xs font-medium text-slate-600">Đơn vị chi phí</span>
          <select
            v-model="form.cost_unit"
            required
            class="input h-10 w-full"
            @change="markDirty"
          >
            <option
              v-for="opt in (options.cost_unit || [])"
              :key="opt.value"
              :value="opt.value"
            >
              {{ opt.label }}
            </option>
          </select>
        </label>

        <label class="block">
          <span class="mb-1 flex items-center gap-1 text-xs font-medium text-slate-600">
            Nhắc trước (ngày)
            <FieldTooltip :text="`Lịch nhắc: ${(reminderSchedule || []).join(', ') || '08:00, 14:00'}`" />
          </span>
          <input
            v-model.number="form.notify_before_days"
            type="number"
            min="1"
            max="365"
            class="input h-10 w-full"
            @input="markDirty"
          >
        </label>

        <label
          v-if="isEdit && account?.can_update_status"
          class="block"
        >
          <span class="mb-1 block text-xs font-medium text-slate-600">Trạng thái</span>
          <select
            v-model="form.status"
            class="input h-10 w-full"
            @change="markDirty"
          >
            <option
              v-for="opt in statusOptions"
              :key="opt.value"
              :value="opt.value"
            >
              {{ opt.label }}
            </option>
          </select>
        </label>

        <label class="block">
          <span class="mb-1 block text-xs font-medium text-slate-600">Ngày gửi đề xuất</span>
          <input
            v-model="form.proposal_sent_at"
            type="date"
            class="input h-10 w-full"
            @change="markDirty"
          >
        </label>

        <label class="block">
          <span class="mb-1 block text-xs font-medium text-slate-600">Ngày gửi đề nghị thanh toán</span>
          <input
            v-model="form.payment_request_sent_at"
            type="date"
            class="input h-10 w-full"
            @change="markDirty"
          >
        </label>

        <label class="block sm:col-span-2">
          <span class="mb-1 block text-xs font-medium text-slate-600">Phiếu đề xuất (file)</span>
          <ul
            v-if="existingProposalDocs.length"
            class="mb-2 space-y-1 text-xs text-slate-600"
          >
            <li
              v-for="(doc, i) in existingProposalDocs"
              :key="i"
            >
              <a
                v-if="doc.url"
                :href="doc.url"
                target="_blank"
                rel="noopener"
                class="text-brand underline"
              >{{ doc.original_name }}</a>
              <span v-else>{{ doc.original_name }}</span>
            </li>
          </ul>
          <input
            type="file"
            multiple
            accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
            class="block w-full text-sm"
            @change="onProposalFiles"
          >
        </label>

        <label class="block sm:col-span-2">
          <span class="mb-1 block text-xs font-medium text-slate-600">Phiếu đề nghị thanh toán (file)</span>
          <ul
            v-if="existingPaymentDocs.length"
            class="mb-2 space-y-1 text-xs text-slate-600"
          >
            <li
              v-for="(doc, i) in existingPaymentDocs"
              :key="i"
            >
              <a
                v-if="doc.url"
                :href="doc.url"
                target="_blank"
                rel="noopener"
                class="text-brand underline"
              >{{ doc.original_name }}</a>
              <span v-else>{{ doc.original_name }}</span>
            </li>
          </ul>
          <input
            type="file"
            multiple
            accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
            class="block w-full text-sm"
            @change="onPaymentFiles"
          >
        </label>

        <label class="block sm:col-span-2">
          <span class="mb-1 block text-xs font-medium text-slate-600">Ghi chú</span>
          <textarea
            v-model="form.notes"
            rows="3"
            class="input w-full"
            @input="markDirty"
          />
        </label>
      </div>

      <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
        <button
          type="button"
          class="btn-ghost h-9 px-3 text-sm"
          @click="onClose"
        >
          Huỷ
        </button>
        <button
          type="submit"
          class="btn-primary h-9 px-3 text-sm"
        >
          {{ isEdit ? 'Lưu thay đổi' : 'Tạo tài khoản' }}
        </button>
      </div>
    </form>
  </Modal>
</template>
