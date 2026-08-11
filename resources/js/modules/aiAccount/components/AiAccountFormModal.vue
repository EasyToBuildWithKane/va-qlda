<script setup>
import { computed, reactive, ref, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
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

const TABS = [
    { key: 'info', label: 'Thông tin', icon: 'account' },
    { key: 'billing', label: 'Chi phí & hạn', icon: 'budget' },
    { key: 'docs', label: 'Chứng từ', icon: 'documents' },
];

const activeTab = ref('info');
const dirty = ref(false);
const attemptedSubmit = ref(false);
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
const tabIndex = computed(() => TABS.findIndex((t) => t.key === activeTab.value));
const reminderHint = computed(() => (
    `Lịch nhắc: ${(props.reminderSchedule || []).join(', ') || '08:00, 14:00'}`
));

const accountDraftScope = computed(() => (
    props.account?.id ? `edit.${props.account.id}` : 'create'
));

const formDraft = useModalFormDraft('ai-account', {
    getScope: () => accountDraftScope.value,
    pick: () => ({ ...form, activeTab: activeTab.value }),
});

const missingByTab = computed(() => {
    const info = [];
    const billing = [];
    if (!form.tool_name?.trim()) info.push('tool_name');
    if (!form.group_function) info.push('group_function');
    if (!form.email_registered?.trim()) info.push('email_registered');
    if (!form.purchase_date) billing.push('purchase_date');
    if (!form.expiry_date) billing.push('expiry_date');
    if (
        form.cost_amount === null
        || form.cost_amount === undefined
        || form.cost_amount === ''
        || Number.isNaN(Number(form.cost_amount))
    ) {
        billing.push('cost_amount');
    }
    if (!form.cost_unit) billing.push('cost_unit');
    return { info, billing, docs: [] };
});

const tabHasGap = (key) => attemptedSubmit.value && (missingByTab.value[key]?.length ?? 0) > 0;

watch(() => props.show, (open) => {
    if (!open) return;
    dirty.value = false;
    attemptedSubmit.value = false;
    activeTab.value = 'info';
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
            const { activeTab: draftTab, ...rest } = draft.data;
            Object.assign(form, rest);
            if (TABS.some((t) => t.key === draftTab)) activeTab.value = draftTab;
            dirty.value = true;
        }
    }
});

function markDirty() {
    dirty.value = true;
}

function setTab(key) {
    activeTab.value = key;
}

function stepTab(delta) {
    const next = Math.min(TABS.length - 1, Math.max(0, tabIndex.value + delta));
    activeTab.value = TABS[next].key;
}

function firstInvalidTab() {
    for (const tab of TABS) {
        if ((missingByTab.value[tab.key] || []).length) return tab.key;
    }
    return null;
}

function onClose() {
    formDraft.saveOnClose(
        { activeTab: activeTab.value },
        buildDraftSaveMeta(props.account),
    );
    emit('close');
}

