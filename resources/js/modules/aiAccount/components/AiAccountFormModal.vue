<script setup>
import { computed, reactive, ref, watch, inject } from 'vue';
import Modal from '@/Components/Ui/Modal.vue';
import FieldTooltip from '@/shared/ui/FieldTooltip.vue';
import PasswordInput from '@/shared/ui/form/PasswordInput.vue';
import VndAmount from '@/modules/aiAccount/components/VndAmount.vue';
import ApprovedProposalPick from '@/modules/aiAccount/components/ApprovedProposalPick.vue';
import { httpGet } from '@/shared/services/http';
import { costUnitSuffix } from '@/modules/aiAccount/utils/formatVnd';
import { formatDaysLeftLabel, resolveDaysLeft } from '@/modules/aiAccount/utils/daysUntilExpiry';
import { statusSelectClass } from '@/modules/aiAccount/utils/accountStatusStyle';
import { useModalFormDraft } from '@/composables/useModalFormDraft';
import { buildDraftSaveMeta, entityRevisionFrom } from '@/composables/useModalDraftHelpers';

const props = defineProps({
    show: Boolean,
    account: { type: Object, default: null },
    can: { type: Object, default: () => ({}) },
    formHints: { type: Object, default: () => ({}) },
    reminderSchedule: { type: Array, default: () => ['08:00', '14:00'] },
    statusOptions: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'submit']);
const modalClose = inject('modalClose', () => emit('close'));

const dirty = ref(false);
const loadingProposals = ref(false);
const awaitingProposals = ref([]);
const selectedProposal = ref(null);

const form = reactive({
    proposal_id: null,
    email_registered: '',
    password: '',
    notify_before_days: 14,
    notes: '',
    status: 'active',
    purchase_date: '',
    expiry_date: '',
});

const accountDraftScope = computed(() => (
    props.account?.id ? `edit.${props.account.id}` : 'create'
));

const formDraft = useModalFormDraft('ai-account', {
    getScope: () => accountDraftScope.value,
    pick: () => ({
        proposal_id: form.proposal_id,
        email_registered: form.email_registered,
        notify_before_days: form.notify_before_days,
        notes: form.notes,
        status: form.status,
        purchase_date: form.purchase_date,
        expiry_date: form.expiry_date,
    }),
});

const applyAccountDraft = (data) => {
    form.proposal_id = data.proposal_id ?? null;
    form.email_registered = data.email_registered ?? '';
    form.notify_before_days = data.notify_before_days ?? 14;
    form.notes = data.notes ?? '';
    form.status = data.status ?? 'active';
    form.purchase_date = data.purchase_date ?? '';
    form.expiry_date = data.expiry_date ?? '';
    if (form.proposal_id) {
        const p = awaitingProposals.value.find((x) => x.id === form.proposal_id);
        if (p) selectedProposal.value = p;
    }
    dirty.value = true;
};

const saveDraftOnClose = () => {
    formDraft.saveOnClose({}, buildDraftSaveMeta(props.account));
};

const isEdit = computed(() => !!props.account?.id);
const canViewPassword = computed(() => {
    if (props.account?.can_view_password) return true;
    return !isEdit.value && !!props.can?.view_password;
});
const canUpdateStatus = computed(() => !!props.account?.can_update_status);

const modalTitle = computed(() =>
    (isEdit.value ? 'Sửa tài khoản AI' : 'Thêm tài khoản từ phiếu đã duyệt'),
);

const billingHint = computed(() => {
    if (selectedProposal.value?.billing_hint) {
        return selectedProposal.value.billing_hint;
    }
    const unit = props.account?.cost_unit;
    if (unit === 'yearly') return props.formHints?.billing_yearly ?? '';
    return props.formHints?.billing_monthly ?? '';
});

const notifyHintText = computed(() =>
    formHintsNotify.value || selectedProposal.value?.notify_hint || '',
);

const formHintsNotify = computed(() => props.formHints?.notify ?? '');

const reminderScheduleLabel = computed(() =>
    (props.reminderSchedule ?? []).join(' và '),
);

