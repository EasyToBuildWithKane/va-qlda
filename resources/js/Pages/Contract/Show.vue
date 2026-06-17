<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import ContractFormModal from '@/modules/contract/components/ContractFormModal.vue';
import ContractDocuments from '@/modules/contract/components/ContractDocuments.vue';
import RenewalQuickModal from '@/modules/contract/components/RenewalQuickModal.vue';
import EmployeeAutocomplete from '@/modules/contract/components/EmployeeAutocomplete.vue';
import { useDialog } from '@/composables/useDialog';
import {
    formatMoney,
    formatDate,
    expiryLabel,
    expiryTone,
    formatVndDisplay,
    vndToWords,
} from '@/modules/contract/composables/useContractFormat.js';
import { displayOrEmpty, EMPTY_LABELS } from '@/shared/utils/emptyDisplay.js';

const props = defineProps({
    contract: { type: Object, required: true },
    options: { type: Object, default: () => ({}) },
});

const c = computed(() => props.contract);
const dialog = useDialog();

const tab = ref('overview');
const tabs = computed(() => [
    { key: 'overview',   label: 'Tổng quan',   icon: 'info' },
    { key: 'finance',    label: 'Tài chính',   icon: 'budget' },
    { key: 'evaluation', label: 'Đánh giá NCC', icon: 'star' },
    { key: 'documents',  label: 'Hồ sơ',       icon: 'documents' },
    { key: 'renewals',   label: 'Gia hạn',      icon: 'renewal' },
]);