function onSubmit() {
    attemptedSubmit.value = true;
    const invalid = firstInvalidTab();
    if (invalid) {
        activeTab.value = invalid;
        return;
    }
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

const inputClass = 'input h-10 w-full text-sm';

function fieldClass(tabKey, field) {
    const bad = attemptedSubmit.value && (missingByTab.value[tabKey] || []).includes(field);
    return bad ? `${inputClass} border-rose-400 ring-1 ring-rose-200` : inputClass;
}
</script>

<template>
  <Modal
    :show="show"
    :title="isEdit ? 'Sửa tài khoản AI' : 'Tạo tài khoản AI'"
    :dirty="dirty"
    max-width="max-w-3xl"
    fit-viewport
    @close="onClose"
  >
    <form
      class="flex h-full min-h-0 flex-col"
      @submit.prevent="onSubmit"
    >
      <p class="mb-3 shrink-0 text-[11px] leading-relaxed text-slate-500">
        Trường có dấu
        <span class="font-medium text-danger">*</span>
        là bắt buộc. Chia thành 3 tab để điền nhanh trong một màn hình.
      </p>

      <div
        class="mb-3 flex shrink-0 gap-1 border-b border-slate-200"
        role="tablist"
        aria-label="Các bước form tài khoản AI"
      >
        <button
          v-for="tab in TABS"
          :key="tab.key"
          type="button"
          role="tab"
          class="-mb-px flex min-w-0 flex-1 items-center justify-center gap-1.5 rounded-t-md border-b-2 px-2 py-2 text-xs font-medium transition-colors sm:px-3"
          :class="activeTab === tab.key
            ? 'border-brand text-brand'
            : tabHasGap(tab.key)
              ? 'border-transparent text-rose-600 hover:text-rose-700'
              : 'border-transparent text-slate-500 hover:text-slate-700'"
          :aria-selected="activeTab === tab.key"
          @click="setTab(tab.key)"
        >
          <AppIcon
            :name="tab.icon"
            :size="13"
            class="shrink-0"
          />
          <span class="truncate">{{ tab.label }}</span>
          <span
            v-if="tabHasGap(tab.key)"
            class="ml-0.5 inline-flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-rose-100 px-1 text-[10px] font-semibold text-rose-700"
            aria-label="Thiếu trường bắt buộc"
          >!</span>
        </button>
      </div>

      <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain pr-0.5">
        <!-- Tab: Thông tin -->
        <div
          v-show="activeTab === 'info'"
          class="space-y-3"
          role="tabpanel"
        >
          <div class="rounded-lg border border-slate-200/80 bg-gradient-to-b from-slate-50/80 to-white p-3 sm:p-4">
            <div class="grid gap-3 sm:grid-cols-2">
              <label class="block sm:col-span-2">
                <span class="mb-1 flex items-center gap-1 text-xs font-medium text-slate-600">
                  Tên công cụ AI
                  <span
                    class="text-danger"
                    aria-hidden="true"
                    title="Bắt buộc"
                  >*</span>
                </span>
                <input
                  v-model="form.tool_name"
                  type="text"
                  :class="fieldClass('info', 'tool_name')"
                  placeholder="VD: ChatGPT Team, Claude Pro, Cursor Business…"
                  @input="markDirty"
                >
              </label>

              <label class="block">
                <span class="mb-1 flex items-center gap-1 text-xs font-medium text-slate-600">
                  Nhóm chức năng
                  <span
                    class="text-danger"
                    aria-hidden="true"
                    title="Bắt buộc"
                  >*</span>
                </span>
                <select
                  v-model="form.group_function"
                  :class="fieldClass('info', 'group_function')"
                  @change="markDirty"
                >
                  <option
                    disabled
                    value=""
                  >
                    Chọn nhóm chức năng
                  </option>
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
                  <span
                    class="text-danger"
                    aria-hidden="true"
                    title="Bắt buộc"
                  >*</span>
                  <FieldTooltip :text="formHints.notify || 'Email dùng nhận nhắc hết hạn và liên hệ license.'" />
                </span>
                <input
                  v-model="form.email_registered"
                  type="email"
                  :class="fieldClass('info', 'email_registered')"
                  placeholder="VD: team.dev@vaschools.edu.vn"
                  @input="markDirty"
                >
              </label>

              <label
                v-if="canViewPassword"
                class="block sm:col-span-2"
              >
                <span class="mb-1 flex items-center gap-1 text-xs font-medium text-slate-600">
                  Mật khẩu đăng nhập
                  <span class="font-normal text-slate-400">
                    ({{ isEdit ? 'để trống nếu không đổi' : 'tuỳ chọn' }})
                  </span>
                </span>
                <PasswordInput
                  v-model="form.password"
                  class="w-full"
                  :placeholder="isEdit ? 'Để trống nếu giữ mật khẩu hiện tại' : 'Nhập mật khẩu đăng nhập (nếu có)'"
                  @update:model-value="markDirty"
                />
              </label>

              <label
                v-if="isEdit && account?.can_update_status"
                class="block sm:col-span-2"
              >
                <span class="mb-1 block text-xs font-medium text-slate-600">Trạng thái</span>
                <select
                  v-model="form.status"
                  :class="inputClass"
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
            </div>
          </div>
        </div>

        <!-- Tab: Chi phí & hạn -->
        <div
          v-show="activeTab === 'billing'"
          class="space-y-3"
          role="tabpanel"
        >
          <div class="rounded-lg border border-slate-200/80 bg-gradient-to-b from-slate-50/80 to-white p-3 sm:p-4">
            <div class="grid gap-3 sm:grid-cols-2">
              <label class="block">
                <span class="mb-1 flex items-center gap-1 text-xs font-medium text-slate-600">
                  Ngày mua
                  <span
                    class="text-danger"
                    aria-hidden="true"
                    title="Bắt buộc"
                  >*</span>
                </span>
                <input
                  v-model="form.purchase_date"
                  type="date"
                  :class="fieldClass('billing', 'purchase_date')"
                  @change="markDirty"
                >
              </label>

              <label class="block">
                <span class="mb-1 flex items-center gap-1 text-xs font-medium text-slate-600">
                  Ngày hết hạn
                  <span
                    class="text-danger"
                    aria-hidden="true"
                    title="Bắt buộc"
                  >*</span>
                </span>
                <input
                  v-model="form.expiry_date"
                  type="date"
                  :class="fieldClass('billing', 'expiry_date')"
                  @change="markDirty"
                >
              </label>

              <label class="block">
                <span class="mb-1 flex items-center gap-1 text-xs font-medium text-slate-600">
                  Chi phí (VNĐ)
                  <span
                    class="text-danger"
                    aria-hidden="true"
                    title="Bắt buộc"
                  >*</span>
                </span>
                <input
                  v-model.number="form.cost_amount"
                  type="number"
                  min="0"
                  :class="fieldClass('billing', 'cost_amount')"
                  placeholder="VD: 500000"
                  @input="markDirty"
                >
              </label>

              <label class="block">
                <span class="mb-1 flex items-center gap-1 text-xs font-medium text-slate-600">
                  Đơn vị chi phí
                  <span
                    class="text-danger"
                    aria-hidden="true"
                    title="Bắt buộc"
                  >*</span>
                </span>
                <select
                  v-model="form.cost_unit"
                  :class="fieldClass('billing', 'cost_unit')"
                  @change="markDirty"
                >
                  <option
                    disabled
                    value=""
                  >
                    Chọn đơn vị chi phí
                  </option>
                  <option
                    v-for="opt in (options.cost_unit || [])"
                    :key="opt.value"
                    :value="opt.value"
                  >
                    {{ opt.label }}
                  </option>
                </select>
              </label>

              <label class="block sm:col-span-2">
                <span class="mb-1 flex items-center gap-1 text-xs font-medium text-slate-600">
                  Nhắc trước (ngày)
                  <FieldTooltip :text="reminderHint" />
                </span>
                <input
                  v-model.number="form.notify_before_days"
                  type="number"
                  min="1"
                  max="365"
                  :class="inputClass"
                  placeholder="VD: 14"
                  @input="markDirty"
                >
                <p class="mt-1 text-[11px] text-slate-400">
                  Hệ thống gửi nhắc theo lịch {{ (reminderSchedule || []).join(', ') || '08:00, 14:00' }}.
                </p>
              </label>
            </div>
          </div>
        </div>

        <!-- Tab: Chứng từ -->
        <div
          v-show="activeTab === 'docs'"
          class="space-y-3"
          role="tabpanel"
        >
          <div class="rounded-lg border border-slate-200/80 bg-gradient-to-b from-slate-50/80 to-white p-3 sm:p-4">
            <div class="grid gap-3 sm:grid-cols-2">
              <label class="block">
                <span class="mb-1 block text-xs font-medium text-slate-600">Ngày gửi đề xuất</span>
                <input
                  v-model="form.proposal_sent_at"
                  type="date"
                  :class="inputClass"
                  @change="markDirty"
                >
              </label>

              <label class="block">
                <span class="mb-1 block text-xs font-medium text-slate-600">Ngày gửi đề nghị thanh toán</span>
                <input
                  v-model="form.payment_request_sent_at"
                  type="date"
                  :class="inputClass"
                  @change="markDirty"
                >
              </label>

              <div class="sm:col-span-2">
                <span class="mb-1.5 block text-xs font-medium text-slate-600">Phiếu đề xuất (file)</span>
                <ul
                  v-if="existingProposalDocs.length"
                  class="mb-2 space-y-1 text-xs text-slate-600"
                >
                  <li
                    v-for="(doc, i) in existingProposalDocs"
                    :key="`p-${i}`"
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
                <label class="flex cursor-pointer flex-col items-center justify-center gap-1 rounded-lg border border-dashed border-slate-300 bg-white px-3 py-4 text-center transition hover:border-brand/40 hover:bg-brand/5">
                  <AppIcon
                    name="upload"
                    :size="16"
                    class="text-slate-400"
                  />
                  <span class="text-xs font-medium text-slate-600">Chọn file phiếu đề xuất</span>
                  <span class="text-[11px] text-slate-400">PDF, ảnh, Word, Excel — tối đa 5 file</span>
                  <input
                    type="file"
                    multiple
                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
                    class="sr-only"
                    @change="onProposalFiles"
                  >
                </label>
                <p
                  v-if="proposalFiles.length"
                  class="mt-1.5 text-[11px] text-slate-500"
                >
                  Đã chọn {{ proposalFiles.length }} file mới.
                </p>
              </div>

              <div class="sm:col-span-2">
                <span class="mb-1.5 block text-xs font-medium text-slate-600">Phiếu đề nghị thanh toán (file)</span>
                <ul
                  v-if="existingPaymentDocs.length"
                  class="mb-2 space-y-1 text-xs text-slate-600"
                >
                  <li
                    v-for="(doc, i) in existingPaymentDocs"
                    :key="`pay-${i}`"
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
                <label class="flex cursor-pointer flex-col items-center justify-center gap-1 rounded-lg border border-dashed border-slate-300 bg-white px-3 py-4 text-center transition hover:border-brand/40 hover:bg-brand/5">
                  <AppIcon
                    name="upload"
                    :size="16"
                    class="text-slate-400"
                  />
                  <span class="text-xs font-medium text-slate-600">Chọn file đề nghị thanh toán</span>
                  <span class="text-[11px] text-slate-400">PDF, ảnh, Word, Excel — tối đa 5 file</span>
                  <input
                    type="file"
                    multiple
                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
                    class="sr-only"
                    @change="onPaymentFiles"
                  >
                </label>
                <p
                  v-if="paymentFiles.length"
                  class="mt-1.5 text-[11px] text-slate-500"
                >
                  Đã chọn {{ paymentFiles.length }} file mới.
                </p>
              </div>

              <label class="block sm:col-span-2">
                <span class="mb-1 block text-xs font-medium text-slate-600">Ghi chú</span>
                <textarea
                  v-model="form.notes"
                  rows="3"
                  class="input w-full text-sm"
                  placeholder="Ghi chú nội bộ: số hợp đồng, seat, link admin portal…"
                  @input="markDirty"
                />
              </label>
            </div>
          </div>
        </div>
      </div>

      <div class="mt-3 flex shrink-0 flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3">
        <button
          type="button"
          class="btn-ghost h-9 px-3 text-sm"
          @click="onClose"
        >
          Huỷ
        </button>
        <div class="flex items-center gap-2">
          <button
            v-if="tabIndex > 0"
            type="button"
            class="btn-ghost h-9 gap-1 px-3 text-sm"
            @click="stepTab(-1)"
          >
            <AppIcon
              name="chevron-left"
              :size="14"
            />
            Trước
          </button>
          <button
            v-if="tabIndex < TABS.length - 1"
            type="button"
            class="btn-ghost h-9 gap-1 px-3 text-sm"
            @click="stepTab(1)"
          >
            Tiếp
            <AppIcon
              name="chevron-right"
              :size="14"
            />
          </button>
          <button
            type="submit"
            class="btn-primary h-9 px-3 text-sm"
          >
            {{ isEdit ? 'Lưu thay đổi' : 'Tạo tài khoản' }}
          </button>
        </div>
      </div>
    </form>
  </Modal>
</template>