const summary = computed(() => {
    if (isEdit.value && props.account) {
        return {
            tool_name: props.account.tool_name,
            proposal_code: props.account.proposal_code,
            group_label: props.account.group_label ?? props.account.group_function,
            license_type: props.account.license_type,
            cost_amount: props.account.cost_amount,
            cost_unit: props.account.cost_unit,
            cost_monthly: props.account.cost_monthly,
            purchase_date: props.account.purchase_date,
            expiry_date: props.account.expiry_date,
            days_until_expiry: props.account.days_until_expiry,
            days_until_expiry_signed: props.account.days_until_expiry_signed,
            status: props.account.status,
        };
    }
    return selectedProposal.value;
});

const daysLeftSummary = computed(() => {
    const s = summary.value;
    if (!s) return null;
    return formatDaysLeftLabel(resolveDaysLeft(s), s.status);
});

const summaryItems = computed(() => {
    const s = summary.value;
    if (!s) return [];
    return [
        { key: 'code', label: 'Mã phiếu', value: s.proposal_code, mono: true },
        { key: 'tool', label: 'Sản phẩm', value: s.tool_name },
        { key: 'group', label: 'Nhóm chức năng', value: s.group_label ?? s.group_function },
        { key: 'license', label: 'Loại license', value: s.license_type },
        { key: 'start', label: 'Ngày bắt đầu', value: s.purchase_date ?? s.start_date ?? '—' },
        {
            key: 'end',
            label: 'Ngày hết hạn',
            value: s.expiry_date ?? s.end_date ?? '—',
            sub: daysLeftSummary.value?.text,
            subUrgent: daysLeftSummary.value?.urgent,
        },
    ].filter((i) => (i.value && i.value !== '—') || i.key === 'end');
});

watch(
    () => props.show,
    async (open) => {
        if (!open) return;
        dirty.value = false;
        selectedProposal.value = null;
        const a = props.account;
        const epoch = formDraft.bumpOpenEpoch();
        if (a) {
            Object.assign(form, {
                proposal_id: null,
                email_registered: a.email_registered ?? '',
                password: a.password ?? '',
                notify_before_days: a.notify_before_days ?? 14,
                notes: a.notes ?? '',
                status: a.status ?? 'active',
                purchase_date: a.purchase_date ?? '',
                expiry_date: a.expiry_date ?? '',
            });
            await formDraft.tryRestore(applyAccountDraft, {
                isActive: () => props.show,
                openEpoch: epoch,
                entityRevision: entityRevisionFrom(a),
            });
        } else {
            Object.assign(form, {
                proposal_id: null,
                email_registered: '',
                password: '',
                notify_before_days: 14,
                notes: '',
                status: 'active',
                expiry_date: '',
            });
            await loadAwaitingProposals();
            await formDraft.tryRestore(applyAccountDraft, {
                isActive: () => props.show,
                openEpoch: epoch,
                entityRevision: null,
            });
        }
    },
);

async function loadAwaitingProposals() {
    loadingProposals.value = true;
    try {
        const res = await httpGet(route('api.ai-accounts.proposals.awaiting-account'));
        awaitingProposals.value = res.data?.proposals ?? res.proposals ?? [];
    } catch {
        awaitingProposals.value = [];
    } finally {
        loadingProposals.value = false;
    }
}

function onProposalPick(p) {
    selectedProposal.value = p;
    if (!p) return;
    dirty.value = true;
    form.proposal_id = p.id;
    const slot = typeof p.account_slot_index === 'number' ? p.account_slot_index : 0;
    const emails = Array.isArray(p.registration_emails) ? p.registration_emails : [];
    const fromSlot = (emails[slot] ?? '').trim();
    if (fromSlot) {
        form.email_registered = fromSlot;
    } else if (p.registration_email) {
        form.email_registered = p.registration_email;
    }
    form.notify_before_days = p.notify_before_days_suggested ?? 14;
    form.notes = p.notes_suggested ?? '';
}

function onInput() {
    dirty.value = true;
}

