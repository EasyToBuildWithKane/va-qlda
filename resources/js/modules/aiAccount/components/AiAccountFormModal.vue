<script setup>
import { computed, reactive, ref, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import Modal from '@/Components/Ui/Modal.vue';
import PasswordInput from '@/shared/ui/form/PasswordInput.vue';
import MoneyInput from '@/shared/ui/MoneyInput.vue';
import FilterDatePicker from '@/shared/ui/FilterDatePicker.vue';
import DatagridSegmentedControl from '@/shared/ui/DatagridSegmentedControl.vue';
import AiAccountDocSlot from '@/modules/aiAccount/components/AiAccountDocSlot.vue';
import AiAccountAccessGrantsPanel from '@/modules/aiAccount/components/AiAccountAccessGrantsPanel.vue';
import { useModalFormDraft } from '@/composables/useModalFormDraft';
import { buildDraftSaveMeta } from '@/composables/useModalDraftHelpers';
import { useToast } from '@/shared/composables/useToast';
import { AI_ACCOUNT_ACCESS_PERMISSIONS } from '@/modules/aiAccount/config/accessPermissions';

const props = defineProps({
    show: Boolean,
    account: { type: Object, default: null },
    can: { type: Object, default: () => ({}) },
    formHints: { type: Object, default: () => ({}) },
    reminderSchedule: { type: Array, default: () => ['08:00', '14:00'] },
    statusOptions: { type: Array, default: () => [] },
    options: { type: Object, default: () => ({}) },
    accessAccountOptions: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'submit']);
const toast = useToast();

const TABS = [
    { key: 'info', label: 'Thông tin', icon: 'account' },
    { key: 'billing', label: 'Chi phí & hạn', icon: 'budget' },
    { key: 'docs', label: 'Chứng từ', icon: 'documents' },
    { key: 'access', label: 'Phân quyền', icon: 'lock' },
];

const LOGIN_ITEMS = [
    { key: 'google', label: 'Google', icon: 'sparkles', title: 'Đăng nhập bằng Google' },
    { key: 'password', label: 'Tài khoản thường', icon: 'lock', title: 'Email + mật khẩu' },
];

const activeTab = ref('info');
const dirty = ref(false);
const attemptedSubmit = ref(false);
/** @type {import('vue').Ref<File|null>} */
const proposalFile = ref(null);
/** @type {import('vue').Ref<File|null>} */
const paymentFile = ref(null);

const form = reactive({
    tool_name: '',
    group_function: 'DEV',
    email_registered: '',
    login_method: 'password',
    password: '',
    purchase_date: '',
    expiry_date: '',
    cost_amount: null,
    cost_unit: 'monthly',
    notify_before_days: 14,
    proposal_sent_at: '',
    proposal_approved_at: '',
    payment_request_sent_at: '',
    notes: '',
    purchase_url: '',
    status: 'active',
});

const isEdit = computed(() => !!props.account?.id);
const canViewPassword = computed(() => props.can?.view_password || props.account?.can_view_password);
const tabIndex = computed(() => TABS.findIndex((t) => t.key === activeTab.value));
const isPasswordLogin = computed(() => form.login_method === 'password');

const permissionOptions = computed(() => (
    props.options?.access_permissions?.length
        ? props.options.access_permissions
        : AI_ACCOUNT_ACCESS_PERMISSIONS
));

const accountDraftScope = computed(() => (
    props.account?.id ? `edit.${props.account.id}` : 'create'
));

const formDraft = useModalFormDraft('ai-account', {
    getScope: () => accountDraftScope.value,
    pick: () => ({ ...form, activeTab: activeTab.value }),
});

const existingProposalDoc = computed(() => (props.account?.proposal_documents ?? [])[0] ?? null);
const existingPaymentDoc = computed(() => (props.account?.payment_request_documents ?? [])[0] ?? null);

const todayIso = () => new Date().toISOString().slice(0, 10);
const proposalApprovedToday = computed(() => form.proposal_approved_at === todayIso());
const paymentSentToday = computed(() => form.payment_request_sent_at === todayIso());

const missingByTab = computed(() => {
    const info = [];
    const billing = [];
    const docs = [];
    if (!form.tool_name?.trim()) info.push('tool_name');
    if (!form.group_function) info.push('group_function');
    if (!form.email_registered?.trim()) info.push('email_registered');
    if (!form.login_method) info.push('login_method');
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
    if (form.proposal_sent_at && !proposalFile.value && !existingProposalDoc.value) {
        docs.push('proposal_file');
    }
    if (form.payment_request_sent_at && !paymentFile.value && !existingPaymentDoc.value) {
        docs.push('payment_file');
    }
    if (form.proposal_approved_at && !form.proposal_sent_at) {
        docs.push('proposal_approved_at');
    }
    return { info, billing, docs, access: [] };
});

const tabHasGap = (key) => attemptedSubmit.value && (missingByTab.value[key]?.length ?? 0) > 0;

watch(() => props.show, (open) => {
    if (!open) return;
    dirty.value = false;
    attemptedSubmit.value = false;
    activeTab.value = 'info';
    proposalFile.value = null;
    paymentFile.value = null;
    if (props.account) {
        form.tool_name = props.account.tool_name ?? '';
        form.group_function = props.account.group_function ?? 'DEV';
        form.email_registered = props.account.email_registered ?? '';
        form.login_method = props.account.login_method ?? 'password';
        form.password = '';
        form.purchase_date = props.account.purchase_date ?? '';
        form.expiry_date = props.account.expiry_date ?? '';
        form.cost_amount = props.account.cost_amount ?? null;
        form.cost_unit = props.account.cost_unit ?? 'monthly';
        form.notify_before_days = props.account.notify_before_days ?? 14;
        form.proposal_sent_at = props.account.proposal_sent_at ?? '';
        form.proposal_approved_at = props.account.proposal_approved_at ?? '';
        form.payment_request_sent_at = props.account.payment_request_sent_at ?? '';
        form.notes = props.account.notes ?? '';
        form.purchase_url = props.account.purchase_url ?? '';
        form.status = props.account.status ?? 'active';
    } else {
        form.tool_name = '';
        form.group_function = props.options?.group_function?.[0]?.value ?? 'DEV';
        form.email_registered = '';
        form.login_method = 'password';
        form.password = '';
        form.purchase_date = '';
        form.expiry_date = '';
        form.cost_amount = null;
        form.cost_unit = 'monthly';
        form.notify_before_days = 14;
        form.proposal_sent_at = '';
        form.proposal_approved_at = '';
        form.payment_request_sent_at = '';
        form.notes = '';
        form.purchase_url = '';
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
        if (invalid === 'docs') {
            toast.warning('Ngày gửi đề xuất / đề nghị cần kèm đúng 1 file chứng từ.');
        }
        return;
    }
    const payload = {
        tool_name: form.tool_name.trim(),
        group_function: form.group_function,
        email_registered: form.email_registered.trim(),
        login_method: form.login_method,
        purchase_date: form.purchase_date,
        expiry_date: form.expiry_date,
        cost_amount: Number(form.cost_amount) || 0,
        cost_unit: form.cost_unit,
        notify_before_days: Number(form.notify_before_days) || 14,
        proposal_sent_at: form.proposal_sent_at || null,
        proposal_approved_at: form.proposal_approved_at || null,
        payment_request_sent_at: form.payment_request_sent_at || null,
        notes: form.notes || null,
        purchase_url: form.purchase_url?.trim() || null,
    };
    if (isPasswordLogin.value && form.password) payload.password = form.password;
    if (isEdit.value && props.account?.can_update_status) {
        payload.status = form.status;
    }
    if (proposalFile.value) {
        payload.proposal_documents = [proposalFile.value];
        payload.replace_proposal_documents = true;
    }
    if (paymentFile.value) {
        payload.payment_request_documents = [paymentFile.value];
        payload.replace_payment_request_documents = true;
    }
    emit('submit', payload);
}

function onProposalFile(file) {
    proposalFile.value = file;
    markDirty();
}

function onPaymentFile(file) {
    paymentFile.value = file;
    markDirty();
}

function clearProposalFile() {
    proposalFile.value = null;
    markDirty();
}

function clearPaymentFile() {
    paymentFile.value = null;
    markDirty();
}

function confirmProposalApproved() {
    if (!form.proposal_sent_at) {
        toast.warning('Chọn ngày gửi đề xuất và đính kèm file trước khi xác nhận duyệt.');
        return;
    }
    if (!proposalFile.value && !existingProposalDoc.value) {
        toast.warning('Đính kèm 1 file phiếu đề xuất trước khi xác nhận duyệt.');
        return;
    }
    form.proposal_approved_at = todayIso();
    markDirty();
    toast.success('Đã ghi nhận đề xuất được duyệt hôm nay.');
}

function confirmPaymentSent() {
    form.payment_request_sent_at = todayIso();
    if (!paymentFile.value && !existingPaymentDoc.value) {
        toast.warning('Đã ghi ngày hôm nay — nhớ đính kèm 1 file đề nghị thanh toán trước khi lưu.');
    } else {
        toast.success('Đã ghi nhận ngày gửi đề nghị thanh toán hôm nay.');
    }
    markDirty();
}

function openPurchaseUrl() {
    const url = form.purchase_url?.trim();
    if (!url) return;
    window.open(url, '_blank', 'noopener,noreferrer');
}

const inputClass = 'input h-10 w-full text-sm';

function fieldClass(tabKey, field) {
    const bad = attemptedSubmit.value && (missingByTab.value[tabKey] || []).includes(field);
    return bad ? `${inputClass} border-rose-400 ring-1 ring-rose-200` : inputClass;
}

const costInvalid = computed(() => (
    attemptedSubmit.value && missingByTab.value.billing.includes('cost_amount')
));
</script>

<template>
  <Modal
    :show="show"
    :title="isEdit ? 'Sửa tài khoản AI' : 'Tạo tài khoản AI'"
    :dirty="dirty"
    max-width="max-w-4xl"
    fit-viewport
    @close="onClose"
  >
    <form
      class="flex min-h-0 flex-1 flex-col overflow-hidden"
      @submit.prevent="onSubmit"
    >
      <div
        class="mb-3 flex shrink-0 gap-1 overflow-x-auto border-b border-slate-200"
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
          >!</span>
        </button>
      </div>

      <div class="ai-account-form-scroll min-h-0 flex-1 overflow-y-auto overscroll-contain [-webkit-overflow-scrolling:touch]">
        <!-- Tab: Thông tin -->
        <div
          v-show="activeTab === 'info'"
          class="space-y-3"
          role="tabpanel"
        >
          <div class="rounded-xl border border-slate-200/80 bg-gradient-to-b from-slate-50/90 to-white p-3 shadow-sm sm:p-4">
            <div class="grid gap-3 sm:grid-cols-2">
              <label class="block sm:col-span-2">
                <span class="mb-1 flex items-center gap-1 text-xs font-medium text-slate-600">
                  Tên công cụ AI <span class="text-danger">*</span>
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
                  Nhóm chức năng <span class="text-danger">*</span>
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
                  Email đăng ký <span class="text-danger">*</span>
                </span>
                <input
                  v-model="form.email_registered"
                  type="email"
                  :class="fieldClass('info', 'email_registered')"
                  placeholder="VD: team.dev@vaschools.edu.vn"
                  @input="markDirty"
                >
              </label>

              <div class="sm:col-span-2">
                <span class="mb-1.5 block text-xs font-medium text-slate-600">
                  Cách đăng nhập <span class="text-danger">*</span>
                </span>
                <DatagridSegmentedControl
                  v-model="form.login_method"
                  :items="LOGIN_ITEMS"
                  aria-label="Cách đăng nhập"
                  @update:model-value="markDirty"
                />
              </div>

              <label
                v-if="isPasswordLogin && canViewPassword"
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
              <div class="sm:col-span-2">
                <span class="mb-1 block text-xs font-medium text-slate-600">
                  Link chỗ mua
                </span>
                <div class="flex gap-2">
                  <input
                    v-model="form.purchase_url"
                    type="url"
                    :class="inputClass"
                    class="flex-1"
                    placeholder="https://…"
                    @input="markDirty"
                  >
                  <button
                    type="button"
                    class="btn-ghost inline-flex h-10 shrink-0 items-center gap-1.5 border border-slate-200 px-3 text-xs"
                    :disabled="!form.purchase_url?.trim()"
                    @click="openPurchaseUrl"
                  >
                    <AppIcon
                      name="external-link"
                      :size="14"
                    />
                    Mở
                  </button>
                </div>
              </div>

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
          <div class="rounded-xl border border-slate-200/80 bg-gradient-to-b from-slate-50/90 to-white p-3 shadow-sm sm:p-4">
            <div class="grid gap-3 sm:grid-cols-2">
              <label class="block">
                <span class="mb-1 flex items-center gap-1 text-xs font-medium text-slate-600">
                  Ngày mua <span class="text-danger">*</span>
                </span>
                <FilterDatePicker
                  v-model="form.purchase_date"
                  placeholder="Chọn ngày mua"
                  @update:model-value="markDirty"
                />
              </label>

              <label class="block">
                <span class="mb-1 flex items-center gap-1 text-xs font-medium text-slate-600">
                  Ngày hết hạn <span class="text-danger">*</span>
                </span>
                <FilterDatePicker
                  v-model="form.expiry_date"
                  placeholder="Chọn ngày hết hạn"
                  :min-date="form.purchase_date || null"
                  @update:model-value="markDirty"
                />
              </label>

              <div class="block">
                <span class="mb-1 flex items-center gap-1 text-xs font-medium text-slate-600">
                  Chi phí (VNĐ) <span class="text-danger">*</span>
                </span>
                <MoneyInput
                  v-model="form.cost_amount"
                  placeholder="1.000.000"
                  :invalid="costInvalid"
                  @update:model-value="markDirty"
                />
              </div>

              <label class="block">
                <span class="mb-1 flex items-center gap-1 text-xs font-medium text-slate-600">
                  Đơn vị chi phí <span class="text-danger">*</span>
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
                </span>
                <input
                  v-model.number="form.notify_before_days"
                  type="number"
                  min="1"
                  max="365"
                  inputmode="numeric"
                  :class="inputClass"
                  placeholder="VD: 14"
                  @input="markDirty"
                >
              </label>
            </div>
          </div>
        </div>

        <!-- Tab: Chứng từ — 2 cột ngang (PĐX | ĐNTT) -->
        <div
          v-show="activeTab === 'docs'"
          class="space-y-3"
          role="tabpanel"
        >
          <div class="grid grid-cols-1 gap-3 lg:grid-cols-2 lg:items-stretch">
            <div class="flex min-w-0 flex-col gap-2">
              <AiAccountDocSlot
                class="h-full"
                compact
                title="Phiếu đề xuất (PĐX)"
                date-label="Ngày gửi đề xuất"
                :date-value="form.proposal_sent_at"
                date-placeholder="Chọn ngày gửi đề xuất"
                :existing-doc="existingProposalDoc"
                :pending-file="proposalFile"
                confirm-label="Xác nhận đã duyệt"
                :confirmed="proposalApprovedToday"
                @update:date-value="(v) => { form.proposal_sent_at = v; markDirty(); }"
                @file-change="onProposalFile"
                @clear-file="clearProposalFile"
                @confirm="confirmProposalApproved"
              />
              <div
                v-if="form.proposal_approved_at || form.proposal_sent_at"
                class="rounded-xl border border-emerald-200/80 bg-emerald-50/50 px-3 py-2.5"
              >
                <label class="block">
                  <span class="mb-1 block text-xs font-medium text-emerald-900">
                    Ngày đề xuất được duyệt
                  </span>
                  <FilterDatePicker
                    v-model="form.proposal_approved_at"
                    placeholder="Chọn ngày duyệt"
                    :min-date="form.proposal_sent_at || null"
                    @update:model-value="markDirty"
                  />
                </label>
              </div>
            </div>

            <AiAccountDocSlot
              class="h-full min-w-0"
              compact
              title="Đề nghị thanh toán (ĐNTT)"
              date-label="Ngày gửi đề nghị thanh toán"
              :date-value="form.payment_request_sent_at"
              date-placeholder="Chọn ngày gửi đề nghị"
              :existing-doc="existingPaymentDoc"
              :pending-file="paymentFile"
              confirm-label="Xác nhận đã gửi"
              :confirmed="paymentSentToday"
              :min-date="form.proposal_approved_at || form.proposal_sent_at || null"
              @update:date-value="(v) => { form.payment_request_sent_at = v; markDirty(); }"
              @file-change="onPaymentFile"
              @clear-file="clearPaymentFile"
              @confirm="confirmPaymentSent"
            />
          </div>

          <label class="block rounded-xl border border-slate-200/90 bg-white px-3 py-2.5 sm:px-4">
            <span class="mb-1 block text-xs font-medium text-slate-600">Ghi chú</span>
            <textarea
              v-model="form.notes"
              rows="2"
              class="input w-full text-sm"
              placeholder="Ghi chú nội bộ: số hợp đồng, seat, link admin portal…"
              @input="markDirty"
            />
          </label>
        </div>

        <!-- Tab: Phân quyền -->
        <div
          v-show="activeTab === 'access'"
          class="space-y-3"
          role="tabpanel"
        >
          <AiAccountAccessGrantsPanel
            :ai-account-id="account?.id || null"
            :can-manage-access="isEdit ? !!account?.can_manage_access : false"
            :permission-options="permissionOptions"
            :owner-options="accessAccountOptions"
          />
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

<style scoped>
/* Ẩn nút ▲▼ của scrollbar Windows (vẫn cuộn được) */
.ai-account-form-scroll {
    scrollbar-width: thin;
    scrollbar-color: rgb(203 213 225 / 0.9) transparent;
}

.ai-account-form-scroll::-webkit-scrollbar {
    width: 6px;
}

.ai-account-form-scroll::-webkit-scrollbar-button {
    display: none;
    height: 0;
    width: 0;
}

.ai-account-form-scroll::-webkit-scrollbar-thumb {
    border-radius: 9999px;
    background: rgb(203 213 225 / 0.9);
}

.ai-account-form-scroll::-webkit-scrollbar-track {
    background: transparent;
}
</style>
