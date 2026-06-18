<script setup>
import { computed, provide, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import ContractFormModal from '@/modules/contract/components/ContractFormModal.vue';
import ContractDocuments from '@/modules/contract/components/ContractDocuments.vue';
import RenewalQuickModal from '@/modules/contract/components/RenewalQuickModal.vue';
import ContractShowSummaryBar from '@/modules/contract/components/ContractShowSummaryBar.vue';
import ContractFinanceFormPanel from '@/modules/contract/components/ContractFinanceFormPanel.vue';
import EmployeeAutocomplete from '@/modules/contract/components/EmployeeAutocomplete.vue';
import VendorFieldLabel from '@/modules/contract/components/VendorFieldLabel.vue';
import { useDialog } from '@/composables/useDialog';
import {
    formatMoney,
    formatDate,
    expiryLabel,
    expiryTone,
    formatVndDisplay,
    addMonths,
} from '@/modules/contract/composables/useContractFormat.js';
import { displayOrEmpty, EMPTY_LABELS } from '@/shared/utils/emptyDisplay.js';

const props = defineProps({
    contract: { type: Object, required: true },
    options: { type: Object, default: () => ({}) },
});

// ContractResource đi qua Inertia bị bọc trong khoá `data` (JsonResource $wrap).
// Bóc lớp này giống Index.vue để mọi trường hợp đồng hiển thị đúng.
const c = computed(() => props.contract?.data ?? props.contract);
const dialog = useDialog();

const tab = ref('overview');

/** Bản gia hạn / phụ lục mới (trỏ về hợp đồng gốc bộ) — chỉ tab Tổng quan · Đánh giá · Hồ sơ. */
const isRenewalSuccessor = computed(() => c.value.root_contract_id != null);

const ALL_TABS = [
    { key: 'overview',   label: 'Tổng quan',   icon: 'info' },
    { key: 'finance',    label: 'Tài chính',   icon: 'budget' },
    { key: 'evaluation', label: 'Đánh giá NCC', icon: 'star' },
    { key: 'documents',  label: 'Hồ sơ',       icon: 'documents' },
    { key: 'renewals',   label: 'Gia hạn',      icon: 'renewal' },
];

const tabs = computed(() => {
    if (isRenewalSuccessor.value) {
        return ALL_TABS.filter((t) => ['overview', 'evaluation', 'documents'].includes(t.key));
    }
    return ALL_TABS;
});

watch(tabs, (list) => {
    if (!list.some((t) => t.key === tab.value)) {
        tab.value = 'overview';
    }
});

// ─── Header / Status ────────────────────────────────────────────────────────
const reviewsForContract = computed(() => c.value.reviews_for_contract ?? []);
const avgReviewScore = computed(() => {
    const scores = reviewsForContract.value.map((r) => r.total_score).filter((s) => s != null);
    if (!scores.length) return null;
    return Math.round((scores.reduce((a, b) => a + b, 0) / scores.length) * 10) / 10;
});

const contractHeaderSubtitle = computed(() => {
    const parts = [
        c.value.code,
        c.value.vendor?.name,
        c.value.category?.name,
    ].filter(Boolean);
    return parts.join(' · ') || 'Chi tiết hợp đồng';
});

const showEdit  = ref(false);
const showRenew = ref(false);

async function onDelete() {
    const ok = await dialog.confirm({
        title: 'Xoá hợp đồng?',
        message: `Hợp đồng "${c.value.name}" sẽ được chuyển vào thùng rác.`,
        confirmText: 'Xoá',
        tone: 'danger',
    });
    if (!ok) return;
    router.delete(`/contracts/${c.value.id}`);
}

// ─── Overview helpers ────────────────────────────────────────────────────────
function orNA(val, label = EMPTY_LABELS.notUpdated) {
    return displayOrEmpty(val, label);
}

const hasFinanceLines = computed(() => (c.value.finances ?? []).length > 0);

/** Chi phí năm = tổng tiền HĐ (cùng nguồn với tổng vòng đời khi có dòng tài chính). */
function contractTotalAmount() {
    if (hasFinanceLines.value) {
        return c.value.lifecycle_cost_resolved
            ?? c.value.annual_cost_resolved
            ?? c.value.lifecycle_cost
            ?? c.value.annual_cost;
    }
    return c.value.lifecycle_cost
        ?? c.value.annual_cost
        ?? c.value.annual_cost_resolved
        ?? c.value.lifecycle_cost_resolved;
}

const annualDisplay = computed(() => {
    const amt = contractTotalAmount();
    if (amt == null || amt === '') {
        return { primary: EMPTY_LABELS.notUpdated, secondary: null };
    }
    return formatVndDisplay(amt);
});

function moneyDisplay(resolved, raw) {
    const amt = resolved ?? raw;
    if (amt == null || amt === '') {
        return { primary: EMPTY_LABELS.notUpdated, secondary: null };
    }
    return formatVndDisplay(amt);
}

const unitPriceDisplay = computed(() => moneyDisplay(c.value.unit_price_resolved, c.value.unit_price));
const monthlyDisplay = computed(() => moneyDisplay(c.value.monthly_cost_resolved, c.value.monthly_cost));
const lifecycleDisplay = computed(() => annualDisplay.value);

const attachmentCount = computed(() => (c.value.attachments ?? []).length);
const addendaCount = computed(() => (c.value.addenda ?? []).length);
const financeRowCount = computed(() => chainFinances.value.length);

const financeTargets = computed(() => c.value.finance_targets ?? []);

const chainFinances = computed(() => {
    if (Array.isArray(c.value.chain_finances) && c.value.chain_finances.length) {
        return c.value.chain_finances;
    }
    return (c.value.finances ?? []).map((f) => ({
        ...f,
        contract_id: c.value.id,
        contract_code: c.value.code,
        contract_kind: 'root',
        contract_label: 'Gốc',
    }));
});

/** Ngày đến hạn của một dòng tài chính = ngày SD + số tháng cam kết. */
function financeDueDate(f) {
    return addMonths(f.used_date, f.term_months);
}

/** Tổng cộng các cột số tiền của bảng tài chính (chân bảng). */
const financeTotals = computed(() => {
    const rows = chainFinances.value;
    const sum = (key) => rows.reduce((acc, f) => acc + (Number(f[key]) || 0), 0);
    return {
        init_fee: sum('init_fee'),
        maintenance_fee: sum('maintenance_fee'),
        renewal_cost: sum('renewal_cost'),
        total: sum('total'),
    };
});

// ─── Finance Tab ─────────────────────────────────────────────────────────────
const showFinanceForm = ref(false);
const editingFinanceId = ref(null);
const editingFinanceContractId = ref(null);

const financeForm = useForm({
    target_contract_id: null,
    used_date:       '',
    term_months:     '',
    quantity:        '',
    unit_price:      '',
    init_fee:        '',
    maintenance_fee: '',
    renewal_cost:    '',
    total:           '',
    note:            '',
});

provide('contractFinanceForm', financeForm);

function openAddFinance() {
    editingFinanceId.value = null;
    editingFinanceContractId.value = null;
    financeForm.reset();
    financeForm.target_contract_id = c.value.id;
    showFinanceForm.value = true;
}

function openEditFinance(f) {
    editingFinanceId.value = f.id;
    editingFinanceContractId.value = f.contract_id ?? c.value.id;
    financeForm.target_contract_id = f.contract_id ?? c.value.id;
    financeForm.used_date       = f.used_date ?? '';
    financeForm.term_months     = f.term_months ?? '';
    financeForm.quantity        = f.quantity ?? '';
    financeForm.unit_price      = f.unit_price ?? '';
    financeForm.init_fee        = f.init_fee ?? '';
    financeForm.maintenance_fee = f.maintenance_fee ?? '';
    financeForm.renewal_cost    = f.renewal_cost ?? '';
    financeForm.total           = f.total ?? '';
    financeForm.note            = f.note ?? '';
    financeForm.clearErrors();
    showFinanceForm.value = true;
}

function cancelFinance() {
    showFinanceForm.value = false;
    editingFinanceId.value = null;
    editingFinanceContractId.value = null;
    financeForm.reset();
}

function financeOwnerId() {
    if (editingFinanceId.value) {
        return editingFinanceContractId.value ?? c.value.id;
    }
    return financeForm.target_contract_id ?? c.value.id;
}

function submitFinance() {
    if (!editingFinanceId.value && financeTargets.value.length > 1 && !financeForm.target_contract_id) {
        financeForm.setError('target_contract_id', 'Vui lòng chọn hợp đồng gốc hoặc phụ lục.');
        return;
    }
    const base = `/contracts/${financeOwnerId()}/finances`;
    if (editingFinanceId.value) {
        financeForm.put(`${base}/${editingFinanceId.value}`, {
            preserveScroll: true,
            onSuccess() { cancelFinance(); router.reload({ only: ['contract'] }); },
        });
    } else {
        financeForm.post(base, {
            preserveScroll: true,
            onSuccess() { cancelFinance(); router.reload({ only: ['contract'] }); },
        });
    }
}

async function deleteFinance(f) {
    const ok = await dialog.confirm({
        title: 'Xoá dòng tài chính?',
        message: 'Dòng dữ liệu này sẽ bị xoá vĩnh viễn.',
        confirmText: 'Xoá',
        tone: 'danger',
    });
    if (!ok) return;
    const ownerId = f.contract_id ?? c.value.id;
    router.delete(`/contracts/${ownerId}/finances/${f.id}`, {
        preserveScroll: true,
        onSuccess() { router.reload({ only: ['contract'] }); },
    });
}

// ─── Evaluation Tab ──────────────────────────────────────────────────────────
const reviewCriteria = [
    { key: 'service_quality',    label: 'Chất lượng DV', tip: 'Chất lượng dịch vụ/sản phẩm so với cam kết.' },
    { key: 'sla',                label: 'SLA',           tip: 'Mức độ đáp ứng cam kết dịch vụ (SLA, uptime…).' },
    { key: 'speed',              label: 'Tốc độ',        tip: 'Tốc độ phản hồi và xử lý yêu cầu/sự cố.' },
    { key: 'price_satisfaction', label: 'Giá hài lòng',  tip: 'Mức độ hài lòng về giá so với giá trị nhận được.' },
    { key: 'stability',          label: 'Ổn định',       tip: 'Độ ổn định của dịch vụ trong suốt kỳ sử dụng.' },
    { key: 'attitude',           label: 'Thái độ',       tip: 'Thái độ hợp tác, hỗ trợ của nhà cung cấp.' },
];

const showReviewForm = ref(false);
const reviewForm = useForm({
    reviewer_id:      null,
    reviewed_at:      new Date().toISOString().slice(0, 10),
    service_quality:  null,
    sla:              null,
    speed:            null,
    price_satisfaction: null,
    stability:        null,
    attitude:         null,
    recommendation:   null,
    note:             '',
});

/** Điểm trung bình tạm tính theo các tiêu chí đã nhập (xem trước trước khi lưu). */
const reviewLivePreview = computed(() => {
    const vals = reviewCriteria
        .map((cr) => reviewForm[cr.key])
        .filter((v) => v !== null && v !== '' && !Number.isNaN(Number(v)))
        .map(Number);
    if (!vals.length) return null;
    return Math.round((vals.reduce((a, b) => a + b, 0) / vals.length) * 10) / 10;
});

function cancelReview() {
    showReviewForm.value = false;
    reviewForm.reset();
}

function submitReview() {
    reviewForm.post(`/contracts/${c.value.id}/reviews`, {
        preserveScroll: true,
        onSuccess() { cancelReview(); router.reload({ only: ['contract'] }); },
    });
}

async function deleteReview(r) {
    const ok = await dialog.confirm({
        title: 'Xoá đánh giá?',
        message: 'Đánh giá này sẽ bị xoá vĩnh viễn.',
        confirmText: 'Xoá',
        tone: 'danger',
    });
    if (!ok) return;
    router.delete(`/contracts/${c.value.id}/reviews/${r.id}`, {
        preserveScroll: true,
        onSuccess() { router.reload({ only: ['contract'] }); },
    });
}

function scoreColor(score) {
    if (score == null) return 'text-slate-400';
    if (score < 5)  return 'text-rose-600 font-semibold';
    if (score < 7)  return 'text-amber-600 font-medium';
    return 'text-emerald-600 font-medium';
}

// ─── Renewals (Addenda) ───────────────────────────────────────────────────────
const addenda = computed(() => c.value.addenda ?? []);
</script>

<template>
  <Head :title="c.name" />
  <AppLayout :flush="true">
    <template #header>
      <PageHeader
        :title="c.name"
        :subtitle="contractHeaderSubtitle"
        icon="documents"
        icon-color="brand"
      >
        <div class="flex shrink-0 flex-wrap items-center justify-end gap-1.5 sm:gap-2">
          <button
            v-if="c.can?.update"
            type="button"
            class="btn-ghost inline-flex h-9 shrink-0 items-center gap-1.5 px-2.5 text-xs sm:px-3"
            @click="showRenew = true"
          >
            <AppIcon
              name="renewal"
              :size="15"
            />
            <span class="hidden sm:inline">Gia hạn</span>
          </button>
          <button
            v-if="c.can?.update"
            type="button"
            class="btn-ghost inline-flex h-9 shrink-0 items-center gap-1.5 px-2.5 text-xs sm:px-3"
            @click="showEdit = true"
          >
            <AppIcon
              name="edit"
              :size="15"
            />
            <span class="hidden sm:inline">Sửa</span>
          </button>
          <button
            v-if="c.can?.delete"
            type="button"
            class="btn-ghost inline-flex h-9 shrink-0 items-center gap-1.5 px-2.5 text-xs text-rose-600 sm:px-3"
            @click="onDelete"
          >
            <AppIcon
              name="delete"
              :size="15"
            />
            <span class="hidden sm:inline">Xoá</span>
          </button>
        </div>
      </PageHeader>
    </template>

    <div class="flex min-h-0 w-full min-w-0 flex-1 flex-col overflow-hidden bg-slate-50">
      <div class="contract-show-scale flex min-h-0 w-full min-w-0 flex-1 flex-col overflow-hidden">
        <nav
          class="flex shrink-0 items-center overflow-x-auto overscroll-x-contain border-b border-slate-200 bg-white px-2 py-1.5 sm:px-3"
          aria-label="Tab hợp đồng"
        >
          <div
            class="inline-flex min-w-0 items-stretch gap-0.5 rounded-lg border border-slate-200/90 bg-slate-100/90 p-0.5"
            role="group"
          >
            <button
              v-for="t in tabs"
              :key="t.key"
              type="button"
              class="inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-md px-2 py-1.5 text-xs font-medium transition sm:px-2.5 sm:text-sm"
              :class="tab === t.key
                ? 'bg-white text-brand shadow-sm'
                : 'text-slate-500 hover:text-slate-700'"
              @click="tab = t.key"
            >
              <AppIcon
                :name="t.icon"
                :size="14"
              />
              {{ t.label }}
            </button>
          </div>
        </nav>

        <ContractShowSummaryBar
          v-if="tab === 'overview'"
          :contract="c"
          :attachment-count="attachmentCount"
          :addenda-count="addendaCount"
          :avg-review-score="avgReviewScore"
          :hide-renewals-nav="isRenewalSuccessor"
          @navigate-tab="tab = $event"
        />

        <div class="flex min-h-0 h-0 flex-1 flex-col overflow-hidden">
          <div class="h-full min-h-0 w-full min-w-0 overflow-x-hidden overflow-y-auto">
            <div class="w-full min-w-0 space-y-4 p-4 sm:space-y-5 sm:p-5 lg:p-6">
              <!-- ══════════════════════════════════════════════════════
           TAB: TỔNG QUAN
      ══════════════════════════════════════════════════════ -->
              <div
                v-if="tab === 'overview'"
                class="space-y-4"
              >
                <!-- Phụ lục banner -->
                <div
                  v-if="c.root_contract_id"
                  class="flex items-center gap-2 rounded-lg border border-sky-200 bg-sky-50 px-4 py-2.5 text-sm text-sky-700"
                >
                  <AppIcon
                    name="renewal"
                    :size="15"
                    class="shrink-0 text-sky-500"
                  />
                  <span>Đây là <strong>bản gia hạn / phụ lục</strong> thuộc bộ hợp đồng.</span>
                  <a
                    :href="`/contracts/${c.root_contract_id}`"
                    class="ml-auto shrink-0 font-medium text-sky-600 hover:underline"
                  >
                    Xem hợp đồng gốc →
                  </a>
                </div>

                <!-- Tổng quan: cột trái 55% (thông tin + thời hạn), cột phải 45% (chi phí + mô tả) -->
                <div
                  class="grid gap-4 lg:grid-cols-[minmax(0,55fr)_minmax(0,45fr)] lg:items-start"
                >
                  <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                    <!-- Card 1: Thông tin chung -->
                    <section class="card p-4">
                      <h3 class="mb-3 font-display text-sm font-semibold text-slate-800">
                        Thông tin chung
                      </h3>
                      <dl class="space-y-2.5 [&_dd]:min-w-0 [&_dd]:break-words">
                        <div class="grid grid-cols-[minmax(0,55fr)_minmax(0,45fr)] gap-x-3 gap-y-0.5 text-sm">
                          <dt class="text-slate-400">
                            Mã hợp đồng
                          </dt>
                          <dd class="text-right font-mono font-semibold text-slate-700">
                            {{ orNA(c.code) }}
                          </dd>
                        </div>
                        <div class="grid grid-cols-[minmax(0,55fr)_minmax(0,45fr)] gap-x-3 gap-y-0.5 text-sm">
                          <dt class="text-slate-400">
                            Trạng thái
                          </dt>
                          <dd class="text-right">
                            <Badge
                              v-if="c.status?.label"
                              :color="c.status.color"
                              :label="c.status.label"
                            />
                            <span
                              v-else
                              class="text-sm italic text-slate-400"
                            >Chưa cập nhật</span>
                          </dd>
                        </div>
                        <div class="grid grid-cols-[minmax(0,55fr)_minmax(0,45fr)] gap-x-3 gap-y-0.5 text-sm">
                          <dt class="text-slate-400">
                            Nhà cung cấp
                          </dt>
                          <dd class="text-right font-medium text-slate-700">
                            <a
                              v-if="c.vendor"
                              :href="`/contracts/vendors/${c.vendor.id}`"
                              class="text-brand hover:underline"
                            >{{ c.vendor.name }}</a>
                            <span
                              v-else
                              class="italic text-slate-400"
                            >Chưa gán</span>
                          </dd>
                        </div>
                        <div class="grid grid-cols-[minmax(0,55fr)_minmax(0,45fr)] gap-x-3 gap-y-0.5 text-sm">
                          <dt class="text-slate-400">
                            Nhóm dịch vụ
                          </dt>
                          <dd class="text-right font-medium text-slate-700">
                            {{ orNA(c.category?.name, 'Chưa phân loại') }}
                          </dd>
                        </div>
                        <div class="grid grid-cols-[minmax(0,55fr)_minmax(0,45fr)] gap-x-3 gap-y-0.5 text-sm">
                          <dt class="text-slate-400">
                            Phòng ban
                          </dt>
                          <dd class="text-right font-medium text-slate-700">
                            {{ orNA(c.using_unit) }}
                          </dd>
                        </div>
                        <div class="grid grid-cols-[minmax(0,55fr)_minmax(0,45fr)] gap-x-3 gap-y-0.5 text-sm">
                          <dt class="text-slate-400">
                            Người phụ trách
                          </dt>
                          <dd class="text-right font-medium text-slate-700">
                            {{ orNA(c.owner?.name, 'Chưa gán') }}
                          </dd>
                        </div>
                        <div class="grid grid-cols-[minmax(0,55fr)_minmax(0,45fr)] gap-x-3 gap-y-0.5 text-sm">
                          <dt class="text-slate-400">
                            Người quản lý
                          </dt>
                          <dd class="text-right font-medium text-slate-700">
                            {{ orNA(c.manager?.name, 'Chưa gán') }}
                          </dd>
                        </div>
                        <div class="grid grid-cols-[minmax(0,55fr)_minmax(0,45fr)] gap-x-3 gap-y-0.5 text-sm">
                          <dt class="text-slate-400">
                            Chu kỳ TT
                          </dt>
                          <dd class="text-right font-medium text-slate-700">
                            {{ orNA(c.billing_cycle?.label, 'Chưa chọn') }}
                          </dd>
                        </div>
                      </dl>
                    </section>

                    <!-- Card 2: Thời hạn -->
                    <section class="card p-4">
                      <h3 class="mb-3 font-display text-sm font-semibold text-slate-800">
                        Thời hạn
                      </h3>
                      <dl class="space-y-2.5 [&_dd]:min-w-0 [&_dd]:break-words">
                        <div class="grid grid-cols-[minmax(0,55fr)_minmax(0,45fr)] gap-x-3 gap-y-0.5 text-sm">
                          <dt class="text-slate-400">
                            Ngày ký
                          </dt>
                          <dd class="text-right font-medium text-slate-700">
                            {{ c.signed_date ? formatDate(c.signed_date) : 'Chưa cập nhật' }}
                          </dd>
                        </div>
                        <div class="grid grid-cols-[minmax(0,55fr)_minmax(0,45fr)] gap-x-3 gap-y-0.5 text-sm">
                          <dt class="text-slate-400">
                            Ngày hiệu lực
                          </dt>
                          <dd class="text-right font-medium text-slate-700">
                            {{ c.effective_date ? formatDate(c.effective_date) : 'Chưa cập nhật' }}
                          </dd>
                        </div>
                        <div class="grid grid-cols-[minmax(0,55fr)_minmax(0,45fr)] gap-x-3 gap-y-0.5 text-sm">
                          <dt class="text-slate-400">
                            Ngày hết hạn
                          </dt>
                          <dd class="text-right font-medium text-slate-700">
                            {{ c.expiry_date ? formatDate(c.expiry_date) : 'Chưa cập nhật' }}
                          </dd>
                        </div>
                        <div class="grid grid-cols-[minmax(0,55fr)_minmax(0,45fr)] gap-x-3 gap-y-0.5 text-sm">
                          <dt class="text-slate-400">
                            Còn lại
                          </dt>
                          <dd
                            class="text-right text-sm font-semibold"
                            :class="{
                              'text-rose-600': expiryTone(c.days_until_expiry) === 'rose',
                              'text-amber-600': expiryTone(c.days_until_expiry) === 'amber',
                              'text-sky-600': expiryTone(c.days_until_expiry) === 'sky',
                              'text-emerald-600': expiryTone(c.days_until_expiry) === 'emerald',
                              'text-slate-500': expiryTone(c.days_until_expiry) === 'slate',
                            }"
                          >
                            {{ c.expiry_date ? expiryLabel(c.days_until_expiry) : 'Chưa cập nhật' }}
                          </dd>
                        </div>
                        <div class="grid grid-cols-[minmax(0,55fr)_minmax(0,45fr)] gap-x-3 gap-y-0.5 text-sm">
                          <dt class="text-slate-400">
                            Tự động gia hạn
                          </dt>
                          <dd class="text-right font-medium text-slate-700">
                            {{ c.auto_renew ? 'Có' : 'Không' }}
                          </dd>
                        </div>
                        <div
                          v-if="c.notice_period_days != null"
                          class="grid grid-cols-[minmax(0,55fr)_minmax(0,45fr)] gap-x-3 gap-y-0.5 text-sm"
                        >
                          <dt class="text-slate-400">
                            Báo trước hết hạn
                          </dt>
                          <dd class="text-right font-medium text-slate-700">
                            {{ c.notice_period_days }} ngày
                          </dd>
                        </div>
                        <div
                          v-if="c.renewal_term_months"
                          class="grid grid-cols-[minmax(0,55fr)_minmax(0,45fr)] gap-x-3 gap-y-0.5 text-sm"
                        >
                          <dt class="text-slate-400">
                            Kỳ gia hạn
                          </dt>
                          <dd class="text-right font-medium text-slate-700">
                            {{ c.renewal_term_months }} tháng
                          </dd>
                        </div>
                      </dl>
                    </section>
                  </div>

                  <!-- Right main: Chi phí + Mô tả -->
                  <div class="flex min-w-0 flex-col gap-4">
                    <!-- Card 3: Tổng quan chi phí -->
                    <section class="card p-4">
                      <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <h3 class="font-display text-sm font-semibold text-slate-800">
                          Chi phí nhanh
                        </h3>
                        <button
                          v-if="financeRowCount > 0 && !isRenewalSuccessor"
                          type="button"
                          class="text-xs font-medium text-brand hover:underline"
                          @click="tab = 'finance'"
                        >
                          {{ financeRowCount }} dòng chi tiết →
                        </button>
                      </div>
                      <div class="grid grid-cols-2 gap-3 sm:grid-cols-2 lg:grid-cols-2">
                        <div class="rounded-lg bg-slate-50 p-3">
                          <p class="text-xs text-slate-400">
                            Tiền tệ
                          </p>
                          <p class="mt-0.5 text-sm font-semibold text-slate-800">
                            {{ orNA(c.currency) }}
                          </p>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-3">
                          <p class="text-xs text-slate-400">
                            Đơn giá
                          </p>
                          <p class="mt-0.5 text-sm font-semibold text-slate-800">
                            {{ unitPriceDisplay.primary }}
                          </p>
                          <p
                            v-if="unitPriceDisplay.secondary"
                            class="mt-0.5 text-[10px] text-slate-500"
                          >
                            {{ unitPriceDisplay.secondary }}
                          </p>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-3">
                          <p class="text-xs text-slate-400">
                            Hàng tháng
                          </p>
                          <p class="mt-0.5 text-sm font-semibold text-slate-800">
                            {{ monthlyDisplay.primary }}
                          </p>
                          <p
                            v-if="monthlyDisplay.secondary"
                            class="mt-0.5 text-[10px] text-slate-500"
                          >
                            {{ monthlyDisplay.secondary }}
                          </p>
                        </div>
                        <div class="rounded-lg bg-brand/5 p-3 ring-1 ring-brand/20">
                          <p class="text-xs text-brand/70">
                            Chi phí năm
                          </p>
                          <p class="text-[10px] text-brand/60">
                            (= Tổng tiền hợp đồng)
                          </p>
                          <p class="mt-0.5 text-sm font-bold text-brand">
                            {{ annualDisplay.primary }}
                          </p>
                          <p
                            v-if="annualDisplay.secondary"
                            class="mt-0.5 text-[10px] text-brand/60"
                          >
                            {{ annualDisplay.secondary }}
                          </p>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-3 sm:col-span-2">
                          <p class="text-xs text-slate-400">
                            Tổng vòng đời
                          </p>
                          <p class="mt-0.5 text-sm font-semibold text-slate-800">
                            {{ lifecycleDisplay.primary }}
                          </p>
                          <p
                            v-if="lifecycleDisplay.secondary"
                            class="mt-0.5 text-[10px] text-slate-500"
                          >
                            {{ lifecycleDisplay.secondary }}
                          </p>
                        </div>
                      </div>
                    </section>

                    <!-- Card 4: Mô tả -->
                    <section
                      v-if="c.description"
                      class="card p-4"
                    >
                      <h3 class="mb-2 font-display text-sm font-semibold text-slate-800">
                        Mô tả
                      </h3>
                      <p class="whitespace-pre-line text-sm leading-relaxed text-slate-600">
                        {{ c.description }}
                      </p>
                    </section>
                  </div>
                </div>
              </div>

              <!-- ══════════════════════════════════════════════════════
           TAB: TÀI CHÍNH
      ══════════════════════════════════════════════════════ -->
              <div
                v-else-if="tab === 'finance'"
                class="space-y-4"
              >
                <div
                  v-if="!c.root_contract_id"
                  class="flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-700"
                >
                  <AppIcon
                    name="info"
                    :size="14"
                    class="shrink-0"
                  />
                  Mỗi dòng tài chính gắn với <strong>hợp đồng gốc</strong> hoặc <strong>phụ lục gia hạn</strong> — chọn đúng khi thêm mới.
                </div>

                <!-- Toolbar -->
                <div class="flex items-center justify-between">
                  <h3 class="font-display text-sm font-semibold text-slate-700">
                    Dữ liệu tài chính <span
                      v-if="chainFinances.length"
                      class="ml-1 text-xs font-normal text-slate-400"
                    >({{ chainFinances.length }} dòng)</span>
                  </h3>
                  <button
                    v-if="c.can?.update && !showFinanceForm"
                    type="button"
                    class="btn-primary h-8 gap-1.5 px-3 text-xs"
                    @click="openAddFinance"
                  >
                    <AppIcon
                      name="add"
                      :size="13"
                    /> Thêm dữ liệu
                  </button>
                </div>

                <!-- Finance Form Panel -->
                <ContractFinanceFormPanel
                  v-if="showFinanceForm"
                  :editing-id="editingFinanceId"
                  :billing-cycle-label="c.billing_cycle?.label ?? 'Chưa chọn'"
                  :finance-targets="financeTargets"
                  @submit="submitFinance"
                  @cancel="cancelFinance"
                />

                <!-- Finance Table -->
                <div
                  v-if="chainFinances.length"
                  class="card overflow-hidden p-0"
                >
                  <div class="overflow-x-auto">
                    <table class="w-full min-w-[1120px] text-sm">
                      <thead class="border-b border-slate-200 bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                          <th class="px-3 py-2.5 font-medium">
                            Thuộc HĐ
                          </th>
                          <th class="px-3 py-2.5 font-medium">
                            Ngày SD
                          </th>
                          <th class="px-3 py-2.5 font-medium">
                            Đến hạn
                          </th>
                          <th class="px-3 py-2.5 text-right font-medium">
                            SL
                          </th>
                          <th class="px-3 py-2.5 text-right font-medium">
                            Đơn giá
                          </th>
                          <th class="px-3 py-2.5 text-right font-medium">
                            Phí khởi tạo
                          </th>
                          <th class="px-3 py-2.5 text-right font-medium">
                            Phí DT / tháng
                          </th>
                          <th class="px-3 py-2.5 text-right font-medium">
                            Phí gia hạn
                          </th>
                          <th class="px-3 py-2.5 text-right font-semibold text-slate-700">
                            Tổng HĐ
                          </th>
                          <th class="px-3 py-2.5 font-medium">
                            Ghi chú
                          </th>
                          <th
                            v-if="c.can?.update"
                            class="px-3 py-2.5 text-right font-medium"
                          />
                        </tr>
                      </thead>
                      <tbody>
                        <tr
                          v-for="f in chainFinances"
                          :key="`${f.contract_id}-${f.id}`"
                          class="border-t border-slate-100 align-top hover:bg-slate-50/70"
                        >
                          <td class="whitespace-nowrap px-3 py-2.5">
                            <span
                              class="inline-flex flex-col gap-0.5"
                            >
                              <span
                                class="rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                                :class="f.contract_kind === 'root'
                                  ? 'bg-brand/10 text-brand'
                                  : 'bg-sky-100 text-sky-700'"
                              >{{ f.contract_label }}</span>
                              <span class="font-mono text-[11px] text-slate-500">{{ f.contract_code }}</span>
                            </span>
                          </td>
                          <td class="whitespace-nowrap px-3 py-2.5 text-slate-600">
                            {{ f.used_date ? formatDate(f.used_date) : '—' }}
                          </td>
                          <td class="whitespace-nowrap px-3 py-2.5">
                            <template v-if="financeDueDate(f)">
                              <span class="block font-medium text-slate-700">{{ formatDate(financeDueDate(f)) }}</span>
                              <span class="text-[10px] text-slate-400">{{ f.term_months }} tháng</span>
                            </template>
                            <span
                              v-else
                              class="text-slate-300"
                            >—</span>
                          </td>
                          <td class="whitespace-nowrap px-3 py-2.5 text-right tabular-nums text-slate-600">
                            {{ f.quantity != null ? Number(f.quantity).toLocaleString('vi-VN') : '—' }}
                          </td>
                          <td class="whitespace-nowrap px-3 py-2.5 text-right tabular-nums text-slate-600">
                            {{ f.unit_price != null ? formatMoney(f.unit_price, c.currency) : '—' }}
                          </td>
                          <td class="whitespace-nowrap px-3 py-2.5 text-right tabular-nums text-slate-600">
                            {{ f.init_fee != null ? formatMoney(f.init_fee, c.currency) : '—' }}
                          </td>
                          <td class="whitespace-nowrap px-3 py-2.5 text-right tabular-nums text-slate-600">
                            {{ f.maintenance_fee != null ? formatMoney(f.maintenance_fee, c.currency) : '—' }}
                          </td>
                          <td class="whitespace-nowrap px-3 py-2.5 text-right tabular-nums text-slate-600">
                            {{ f.renewal_cost != null ? formatMoney(f.renewal_cost, c.currency) : '—' }}
                          </td>
                          <td class="whitespace-nowrap px-3 py-2.5 text-right tabular-nums">
                            <template v-if="f.total != null">
                              <span class="block font-semibold text-slate-800">{{ formatMoney(f.total, c.currency) }}</span>
                              <span class="text-[10px] text-slate-400">{{ formatVndDisplay(f.total).secondary }}</span>
                            </template>
                            <span
                              v-else
                              class="text-slate-300"
                            >—</span>
                          </td>
                          <td class="max-w-[200px] px-3 py-2.5 text-slate-500">
                            <span
                              v-if="f.note"
                              class="line-clamp-2"
                              :title="f.note"
                            >{{ f.note }}</span>
                            <span
                              v-else
                              class="text-slate-300"
                            >—</span>
                          </td>
                          <td
                            v-if="c.can?.update"
                            class="whitespace-nowrap px-3 py-2.5 text-right"
                          >
                            <div class="flex items-center justify-end gap-1">
                              <button
                                type="button"
                                class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                                title="Sửa"
                                @click="openEditFinance(f)"
                              >
                                <AppIcon
                                  name="edit"
                                  :size="13"
                                />
                              </button>
                              <button
                                type="button"
                                class="rounded p-1 text-slate-400 hover:bg-rose-50 hover:text-rose-600"
                                title="Xoá"
                                @click="deleteFinance(f)"
                              >
                                <AppIcon
                                  name="delete"
                                  :size="13"
                                />
                              </button>
                            </div>
                          </td>
                        </tr>
                      </tbody>
                      <tfoot
                        v-if="chainFinances.length > 1"
                        class="border-t-2 border-slate-200 bg-slate-50 text-xs"
                      >
                        <tr>
                          <td
                            class="px-3 py-2.5 font-semibold text-slate-600"
                            colspan="5"
                          >
                            Tổng cộng ({{ chainFinances.length }} dòng)
                          </td>
                          <td class="whitespace-nowrap px-3 py-2.5 text-right tabular-nums text-slate-600">
                            {{ financeTotals.init_fee ? formatMoney(financeTotals.init_fee, c.currency) : '—' }}
                          </td>
                          <td class="whitespace-nowrap px-3 py-2.5 text-right tabular-nums text-slate-600">
                            {{ financeTotals.maintenance_fee ? formatMoney(financeTotals.maintenance_fee, c.currency) : '—' }}
                          </td>
                          <td class="whitespace-nowrap px-3 py-2.5 text-right tabular-nums text-slate-600">
                            {{ financeTotals.renewal_cost ? formatMoney(financeTotals.renewal_cost, c.currency) : '—' }}
                          </td>
                          <td class="whitespace-nowrap px-3 py-2.5 text-right tabular-nums font-bold text-brand">
                            {{ financeTotals.total ? formatMoney(financeTotals.total, c.currency) : '—' }}
                          </td>
                          <td :colspan="c.can?.update ? 2 : 1" />
                        </tr>
                      </tfoot>
                    </table>
                  </div>
                </div>

                <!-- Empty state -->
                <div
                  v-else-if="!showFinanceForm"
                  class="rounded-xl border-2 border-dashed border-slate-200 py-12 text-center"
                >
                  <AppIcon
                    name="budget"
                    :size="32"
                    class="mx-auto mb-3 text-slate-300"
                  />
                  <p class="text-sm font-medium text-slate-500">
                    Chưa có dữ liệu tài chính
                  </p>
                  <p class="mt-1 text-xs text-slate-400">
                    Nhấn "Thêm dữ liệu" để bắt đầu nhập số liệu tài chính cho hợp đồng.
                  </p>
                  <button
                    v-if="c.can?.update"
                    type="button"
                    class="btn-primary mt-4 text-xs"
                    @click="openAddFinance"
                  >
                    <AppIcon
                      name="add"
                      :size="13"
                    /> Thêm dữ liệu tài chính
                  </button>
                </div>
              </div>

              <!-- ══════════════════════════════════════════════════════
           TAB: ĐÁNH GIÁ NCC
      ══════════════════════════════════════════════════════ -->
              <div
                v-else-if="tab === 'evaluation'"
                class="space-y-4"
              >
                <!-- Header + avg score -->
                <div class="flex flex-wrap items-center justify-between gap-3">
                  <div>
                    <h3 class="font-display text-sm font-semibold text-slate-800">
                      Đánh giá nhà cung cấp{{ c.vendor?.name ? ` · ${c.vendor.name}` : '' }}
                    </h3>
                    <p
                      v-if="avgReviewScore != null"
                      class="mt-0.5 text-xs text-slate-500"
                    >
                      Điểm trung bình hợp đồng này: <strong class="text-amber-600">★ {{ avgReviewScore }} / 10</strong>
                    </p>
                  </div>
                  <div class="flex items-center gap-2">
                    <a
                      v-if="c.vendor"
                      :href="`/contracts/vendors/${c.vendor.id}`"
                      class="text-xs font-medium text-brand hover:underline"
                    >
                      Xem trung bình NCC →
                    </a>
                    <button
                      v-if="c.can?.update && c.vendor_id && !showReviewForm"
                      type="button"
                      class="btn-primary h-8 gap-1.5 px-3 text-xs"
                      @click="showReviewForm = true"
                    >
                      <AppIcon
                        name="add"
                        :size="13"
                      /> Thêm đánh giá
                    </button>
                  </div>
                </div>

                <!-- Review add form -->
                <div
                  v-if="showReviewForm"
                  class="card overflow-hidden p-0"
                >
                  <div class="border-b border-slate-100 bg-slate-50/80 px-4 py-3 sm:px-5">
                    <h4 class="font-display text-sm font-semibold text-slate-800">
                      Thêm đánh giá nhà cung cấp
                    </h4>
                    <p class="mt-0.5 text-xs text-slate-500">
                      Chấm điểm 6 tiêu chí (0–10). Trường có dấu <span class="font-semibold text-danger">*</span> là bắt buộc.
                    </p>
                  </div>

                  <form
                    class="space-y-6 p-4 sm:p-5"
                    @submit.prevent="submitReview"
                  >
                    <!-- Thông tin chung -->
                    <fieldset class="min-w-0 space-y-3">
                      <legend class="mb-1 flex w-full items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <AppIcon
                          name="info"
                          :size="14"
                          class="text-brand/70"
                        />
                        Thông tin đánh giá
                      </legend>
                      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="min-w-0">
                          <VendorFieldLabel
                            label="Người đánh giá"
                            required
                            tooltip="Nhân sự thực hiện đánh giá nhà cung cấp này. Gõ tên hoặc email để tìm."
                          />
                          <EmployeeAutocomplete
                            v-model="reviewForm.reviewer_id"
                            :options="options.employees || []"
                          />
                          <p
                            v-if="reviewForm.errors.reviewer_id"
                            class="mt-1 text-xs text-rose-600"
                          >
                            {{ reviewForm.errors.reviewer_id }}
                          </p>
                        </div>
                        <div class="min-w-0">
                          <VendorFieldLabel
                            for-id="review-date"
                            label="Ngày đánh giá"
                            tooltip="Thời điểm thực hiện đánh giá. Mặc định là hôm nay."
                          />
                          <input
                            id="review-date"
                            v-model="reviewForm.reviewed_at"
                            type="date"
                            class="input h-10 w-full text-sm"
                          >
                        </div>
                      </div>
                    </fieldset>

                    <!-- Điểm tiêu chí -->
                    <fieldset class="min-w-0 space-y-3">
                      <legend class="mb-1 flex w-full items-center justify-between gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <span class="flex items-center gap-2">
                          <AppIcon
                            name="star"
                            :size="14"
                            class="text-brand/70"
                          />
                          Điểm theo tiêu chí (0 – 10)
                        </span>
                        <span
                          v-if="reviewLivePreview != null"
                          class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-0.5 text-[11px] font-semibold normal-case text-amber-700"
                        >
                          TB tạm tính: ★ {{ reviewLivePreview }} / 10
                        </span>
                      </legend>
                      <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        <div
                          v-for="cr in reviewCriteria"
                          :key="cr.key"
                          class="min-w-0"
                        >
                          <VendorFieldLabel
                            :for-id="`review-${cr.key}`"
                            :label="cr.label"
                            compact
                            :tooltip="cr.tip"
                          />
                          <input
                            :id="`review-${cr.key}`"
                            v-model="reviewForm[cr.key]"
                            type="number"
                            min="0"
                            max="10"
                            step="0.5"
                            class="input h-9 w-full text-sm tabular-nums"
                            placeholder="0–10"
                          >
                        </div>
                      </div>
                    </fieldset>

                    <!-- Kết luận -->
                    <fieldset class="min-w-0 space-y-3">
                      <legend class="mb-1 flex w-full items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <AppIcon
                          name="documents"
                          :size="14"
                          class="text-brand/70"
                        />
                        Kết luận
                      </legend>
                      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="min-w-0">
                          <VendorFieldLabel
                            for-id="review-recommendation"
                            label="Đề xuất"
                            tooltip="Khuyến nghị sau đánh giá: tiếp tục gia hạn, đổi NCC, cần review thêm…"
                          />
                          <select
                            id="review-recommendation"
                            v-model="reviewForm.recommendation"
                            class="input h-10 w-full text-sm"
                          >
                            <option :value="null">
                              — Chọn đề xuất —
                            </option>
                            <option
                              v-for="opt in (options.recommendationOptions || [])"
                              :key="opt.value"
                              :value="opt.value"
                            >
                              {{ opt.label }}
                            </option>
                          </select>
                        </div>
                        <div class="min-w-0">
                          <VendorFieldLabel
                            for-id="review-note"
                            label="Ghi chú"
                            tooltip="Nhận xét chi tiết, dẫn chứng cụ thể cho điểm số."
                          />
                          <textarea
                            id="review-note"
                            v-model="reviewForm.note"
                            rows="2"
                            class="input w-full text-sm"
                            placeholder="VD: SLA tốt, hỗ trợ nhanh; giá tăng 10% so với năm trước…"
                          />
                        </div>
                      </div>
                    </fieldset>

                    <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
                      <button
                        type="button"
                        class="btn-ghost h-9"
                        @click="cancelReview"
                      >
                        Huỷ
                      </button>
                      <button
                        type="submit"
                        class="btn-primary h-9"
                        :disabled="reviewForm.processing"
                      >
                        {{ reviewForm.processing ? 'Đang lưu…' : 'Lưu đánh giá' }}
                      </button>
                    </div>
                  </form>
                </div>

                <!-- No vendor warning -->
                <div
                  v-if="!c.vendor_id"
                  class="rounded-lg border border-dashed border-slate-200 py-8 text-center text-sm text-slate-400"
                >
                  Hợp đồng chưa gán nhà cung cấp. Chỉnh sửa hợp đồng để thêm NCC trước khi đánh giá.
                </div>

                <!-- Reviews list -->
                <template v-else-if="reviewsForContract.length">
                  <div
                    v-for="r in reviewsForContract"
                    :key="r.id"
                    class="card p-4"
                  >
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                      <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-slate-800">{{ r.reviewed_at ? formatDate(r.reviewed_at) : 'Chưa cập nhật ngày' }}</span>
                        <Badge
                          v-if="r.recommendation"
                          :label="r.recommendation.label"
                          :color="r.recommendation.color"
                        />
                      </div>
                      <div class="flex items-center gap-2">
                        <span class="text-xs text-slate-400">
                          {{ r.reviewer?.name ?? 'Chưa rõ người đánh giá' }} · Tổng điểm:
                          <strong :class="scoreColor(r.total_score)">
                            {{ r.total_score != null ? r.total_score : 'Chưa có' }}
                          </strong>
                        </span>
                        <button
                          v-if="c.can?.update"
                          type="button"
                          class="rounded p-1 text-slate-400 hover:bg-rose-50 hover:text-rose-600"
                          @click="deleteReview(r)"
                        >
                          <AppIcon
                            name="delete"
                            :size="13"
                          />
                        </button>
                      </div>
                    </div>
                    <div class="grid grid-cols-2 gap-x-4 gap-y-2 sm:grid-cols-3">
                      <div
                        v-for="cr in reviewCriteria"
                        :key="cr.key"
                        class="flex items-center justify-between text-xs"
                      >
                        <span class="text-slate-400">{{ cr.label }}</span>
                        <span
                          class="font-medium"
                          :class="scoreColor(r[cr.key])"
                        >
                          {{ r[cr.key] != null ? r[cr.key] : 'Chưa nhập' }}
                        </span>
                      </div>
                    </div>
                    <p
                      v-if="r.note"
                      class="mt-3 border-t border-slate-100 pt-2 text-xs text-slate-500"
                    >
                      {{ r.note }}
                    </p>
                  </div>
                </template>

                <!-- Empty state -->
                <div
                  v-else-if="!showReviewForm"
                  class="rounded-xl border-2 border-dashed border-slate-200 py-12 text-center"
                >
                  <AppIcon
                    name="star"
                    :size="32"
                    class="mx-auto mb-3 text-slate-300"
                  />
                  <p class="text-sm font-medium text-slate-500">
                    Hợp đồng chưa được đánh giá
                  </p>
                  <p class="mt-1 text-xs text-slate-400">
                    Thêm đánh giá để theo dõi chất lượng dịch vụ của nhà cung cấp.
                  </p>
                </div>
              </div>

              <!-- ══════════════════════════════════════════════════════
           TAB: HỒ SƠ
      ══════════════════════════════════════════════════════ -->
              <div
                v-else-if="tab === 'documents'"
                class="card p-4"
              >
                <ContractDocuments
                  :contract-id="c.id"
                  :attachments="c.attachments || []"
                  :categories="options.attachmentCategories || []"
                  :can-manage="!!c.can?.update"
                />
              </div>

              <!-- ══════════════════════════════════════════════════════
           TAB: GIA HẠN (Addenda)
      ══════════════════════════════════════════════════════ -->
              <div
                v-else-if="tab === 'renewals'"
                class="space-y-4"
              >
                <div class="flex items-center justify-between">
                  <h3 class="font-display text-sm font-semibold text-slate-700">
                    Lịch sử gia hạn
                    <span
                      v-if="addenda.length"
                      class="ml-1 text-xs font-normal text-slate-400"
                    >({{ addenda.length }} phụ lục)</span>
                  </h3>
                  <button
                    v-if="c.can?.update"
                    type="button"
                    class="btn-primary h-8 gap-1.5 px-3 text-xs"
                    @click="showRenew = true"
                  >
                    <AppIcon
                      name="renewal"
                      :size="13"
                    /> Gia hạn
                  </button>
                </div>

                <!-- Addenda cards -->
                <template v-if="addenda.length">
                  <a
                    v-for="a in addenda"
                    :key="a.id"
                    :href="`/contracts/${a.id}`"
                    class="card block p-4 transition-shadow hover:shadow-md"
                  >
                    <div class="flex flex-wrap items-center justify-between gap-2">
                      <div class="flex items-center gap-2">
                        <span class="rounded bg-slate-100 px-2 py-0.5 font-mono text-xs text-slate-600">{{ a.code }}</span>
                        <Badge
                          v-if="a.status"
                          :label="a.status.label"
                          :color="a.status.color"
                        />
                      </div>
                      <div class="flex items-center gap-2">
                        <span
                          v-if="a.attachments_count > 0"
                          class="flex items-center gap-1 text-xs text-slate-400"
                        >
                          <AppIcon
                            name="documents"
                            :size="12"
                          />
                          {{ a.attachments_count }} hồ sơ
                        </span>
                        <span class="text-xs text-slate-400">→</span>
                      </div>
                    </div>
                    <p class="mt-2 text-sm font-medium text-slate-800">{{ a.name }}</p>
                    <div class="mt-1 flex flex-wrap items-center gap-3 text-xs text-slate-500">
                      <span v-if="a.effective_date">
                        Hiệu lực: {{ formatDate(a.effective_date) }}
                      </span>
                      <span v-if="a.expiry_date">
                        Hết hạn: {{ formatDate(a.expiry_date) }}
                      </span>
                      <span
                        v-if="a.annual_cost != null"
                        class="font-medium text-slate-700"
                      >
                        {{ formatMoney(a.annual_cost, a.currency) }}
                      </span>
                    </div>
                  </a>
                </template>

                <!-- Empty state -->
                <div
                  v-else
                  class="rounded-xl border-2 border-dashed border-slate-200 py-12 text-center"
                >
                  <AppIcon
                    name="renewal"
                    :size="32"
                    class="mx-auto mb-3 text-slate-300"
                  />
                  <p class="text-sm font-medium text-slate-500">
                    Chưa có lần gia hạn nào
                  </p>
                  <p class="mt-1 text-xs text-slate-400">
                    Nhấn "Gia hạn" để tạo phụ lục gia hạn cho hợp đồng.
                  </p>
                  <button
                    v-if="c.can?.update"
                    type="button"
                    class="btn-primary mt-4 text-xs"
                    @click="showRenew = true"
                  >
                    <AppIcon
                      name="renewal"
                      :size="13"
                    /> Gia hạn
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ── Modals ─────────────────────────────────────────────────────── -->
      <ContractFormModal
        :show="showEdit"
        :contract="c"
        :vendors="options.vendors || []"
        :categories="options.categories || []"
        :employees="options.employees || []"
        :status-options="options.status || []"
        :billing-options="options.billingCycle || []"
        @close="showEdit = false"
        @saved="() => router.reload({ only: ['contract'] })"
      />

      <RenewalQuickModal
        :show="showRenew"
        :contract="c"
        @close="showRenew = false"
        @saved="() => router.reload({ only: ['contract'] })"
      />
    </div>
  </AppLayout>
</template>

<style scoped>
/* Thu nhỏ toàn bộ workspace chi tiết HĐ ~ Ctrl− 90% (Chrome/Edge); modal giữ 100% */
.contract-show-scale {
  zoom: 0.9;
}
</style>