// ─── Header / Status ────────────────────────────────────────────────────────
const reviewsForContract = computed(() => c.value.reviews_for_contract ?? []);
const avgReviewScore = computed(() => {
    const scores = reviewsForContract.value.map((r) => r.total_score).filter((s) => s != null);
    if (!scores.length) return null;
    return Math.round((scores.reduce((a, b) => a + b, 0) / scores.length) * 10) / 10;
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

const annualDisplay = computed(() => {
    const amt = c.value.annual_cost ?? c.value.annual_cost_resolved;
    return formatVndDisplay(amt);
});

// ─── Finance Tab ─────────────────────────────────────────────────────────────
const showFinanceForm = ref(false);
const editingFinanceId = ref(null);

const financeForm = useForm({
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

function openAddFinance() {
    editingFinanceId.value = null;
    financeForm.reset();
    showFinanceForm.value = true;
}

function openEditFinance(f) {
    editingFinanceId.value = f.id;
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
    financeForm.reset();
}

function submitFinance() {
    const base = `/contracts/${c.value.id}/finances`;
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
    router.delete(`/contracts/${c.value.id}/finances/${f.id}`, {
        preserveScroll: true,
        onSuccess() { router.reload({ only: ['contract'] }); },
    });
}

const computedAnnual = computed(() => {
    const mf = Number(financeForm.maintenance_fee || 0);
    return mf > 0 ? mf * 12 : null;
});

// ─── Evaluation Tab ──────────────────────────────────────────────────────────
const reviewCriteria = [
    { key: 'service_quality',   label: 'Chất lượng DV' },
    { key: 'sla',               label: 'SLA' },
    { key: 'speed',             label: 'Tốc độ' },
    { key: 'price_satisfaction', label: 'Giá hài lòng' },
    { key: 'stability',         label: 'Ổn định' },
    { key: 'attitude',          label: 'Thái độ' },
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
  <AppLayout>
    <template #header>
      <PageHeader
        :title="c.name"
        :subtitle="c.code"
        icon="contract"
        icon-color="brand"
        back-href="/contracts"
      >
        <Badge
          v-if="c.status"
          :label="c.status?.label"
          :color="c.status?.color"
        />
        <Badge
          v-if="avgReviewScore != null"
          :label="`★ ${avgReviewScore}`"
          color="amber"
        />
        <button
          v-if="c.can?.update"
          type="button"
          class="btn-ghost h-9 gap-1.5 px-3 text-xs"
          @click="showRenew = true"
        >
          <AppIcon
            name="renewal"
            :size="15"
          /> Gia hạn
        </button>
        <button
          v-if="c.can?.update"
          type="button"
          class="btn-ghost h-9 gap-1.5 px-3 text-xs"
          @click="showEdit = true"
        >
          <AppIcon
            name="edit"
            :size="15"
          /> Sửa
        </button>
        <button
          v-if="c.can?.delete"
          type="button"
          class="btn-ghost h-9 gap-1.5 px-3 text-xs text-rose-600"
          @click="onDelete"
        >
          <AppIcon
            name="delete"
            :size="15"
          /> Xoá
        </button>
      </PageHeader>
    </template>

    <div class="mx-auto max-w-5xl px-4 py-5">
      <!-- Tab nav -->
      <div class="mb-5 flex gap-1 overflow-x-auto border-b border-slate-200">
        <button
          v-for="t in tabs"
          :key="t.key"
          type="button"
          class="flex shrink-0 items-center gap-1.5 border-b-2 px-3 py-2 text-sm font-medium transition-colors"
          :class="tab === t.key
            ? 'border-brand text-brand'
            : 'border-transparent text-slate-500 hover:text-slate-700'"
          @click="tab = t.key"
        >
          <AppIcon
            :name="t.icon"
            :size="15"
          />
          {{ t.label }}
        </button>
      </div>

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
          <span>Đây là <strong>hợp đồng phụ lục</strong> (trạng thái: Chuyển phụ lục).</span>
          <a
            :href="`/contracts/${c.root_contract_id}`"
            class="ml-auto shrink-0 font-medium text-sky-600 hover:underline"
          >
            Xem hợp đồng gốc →
          </a>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <!-- Card 1: Thông tin chung -->
          <section class="card p-4">
            <h3 class="mb-3 font-display text-sm font-semibold text-slate-800">
              Thông tin chung
            </h3>
            <dl class="space-y-2.5">
              <div class="flex justify-between gap-3 text-sm">
                <dt class="text-slate-400">
                  Mã hợp đồng
                </dt>
                <dd class="text-right font-mono font-semibold text-slate-700">
                  {{ c.code }}
                </dd>
              </div>
              <div class="flex justify-between gap-3 text-sm">
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
              <div class="flex justify-between gap-3 text-sm">
                <dt class="text-slate-400">
                  Nhóm dịch vụ
                </dt>
                <dd class="text-right font-medium text-slate-700">
                  {{ orNA(c.category?.name, 'Chưa phân loại') }}
                </dd>
              </div>
              <div class="flex justify-between gap-3 text-sm">
                <dt class="text-slate-400">
                  Đơn vị sử dụng
                </dt>
                <dd class="text-right font-medium text-slate-700">
                  {{ orNA(c.using_unit) }}
                </dd>
              </div>
              <div class="flex justify-between gap-3 text-sm">
                <dt class="text-slate-400">
                  Người phụ trách
                </dt>
                <dd class="text-right font-medium text-slate-700">
                  {{ orNA(c.owner?.name, 'Chưa gán') }}
                </dd>
              </div>
              <div class="flex justify-between gap-3 text-sm">
                <dt class="text-slate-400">
                  Người quản lý
                </dt>
                <dd class="text-right font-medium text-slate-700">
                  {{ orNA(c.manager?.name, 'Chưa gán') }}
                </dd>
              </div>
              <div class="flex justify-between gap-3 text-sm">
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
            <dl class="space-y-2.5">
              <div class="flex justify-between gap-3 text-sm">
                <dt class="text-slate-400">
                  Ngày ký
                </dt>
                <dd class="text-right font-medium text-slate-700">
                  {{ c.signed_date ? formatDate(c.signed_date) : 'Chưa cập nhật' }}
                </dd>
              </div>
              <div class="flex justify-between gap-3 text-sm">
                <dt class="text-slate-400">
                  Ngày hiệu lực
                </dt>
                <dd class="text-right font-medium text-slate-700">
                  {{ c.effective_date ? formatDate(c.effective_date) : 'Chưa cập nhật' }}
                </dd>
              </div>
              <div class="flex justify-between gap-3 text-sm">
                <dt class="text-slate-400">
                  Ngày hết hạn
                </dt>
                <dd class="text-right font-medium text-slate-700">
                  {{ c.expiry_date ? formatDate(c.expiry_date) : 'Chưa cập nhật' }}
                </dd>
              </div>
              <div class="flex justify-between gap-3 text-sm">
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
              <div class="flex justify-between gap-3 text-sm">
                <dt class="text-slate-400">
                  Tự động gia hạn
                </dt>
                <dd class="text-right font-medium text-slate-700">
                  {{ c.auto_renew ? 'Có' : 'Không' }}
                </dd>
              </div>
              <div
                v-if="c.renewal_term_months"
                class="flex justify-between gap-3 text-sm"
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

          <!-- Card 3: Tổng quan chi phí -->
          <section class="card p-4 sm:col-span-2">
            <h3 class="mb-3 font-display text-sm font-semibold text-slate-800">
              Chi phí nhanh
            </h3>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
              <div class="rounded-lg bg-slate-50 p-3">
                <p class="text-xs text-slate-400">
                  Đơn giá
                </p>
                <p class="mt-0.5 text-sm font-semibold text-slate-800">
                  {{ c.unit_price != null ? formatMoney(c.unit_price, c.currency) : 'Chưa cập nhật' }}
                </p>
              </div>
              <div class="rounded-lg bg-slate-50 p-3">
                <p class="text-xs text-slate-400">
                  Hàng tháng
                </p>
                <p class="mt-0.5 text-sm font-semibold text-slate-800">
                  {{ c.monthly_cost != null ? formatMoney(c.monthly_cost, c.currency) : 'Chưa cập nhật' }}
                </p>
              </div>
              <div class="rounded-lg bg-brand/5 p-3 ring-1 ring-brand/20">
                <p class="text-xs text-brand/70">
                  Hàng năm
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
              <div class="rounded-lg bg-slate-50 p-3">
                <p class="text-xs text-slate-400">
                  Thanh toán
                </p>
                <Badge
                  v-if="c.payment_status"
                  class="mt-1"
                  :label="c.payment_status.label"
                  :color="c.payment_status.color"
                />
                <p
                  v-else
                  class="mt-0.5 text-sm italic text-slate-400"
                >
                  Chưa cập nhật
                </p>
              </div>
            </div>
          </section>

          <!-- Card 4: Mô tả -->
          <section
            v-if="c.description"
            class="card p-4 sm:col-span-2"
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

      <!-- ══════════════════════════════════════════════════════
           TAB: TÀI CHÍNH
      ══════════════════════════════════════════════════════ -->
      <div
        v-else-if="tab === 'finance'"
        class="space-y-4"
      >
        <!-- Master data badge for root contracts -->
        <div
          v-if="!c.root_contract_id && c.finances?.length"
          class="flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-700"
        >
          <AppIcon
            name="info"
            :size="14"
            class="shrink-0"
          />
          Dữ liệu tài chính gốc (master data) — hợp đồng phụ lục sẽ có bộ số liệu riêng.
        </div>

        <!-- Toolbar -->
        <div class="flex items-center justify-between">
          <h3 class="font-display text-sm font-semibold text-slate-700">
            Dữ liệu tài chính <span
              v-if="c.finances?.length"
              class="ml-1 text-xs font-normal text-slate-400"
            >({{ c.finances.length }} dòng)</span>
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
        <div
          v-if="showFinanceForm"
          class="card p-4"
        >
          <h4 class="mb-4 font-display text-sm font-semibold text-slate-800">
            {{ editingFinanceId ? 'Sửa dữ liệu tài chính' : 'Thêm dữ liệu tài chính' }}
          </h4>
          <form
            class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3"
            @submit.prevent="submitFinance"
          >
            <div>
              <label class="label">Ngày Sử Dụng</label>
              <input
                v-model="financeForm.used_date"
                type="date"
                class="input h-10 w-full text-sm"
              >
              <p
                v-if="financeForm.errors.used_date"
                class="mt-1 text-xs text-rose-600"
              >
                {{ financeForm.errors.used_date }}
              </p>
            </div>
            <div>
              <label class="label">Thời Hạn (tháng)</label>
              <input
                v-model="financeForm.term_months"
                type="number"
                min="0"
                class="input h-10 w-full text-sm"
                placeholder="VD: 12"
              >
              <p
                v-if="financeForm.errors.term_months"
                class="mt-1 text-xs text-rose-600"
              >
                {{ financeForm.errors.term_months }}
              </p>
            </div>
            <div>
              <label class="label">Chu Kỳ TT</label>
              <input
                :value="c.billing_cycle?.label ?? 'Chưa chọn'"
                type="text"
                class="input h-10 w-full bg-slate-50 text-sm text-slate-500"
                disabled
                title="Lấy từ hợp đồng"
              >
              <p class="mt-1 text-[10px] text-slate-400">
                Cập nhật qua chỉnh sửa hợp đồng
              </p>
            </div>
            <div>
              <label class="label">Số lượng</label>
              <input
                v-model="financeForm.quantity"
                type="number"
                min="0"
                step="0.01"
                class="input h-10 w-full text-sm"
              >
            </div>
            <div>
              <label class="label">Đơn giá (VNĐ)</label>
              <input
                v-model="financeForm.unit_price"
                type="number"
                min="0"
                class="input h-10 w-full text-sm"
              >
              <p
                v-if="financeForm.errors.unit_price"
                class="mt-1 text-xs text-rose-600"
              >
                {{ financeForm.errors.unit_price }}
              </p>
            </div>
            <div>
              <label class="label">Số tháng duy trì</label>
              <input
                v-model="financeForm.term_months"
                type="number"
                min="0"
                class="input h-10 w-full text-sm"
                placeholder="VD: 12"
              >
            </div>
            <div>
              <label class="label">Phí Khởi Tạo (VNĐ)</label>
              <input
                v-model="financeForm.init_fee"
                type="number"
                min="0"
                class="input h-10 w-full text-sm"
              >
              <p
                v-if="financeForm.errors.init_fee"
                class="mt-1 text-xs text-rose-600"
              >
                {{ financeForm.errors.init_fee }}
              </p>
            </div>
            <div>
              <label class="label">Phí duy trì / tháng (VNĐ)</label>
              <input
                v-model="financeForm.maintenance_fee"
                type="number"
                min="0"
                class="input h-10 w-full text-sm"
              >
              <p
                v-if="financeForm.errors.maintenance_fee"
                class="mt-1 text-xs text-rose-600"
              >
                {{ financeForm.errors.maintenance_fee }}
              </p>
            </div>
            <div>
              <label class="label">Hàng năm (tự tính)</label>
              <input
                :value="computedAnnual != null ? computedAnnual.toLocaleString('vi-VN') : '—'"
                type="text"
                class="input h-10 w-full bg-slate-50 text-sm text-slate-500"
                disabled
                title="= Phí duy trì × 12"
              >
            </div>
            <div>
              <label class="label">Tổng tiền hợp đồng (VNĐ)</label>
              <input
                v-model="financeForm.total"
                type="number"
                min="0"
                class="input h-10 w-full text-sm"
              >
              <p
                v-if="financeForm.errors.total"
                class="mt-1 text-xs text-rose-600"
              >
                {{ financeForm.errors.total }}
              </p>
            </div>
            <div class="sm:col-span-2 md:col-span-3">
              <label class="label">Ghi chú</label>
              <textarea
                v-model="financeForm.note"
                rows="2"
                class="input w-full text-sm"
              />
            </div>
            <!-- TỔNG TIỀN đọc bằng chữ -->
            <div
              v-if="financeForm.total"
              class="sm:col-span-2 md:col-span-3"
            >
              <p class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">
                <span class="font-medium text-slate-700">Đọc bằng chữ:</span>
                {{ vndToWords(financeForm.total) }}
              </p>
            </div>

            <div class="flex justify-end gap-2 sm:col-span-2 md:col-span-3">
              <button
                type="button"
                class="btn-ghost"
                @click="cancelFinance"
              >
                Huỷ
              </button>
              <button
                type="submit"
                class="btn-primary"
                :disabled="financeForm.processing"
              >
                {{ financeForm.processing ? 'Đang lưu…' : (editingFinanceId ? 'Cập nhật' : 'Lưu dữ liệu') }}
              </button>
            </div>
          </form>
        </div>

        <!-- Finance Table -->
        <div
          v-if="c.finances?.length"
          class="card overflow-hidden p-0"
        >
          <div class="overflow-auto">
            <table class="w-full text-sm">
              <thead class="bg-slate-50 text-left text-xs text-slate-500">
                <tr>
                  <th class="px-3 py-2.5 font-medium">
                    Ngày SD
                  </th>
                  <th class="px-3 py-2.5 text-right font-medium">
                    SL
                  </th>
                  <th class="px-3 py-2.5 text-right font-medium">
                    Đơn giá (VNĐ)
                  </th>
                  <th class="px-3 py-2.5 text-right font-medium">
                    Số tháng
                  </th>
                  <th class="px-3 py-2.5 text-right font-medium">
                    Phí Khởi Tạo (VNĐ)
                  </th>
                  <th class="px-3 py-2.5 text-right font-medium">
                    Phí DT / tháng
                  </th>
                  <th class="px-3 py-2.5 text-right font-medium">
                    Hàng năm
                  </th>
                  <th class="px-3 py-2.5 text-right font-medium font-semibold text-slate-700">
                    TỔNG TIỀN HĐ
                  </th>
                  <th
                    v-if="c.can?.update"
                    class="px-3 py-2.5 text-right font-medium"
                  />
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="f in c.finances"
                  :key="f.id"
                  class="border-t border-slate-100 hover:bg-slate-50"
                >
                  <td class="px-3 py-2 text-slate-600">
                    {{ f.used_date ? formatDate(f.used_date) : 'Chưa nhập' }}
                  </td>
                  <td class="px-3 py-2 text-right">
                    {{ f.quantity != null ? Number(f.quantity).toLocaleString('vi-VN') : '' }}
                  </td>
                  <td class="px-3 py-2 text-right">
                    <template v-if="f.unit_price != null">
                      <span class="block">{{ formatMoney(f.unit_price, c.currency) }}</span>
                      <span class="text-[10px] text-slate-400">{{ formatVndDisplay(f.unit_price).secondary }}</span>
                    </template>
                    <span
                      v-else
                      class="text-slate-400"
                    />
                  </td>
                  <td class="px-3 py-2 text-right text-slate-600">
                    {{ f.term_months ? `${f.term_months} th` : '' }}
                  </td>
                  <td class="px-3 py-2 text-right">
                    <template v-if="f.init_fee != null">
                      <span class="block">{{ formatMoney(f.init_fee, c.currency) }}</span>
                      <span class="text-[10px] text-slate-400">{{ formatVndDisplay(f.init_fee).secondary }}</span>
                    </template>
                  </td>
                  <td class="px-3 py-2 text-right">
                    <template v-if="f.maintenance_fee != null">
                      <span class="block">{{ formatMoney(f.maintenance_fee, c.currency) }}</span>
                      <span class="text-[10px] text-slate-400">{{ formatVndDisplay(f.maintenance_fee).secondary }}</span>
                    </template>
                  </td>
                  <td class="px-3 py-2 text-right text-slate-600">
                    <template v-if="f.maintenance_fee != null">
                      {{ formatMoney(f.maintenance_fee * 12, c.currency) }}
                    </template>
                  </td>
                  <td class="px-3 py-2 text-right">
                    <template v-if="f.total != null">
                      <span class="block font-semibold text-slate-800">{{ formatMoney(f.total, c.currency) }}</span>
                      <span class="text-[10px] text-slate-500">{{ vndToWords(f.total) }}</span>
                    </template>
                  </td>
                  <td
                    v-if="c.can?.update"
                    class="px-3 py-2 text-right"
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
          class="card p-4"
        >
          <h4 class="mb-4 font-display text-sm font-semibold text-slate-800">
            Thêm đánh giá cho hợp đồng
          </h4>
          <form
            class="space-y-4"
            @submit.prevent="submitReview"
          >
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div>
                <label class="label">Người đánh giá *</label>
                <EmployeeAutocomplete
                  v-model="reviewForm.reviewer_id"
                  :employees="options.employees || []"
                />
                <p
                  v-if="reviewForm.errors.reviewer_id"
                  class="mt-1 text-xs text-rose-600"
                >
                  {{ reviewForm.errors.reviewer_id }}
                </p>
              </div>
              <div>
                <label class="label">Ngày đánh giá</label>
                <input
                  v-model="reviewForm.reviewed_at"
                  type="date"
                  class="input h-10 w-full text-sm"
                >
              </div>
            </div>

            <div>
              <p class="mb-2 text-xs font-medium text-slate-600">
                Điểm đánh giá (0 – 10)
              </p>
              <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                <div
                  v-for="cr in reviewCriteria"
                  :key="cr.key"
                >
                  <label class="label text-xs">{{ cr.label }}</label>
                  <input
                    v-model="reviewForm[cr.key]"
                    type="number"
                    min="0"
                    max="10"
                    step="0.5"
                    class="input h-9 w-full text-sm"
                    placeholder="0–10"
                  >
                </div>
              </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div>
                <label class="label">Đề xuất</label>
                <select
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
              <div>
                <label class="label">Ghi chú</label>
                <textarea
                  v-model="reviewForm.note"
                  rows="2"
                  class="input w-full text-sm"
                />
              </div>
            </div>

            <div class="flex justify-end gap-2">
              <button
                type="button"
                class="btn-ghost"
                @click="cancelReview"
              >
                Huỷ
              </button>
              <button
                type="submit"
                class="btn-primary"
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
  </AppLayout>
</template>