function buildPayload() {
    if (isEdit.value) {
        const payload = {
            email_registered: form.email_registered.trim(),
            notify_before_days: parseInt(form.notify_before_days, 10) || 14,
            notes: form.notes.trim() || null,
        };
        if (canViewPassword.value) {
            payload.password = form.password;
        }
        if (canUpdateStatus.value) {
            payload.status = form.status;
            payload.purchase_date = form.purchase_date || null;
            payload.expiry_date = form.expiry_date || null;
            payload.sync_expiry_on_expire = form.status === 'expired';
        }
        return payload;
    }
    const payload = {
        proposal_id: form.proposal_id,
        email_registered: form.email_registered.trim(),
        notify_before_days: parseInt(form.notify_before_days, 10) || 14,
        notes: form.notes.trim() || null,
    };
    if (canViewPassword.value && form.password.trim()) {
        payload.password = form.password.trim();
    }
    return payload;
}

function handleSubmit() {
    if (!isEdit.value && !form.proposal_id) return;
    formDraft.clear();
    emit('submit', buildPayload());
}
</script>

<template>
  <Modal
    :show="show"
    :title="modalTitle"
    max-width="max-w-4xl"
    :dirty="dirty"
    :on-save-draft="saveDraftOnClose"
    @close="emit('close')"
  >
    <form
      class="space-y-6"
      @submit.prevent="handleSubmit"
    >
      <!-- §1 Chọn phiếu -->
      <section
        v-if="!isEdit"
        class="space-y-4"
      >
        <header class="border-b border-slate-100 pb-2">
          <h3 class="text-xs font-bold uppercase tracking-wider text-brand">
            1 · Chọn phiếu đã duyệt
          </h3>
          <p class="mt-0.5 text-xs text-slate-500">
            Gõ mã phiếu hoặc tên sản phẩm — các trường còn lại tự điền từ phiếu.
          </p>
        </header>
        <ApprovedProposalPick
          v-model="form.proposal_id"
          :proposals="awaitingProposals"
          :disabled="loadingProposals"
          @pick="onProposalPick"
        />
        <p
          v-if="selectedProposal?.account_slot_label && (selectedProposal?.staff_count ?? 1) > 1"
          class="rounded-lg border border-brand/15 bg-brand-soft/40 px-3 py-2 text-xs text-slate-700"
        >
          Lập tài khoản
          <span class="font-semibold text-brand">{{ selectedProposal.account_slot_label }}</span>
          — cùng phiếu có thể thêm nhiều TK theo số nhân sự.
        </p>
      </section>

      <!-- §2 Thông tin phiếu (readonly) -->
      <section
        v-if="summary"
        class="space-y-4"
      >
        <header class="border-b border-slate-100 pb-2">
          <h3 class="text-xs font-bold uppercase tracking-wider text-slate-600">
            {{ isEdit ? '1' : '2' }} · Thông tin từ phiếu
          </h3>
          <p class="mt-0.5 text-xs text-slate-500">
            Đọc-only — phiếu chỉ sửa được khi còn «chờ duyệt» (tab PĐX & ĐNTT). Gia hạn tài khoản qua thao tác «Gia hạn».
          </p>
        </header>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
          <div
            v-for="item in summaryItems"
            :key="item.key"
            class="min-h-[4.25rem] rounded-lg border border-slate-200/80 bg-slate-50/90 px-3 py-2.5"
          >
            <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">
              {{ item.label }}
            </p>
            <p
              class="mt-1 text-sm font-semibold text-slate-800"
              :class="item.mono ? 'font-mono text-xs' : ''"
            >
              {{ item.value }}
            </p>
            <p
              v-if="item.sub"
              class="mt-1 text-xs font-bold tabular-nums"
              :class="item.subUrgent ? 'text-amber-800' : 'text-slate-500'"
            >
              {{ item.sub }}
            </p>
          </div>
          <div
            v-if="summary.cost_amount"
            class="min-h-[4.25rem] rounded-lg border border-brand/15 bg-brand-soft/40 px-3 py-2.5 sm:col-span-2 lg:col-span-2"
          >
            <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">
              Chi phí
            </p>
            <p class="mt-1 text-sm font-semibold text-slate-800">
              <VndAmount
                :amount="summary.cost_amount"
                inline
              />
              <span class="font-normal text-slate-500">{{ costUnitSuffix(summary.cost_unit) }}</span>
              <span
                v-if="summary.cost_monthly"
                class="block text-xs font-normal text-slate-500"
              >
                ~<VndAmount
                  :amount="summary.cost_monthly"
                  inline
                /> / tháng
              </span>
            </p>
          </div>
        </div>

        <p
          v-if="billingHint"
          class="flex items-start gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-xs text-slate-600"
        >
          <span class="mt-0.5 shrink-0 font-semibold text-brand">Chu kỳ:</span>
          {{ billingHint }}
        </p>
      </section>

      <!-- §3 Đăng nhập & nhắc nhở -->
      <section class="space-y-4">
        <header class="border-b border-slate-100 pb-2">
          <h3 class="text-xs font-bold uppercase tracking-wider text-slate-600">
            {{ isEdit ? '2' : '3' }} · Đăng nhập & nhắc nhở
          </h3>
          <p class="mt-0.5 text-xs text-slate-500">
            Email và mật khẩu portal nhà cung cấp; nhắc hết hạn qua email tự động.
          </p>
        </header>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 md:gap-x-6 md:gap-y-4">
          <template v-if="isEdit && canUpdateStatus">
            <div class="md:col-span-2 rounded-lg border border-amber-200/80 bg-amber-50/60 px-3 py-2.5 text-xs leading-relaxed text-amber-950">
              Gói mua theo tháng nhưng hết sớm hơn hạn trên phiếu: chọn trạng thái
              <strong>Hết hạn</strong> và cập nhật <strong>ngày hết hạn thực tế</strong> để báo cáo và nhắc email đúng.
            </div>
            <div class="flex min-h-[5.5rem] flex-col justify-start gap-1.5">
              <label
                for="ai-account-status"
                class="label mb-0 flex min-h-[1.25rem] items-center gap-1"
              >
                Trạng thái vận hành
                <FieldTooltip
                  wide
                  text="Người tạo phiếu hoặc quản trị viên có thể đánh dấu khi license ngừng hoạt động trước ngày kế hoạch."
                />
              </label>
              <select
                id="ai-account-status"
                v-model="form.status"
                class="input h-10 w-full text-sm font-medium"
                :class="statusSelectClass(form.status)"
                @change="onInput"
              >
                <option
                  v-for="opt in statusOptions"
                  :key="opt.value"
                  :value="opt.value"
                >
                  {{ opt.label }}
                </option>
              </select>
            </div>
            <div class="flex min-h-[5.5rem] flex-col justify-start gap-1.5">
              <label
                for="ai-account-purchase-date"
                class="label mb-0 flex min-h-[1.25rem] items-center gap-1"
              >
                Ngày mua thực tế
              </label>
              <input
                id="ai-account-purchase-date"
                v-model="form.purchase_date"
                type="date"
                class="input h-10 w-full"
                @input="onInput"
              >
            </div>
            <div class="flex min-h-[5.5rem] flex-col justify-start gap-1.5">
              <label
                for="ai-account-expiry"
                class="label mb-0 flex min-h-[1.25rem] items-center gap-1"
              >
                Ngày hết hạn thực tế
                <FieldTooltip
                  wide
                  text="Ngày license thực sự hết — dùng khi gói 1 tháng nhưng chỉ dùng được nửa tháng."
                />
              </label>
              <input
                id="ai-account-expiry"
                v-model="form.expiry_date"
                type="date"
                class="input h-10 w-full"
                @input="onInput"
              >
              <p
                v-if="daysLeftSummary"
                class="text-xs font-semibold tabular-nums text-amber-800"
              >
                {{ daysLeftSummary.text }}
              </p>
            </div>
          </template>

          <div class="flex min-h-[5.5rem] flex-col justify-start gap-1.5">
            <label
              for="ai-account-email"
              class="label mb-0 flex min-h-[1.25rem] items-center gap-1"
            >
              Email / tài khoản đăng ký
              <span class="text-danger">*</span>
              <FieldTooltip
                wide
                text="Email dùng đăng nhập với nhà cung cấp (lấy từ phiếu, có thể chỉnh nếu đã đăng ký thực tế)."
              />
            </label>
            <input
              id="ai-account-email"
              v-model="form.email_registered"
              type="email"
              required
              class="input h-10 w-full"
              placeholder="ten.ban@hcm.vaschools.edu.vn"
              autocomplete="email"
              @input="onInput"
            >
          </div>

          <div
            v-if="canViewPassword"
            class="flex min-h-[5.5rem] flex-col justify-start gap-1.5"
          >
            <label
              for="ai-account-password"
              class="label mb-0 flex min-h-[1.25rem] items-center gap-1"
            >
              Mật khẩu đăng nhập
              <FieldTooltip
                wide
                text="Chỉ quản trị viên xem và chỉnh. Lưu mã hoá — không hiển thị cho thành viên khác."
              />
            </label>
            <PasswordInput
              id="ai-account-password"
              v-model="form.password"
              :placeholder="isEdit && account?.has_password ? 'Để trống nếu không đổi mật khẩu' : 'Mật khẩu portal (ChatGPT, Cursor, …)'"
              @input="onInput"
            />
          </div>

          <div
            v-else
            class="hidden min-h-[5.5rem] md:block"
            aria-hidden="true"
          />

          <div class="flex min-h-[5.5rem] flex-col justify-start gap-1.5">
            <label
              for="ai-account-notify"
              class="label mb-0 flex min-h-[1.25rem] items-center gap-1"
            >
              Nhắc trước hết hạn (ngày)
              <FieldTooltip
                wide
                :text="formHintsNotify || 'Gửi email và thông báo trước ngày license hết hạn.'"
              />
            </label>
            <input
              id="ai-account-notify"
              v-model="form.notify_before_days"
              type="number"
              min="1"
              max="365"
              class="input h-10 w-full"
              placeholder="14"
              @input="onInput"
            >
            <p
              v-if="notifyHintText"
              class="text-xs leading-snug text-slate-500"
            >
              {{ notifyHintText }}
            </p>
          </div>

          <div
            class="flex min-h-[5.5rem] flex-col justify-center gap-1.5 rounded-lg border border-dashed border-slate-200 bg-slate-50/60 px-3 py-2.5"
          >
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
              Email nhắc tự động
            </p>
            <p class="text-xs leading-relaxed text-slate-600">
              Hệ thống gửi lúc <strong>{{ reminderScheduleLabel || '08:00 và 14:00' }}</strong> mỗi ngày cho tài khoản sắp hết hạn / đã hết hạn.
            </p>
          </div>

          <div class="flex flex-col gap-1.5 md:col-span-2">
            <label
              for="ai-account-notes"
              class="label mb-0 flex min-h-[1.25rem] items-center gap-1"
            >
              Ghi chú nội bộ
              <FieldTooltip
                wide
                text="Người quản lý license, mã hóa đơn, link portal, lịch thanh toán hàng tháng…"
              />
            </label>
            <textarea
              id="ai-account-notes"
              v-model="form.notes"
              rows="4"
              class="input min-h-[6.5rem] w-full resize-y"
              placeholder="VD: Thanh toán thẻ công ty · Liên hệ IT khi gia hạn · Nhắc team đầu mỗi tháng"
              @input="onInput"
            />
          </div>
        </div>
      </section>

      <div class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-100 pt-5">
        <button
          type="button"
          class="btn-secondary min-w-[5.5rem]"
          @click="modalClose()"
        >
          Huỷ
        </button>
        <button
          type="submit"
          class="btn-primary min-w-[5.5rem]"
          :disabled="!isEdit && !form.proposal_id"
        >
          Lưu tài khoản
        </button>
      </div>
    </form>
  </Modal>
</template>
