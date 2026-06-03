<script setup>
import { computed, reactive, ref, watch } from 'vue';
import Modal from '@/Components/Ui/Modal.vue';
import FieldTooltip from '@/shared/ui/FieldTooltip.vue';
import VndAmount from '@/modules/aiAccount/components/VndAmount.vue';
import ApprovedProposalPick from '@/modules/aiAccount/components/ApprovedProposalPick.vue';
import { httpGet } from '@/shared/services/http';
import { costUnitSuffix } from '@/modules/aiAccount/utils/formatVnd';

const props = defineProps({
    show: Boolean,
    account: { type: Object, default: null },
    can: { type: Object, default: () => ({}) },
    formHints: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['close', 'submit']);

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
});

const isEdit = computed(() => !!props.account?.id);
const canViewPassword = computed(() => !!props.can?.view_password);

const billingHint = computed(() => {
    if (selectedProposal.value?.billing_hint) {
        return selectedProposal.value.billing_hint;
    }
    const unit = props.account?.cost_unit;
    if (unit === 'yearly') return props.formHints?.billing_yearly ?? '';
    return props.formHints?.billing_monthly ?? '';
});

const summary = computed(() => {
    if (isEdit.value && props.account) {
        return {
            tool_name: props.account.tool_name,
            proposal_code: props.account.proposal_code,
            group_label: props.account.group_function,
            license_type: props.account.license_type,
            cost_amount: props.account.cost_amount,
            cost_unit: props.account.cost_unit,
            purchase_date: props.account.purchase_date,
            expiry_date: props.account.expiry_date,
        };
    }
    return selectedProposal.value;
});

watch(
    () => props.show,
    async (open) => {
        if (!open) return;
        dirty.value = false;
        selectedProposal.value = null;
        const a = props.account;
        if (a) {
            Object.assign(form, {
                proposal_id: null,
                email_registered: a.email_registered ?? '',
                password: a.password ?? '',
                notify_before_days: a.notify_before_days ?? 14,
                notes: a.notes ?? '',
            });
        } else {
            Object.assign(form, {
                proposal_id: null,
                email_registered: '',
                password: '',
                notify_before_days: 14,
                notes: '',
            });
            await loadAwaitingProposals();
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
    if (p.registration_email) {
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
    emit('submit', buildPayload());
}
</script>

<template>
  <Modal
    :show="show"
    :title="isEdit ? 'Sửa tài khoản AI' : 'Thêm tài khoản từ phiếu đã duyệt'"
    max-width="max-w-lg"
    :dirty="dirty"
    @close="emit('close')"
  >
    <form
      class="space-y-5"
      @submit.prevent="handleSubmit"
    >
      <template v-if="!isEdit">
        <ApprovedProposalPick
          v-model="form.proposal_id"
          :proposals="awaitingProposals"
          :disabled="loadingProposals"
          @pick="onProposalPick"
        />
      </template>

      <div
        v-if="summary"
        class="rounded-xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm"
      >
        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">
          Thông tin từ phiếu (tự điền)
        </p>
        <dl class="mt-2 grid grid-cols-1 gap-1.5 sm:grid-cols-2">
          <div v-if="summary.proposal_code">
            <dt class="text-xs text-slate-500">
              Mã phiếu
            </dt>
            <dd class="font-medium text-slate-800">
              {{ summary.proposal_code }}
            </dd>
          </div>
          <div>
            <dt class="text-xs text-slate-500">
              Sản phẩm
            </dt>
            <dd class="font-medium text-slate-800">
              {{ summary.tool_name }}
            </dd>
          </div>
          <div v-if="summary.group_label || summary.group_function">
            <dt class="text-xs text-slate-500">
              Nhóm
            </dt>
            <dd>{{ summary.group_label ?? summary.group_function }}</dd>
          </div>
          <div v-if="summary.license_type">
            <dt class="text-xs text-slate-500">
              License
            </dt>
            <dd>{{ summary.license_type }}</dd>
          </div>
          <div
            v-if="summary.cost_amount"
            class="sm:col-span-2"
          >
            <dt class="text-xs text-slate-500">
              Chi phí
            </dt>
            <dd>
              <VndAmount
                :amount="summary.cost_amount"
                inline
              />
              <span class="text-slate-500">{{ costUnitSuffix(summary.cost_unit) }}</span>
              <span
                v-if="summary.cost_monthly"
                class="text-slate-500"
              > (~<VndAmount
                :amount="summary.cost_monthly"
                inline
              />/tháng)</span>
            </dd>
          </div>
          <div v-if="summary.purchase_date || summary.start_date">
            <dt class="text-xs text-slate-500">
              Bắt đầu
            </dt>
            <dd>{{ summary.purchase_date ?? summary.start_date ?? '—' }}</dd>
          </div>
          <div v-if="summary.expiry_date || summary.end_date">
            <dt class="text-xs text-slate-500">
              Hết hạn
            </dt>
            <dd>{{ summary.expiry_date ?? summary.end_date ?? '—' }}</dd>
          </div>
        </dl>
        <p
          v-if="billingHint"
          class="mt-2 text-xs text-slate-600"
        >
          {{ billingHint }}
        </p>
      </div>

      <div class="min-w-0">
        <label class="label flex items-center gap-1">
          Email / tài khoản đăng ký <span class="text-danger">*</span>
          <FieldTooltip
            wide
            text="Email dùng đăng nhập với nhà cung cấp (lấy từ phiếu, có thể chỉnh nếu đã đăng ký thực tế)."
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

      <div
        v-if="canViewPassword"
        class="min-w-0"
      >
        <label class="label flex items-center gap-1">
          Mật khẩu đăng nhập
          <FieldTooltip
            wide
            text="Chỉ quản trị viên xem và chỉnh. Lưu mã hoá trong hệ thống — không hiển thị cho thành viên khác."
          />
        </label>
        <input
          v-model="form.password"
          type="password"
          class="input w-full font-mono text-sm"
          :placeholder="isEdit && account?.has_password ? 'Để trống nếu không đổi' : 'Nhập mật khẩu portal nhà cung cấp'"
          autocomplete="new-password"
          @input="onInput"
        >
      </div>

      <div class="min-w-0">
        <label class="label flex items-center gap-1">
          Nhắc trước hết hạn (ngày)
          <FieldTooltip
            wide
            :text="formHints.notify || 'Gửi email nhắc trước ngày hết hạn license.'"
          />
        </label>
        <input
          v-model="form.notify_before_days"
          type="number"
          min="1"
          max="365"
          class="input w-full sm:max-w-xs"
          @input="onInput"
        >
        <p class="mt-1 text-xs text-slate-500">
          {{ formHints.notify || selectedProposal?.notify_hint }}
        </p>
      </div>

      <div class="min-w-0">
        <label class="label flex items-center gap-1">
          Ghi chú
          <FieldTooltip
            wide
            text="Thông tin nội bộ: người quản lý, hóa đơn, link portal, chu kỳ thanh toán hàng tháng…"
          />
        </label>
        <textarea
          v-model="form.notes"
          rows="3"
          class="input w-full"
          placeholder="VD: Thanh toán thẻ công ty; nhắc gia hạn đầu mỗi tháng"
          @input="onInput"
        />
      </div>

      <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
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
          :disabled="!isEdit && !form.proposal_id"
        >
          Lưu
        </button>
      </div>
    </form>
  </Modal>
</template>
