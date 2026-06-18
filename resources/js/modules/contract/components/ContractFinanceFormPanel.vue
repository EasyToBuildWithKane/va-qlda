<script setup>
import { computed, inject } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import MoneyInput from '@/modules/contract/components/MoneyInput.vue';
import VendorFieldLabel from '@/modules/contract/components/VendorFieldLabel.vue';
import VndAmount from '@/modules/contract/components/VndAmount.vue';
import { formatVndDisplay } from '@/modules/contract/composables/useContractFormat.js';

defineProps({
    editingId: { type: [Number, null], default: null },
    billingCycleLabel: { type: String, default: 'Chưa chọn' },
});

const emit = defineEmits(['submit', 'cancel']);

const form = inject('contractFinanceForm');

const computedAnnual = computed(() => {
    const mf = Number(form?.maintenance_fee || 0);
    return mf > 0 ? mf * 12 : null;
});

/** Gợi ý tổng dòng = SL×ĐG + phí KT + phí duy trì×tháng */
const suggestedTotal = computed(() => {
    const q = Number(form?.quantity) || 0;
    const up = Number(form?.unit_price) || 0;
    const init = Number(form?.init_fee) || 0;
    const mf = Number(form?.maintenance_fee) || 0;
    const months = Number(form?.term_months) || 0;
    const sum = q * up + init + mf * months;
    return sum > 0 ? Math.round(sum) : null;
});

const annualPreview = computed(() => (
    computedAnnual.value != null ? formatVndDisplay(computedAnnual.value) : null
));

const suggestedPreview = computed(() => (
    suggestedTotal.value != null ? formatVndDisplay(suggestedTotal.value) : null
));

function applySuggestedTotal() {
    if (suggestedTotal.value != null && form) {
        form.total = suggestedTotal.value;
    }
}
</script>

<template>
  <div class="card overflow-hidden p-0">
    <div class="border-b border-slate-100 bg-slate-50/80 px-4 py-3 sm:px-5">
      <h4 class="font-display text-sm font-semibold text-slate-800">
        {{ editingId ? 'Sửa dữ liệu tài chính' : 'Thêm dữ liệu tài chính' }}
      </h4>
      <p class="mt-0.5 text-xs text-slate-500">
        Số tiền tự định dạng VNĐ; dòng chữ bên dưới cập nhật ngay khi bạn nhập.
      </p>
    </div>

    <form
      class="space-y-6 p-4 sm:p-5"
      @submit.prevent="emit('submit')"
    >
      <!-- Kỳ & tham chiếu -->
      <fieldset class="min-w-0 space-y-3">
        <legend class="mb-1 flex w-full items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
          <AppIcon
            name="calendar"
            :size="14"
            class="text-brand/70"
          />
          Kỳ sử dụng
        </legend>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <div class="min-w-0">
            <VendorFieldLabel
              for-id="finance-used-date"
              label="Ngày sử dụng"
              compact
              tooltip="Ngày bắt đầu ghi nhận chi phí cho dòng này (theo hợp đồng hoặc phụ lục)."
            />
            <input
              id="finance-used-date"
              v-model="form.used_date"
              type="date"
              class="input h-10 w-full text-sm"
            >
            <p
              v-if="form.errors.used_date"
              class="mt-1 text-xs text-rose-600"
            >
              {{ form.errors.used_date }}
            </p>
          </div>
          <div class="min-w-0">
            <VendorFieldLabel
              for-id="finance-term-months"
              label="Thời hạn (tháng)"
              compact
              tooltip="Số tháng duy trì tính phí định kỳ; dùng để gợi ý tổng tiền hợp đồng."
            />
            <input
              id="finance-term-months"
              v-model="form.term_months"
              type="number"
              min="0"
              step="1"
              class="input h-10 w-full text-sm tabular-nums"
              placeholder="VD: 12"
            >
            <p
              v-if="form.errors.term_months"
              class="mt-1 text-xs text-rose-600"
            >
              {{ form.errors.term_months }}
            </p>
          </div>
          <div class="min-w-0">
            <VendorFieldLabel
              label="Chu kỳ thanh toán"
              compact
              tooltip="Lấy từ hồ sơ hợp đồng; chỉnh qua nút Sửa ở đầu trang."
            />
            <input
              :value="billingCycleLabel"
              type="text"
              class="input h-10 w-full bg-slate-50 text-sm text-slate-600"
              disabled
              tabindex="-1"
            >
          </div>
          <div class="min-w-0">
            <VendorFieldLabel
              for-id="finance-quantity"
              label="Số lượng"
              compact
              tooltip="Số lượng đơn vị dịch vụ (license, tài khoản, gói…)."
            />
            <input
              id="finance-quantity"
              v-model="form.quantity"
              type="number"
              min="0"
              step="0.01"
              class="input h-10 w-full text-sm tabular-nums"
              placeholder="VD: 100"
            >
          </div>
        </div>
      </fieldset>

      <!-- Đơn giá -->
      <fieldset class="min-w-0 space-y-3">
        <legend class="mb-1 flex w-full items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
          <AppIcon
            name="money"
            :size="14"
            class="text-brand/70"
          />
          Đơn giá
        </legend>
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
          <div class="min-w-0">
            <VendorFieldLabel
              for-id="finance-unit-price"
              label="Đơn giá"
              compact
              tooltip="Giá một đơn vị trước thuế/phí (nếu có)."
            />
            <MoneyInput
              id="finance-unit-price"
              v-model="form.unit_price"
              placeholder="Nhập đơn giá"
            />
            <p
              v-if="form.errors.unit_price"
              class="mt-1 text-xs text-rose-600"
            >
              {{ form.errors.unit_price }}
            </p>
          </div>
        </div>
      </fieldset>

      <!-- Phí -->
      <fieldset class="min-w-0 space-y-3">
        <legend class="mb-1 flex w-full items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
          <AppIcon
            name="budget"
            :size="14"
            class="text-brand/70"
          />
          Phí &amp; tổng
        </legend>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <div class="min-w-0">
            <VendorFieldLabel
              for-id="finance-init-fee"
              label="Phí khởi tạo"
              compact
              tooltip="Chi phí triển khai / setup một lần."
            />
            <MoneyInput
              id="finance-init-fee"
              v-model="form.init_fee"
              placeholder="0"
            />
            <p
              v-if="form.errors.init_fee"
              class="mt-1 text-xs text-rose-600"
            >
              {{ form.errors.init_fee }}
            </p>
          </div>
          <div class="min-w-0">
            <VendorFieldLabel
              for-id="finance-maintenance"
              label="Phí duy trì / tháng"
              compact
              tooltip="Phí định kỳ hàng tháng; chi phí năm = phí tháng × 12."
            />
            <MoneyInput
              id="finance-maintenance"
              v-model="form.maintenance_fee"
              placeholder="0"
            />
            <p
              v-if="form.errors.maintenance_fee"
              class="mt-1 text-xs text-rose-600"
            >
              {{ form.errors.maintenance_fee }}
            </p>
          </div>
          <div class="min-w-0">
            <VendorFieldLabel
              label="Chi phí năm (tự tính)"
              compact
              tooltip="= Phí duy trì tháng × 12 — chỉ đọc."
            />
            <div
              class="flex min-h-[2.5rem] flex-col justify-center rounded-lg border border-dashed border-slate-200 bg-slate-50 px-3 py-2"
            >
              <template v-if="annualPreview">
                <span class="text-sm font-semibold tabular-nums text-slate-800">{{ annualPreview.primary }}</span>
                <span class="text-[11px] text-slate-500">{{ annualPreview.secondary }}</span>
              </template>
              <span
                v-else
                class="text-sm italic text-slate-400"
              >Nhập phí duy trì tháng</span>
            </div>
          </div>
          <div class="min-w-0 sm:col-span-2 lg:col-span-3">
            <VendorFieldLabel
              for-id="finance-total"
              label="Tổng tiền hợp đồng"
              compact
              tooltip="Tổng cam kết cho dòng tài chính này; có thể áp dụng gợi ý từ công thức bên dưới."
            />
            <MoneyInput
              id="finance-total"
              v-model="form.total"
              placeholder="Nhập tổng hoặc dùng gợi ý"
            />
            <p
              v-if="form.errors.total"
              class="mt-1 text-xs text-rose-600"
            >
              {{ form.errors.total }}
            </p>
          </div>
        </div>

        <div
          v-if="suggestedPreview"
          class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-brand/15 bg-brand/[0.04] px-3 py-2.5"
        >
          <div class="min-w-0 text-sm text-slate-700">
            <span class="font-medium text-slate-800">Gợi ý tổng:</span>
            <VndAmount
              :amount="suggestedTotal"
              inline
              class="ml-1"
            />
            <span class="mt-0.5 block text-[11px] text-slate-500">
              SL × đơn giá + phí KT + phí duy trì × tháng
            </span>
          </div>
          <button
            type="button"
            class="btn-ghost h-8 shrink-0 px-2.5 text-xs"
            @click="applySuggestedTotal"
          >
            Áp dụng gợi ý
          </button>
        </div>
      </fieldset>

      <div class="min-w-0">
        <VendorFieldLabel
          for-id="finance-note"
          label="Ghi chú"
          compact
          tooltip="Diễn giải thêm (hạng mục, đợt thanh toán…)."
        />
        <textarea
          id="finance-note"
          v-model="form.note"
          rows="2"
          class="input w-full text-sm"
          placeholder="VD: Gói năm học 2024–2025, thanh toán một lần"
        />
      </div>

      <div class="flex flex-wrap justify-end gap-2 border-t border-slate-100 pt-4">
        <button
          type="button"
          class="btn-ghost h-9"
          @click="emit('cancel')"
        >
          Huỷ
        </button>
        <button
          type="submit"
          class="btn-primary h-9"
          :disabled="form.processing"
        >
          {{ form.processing ? 'Đang lưu…' : (editingId ? 'Cập nhật' : 'Lưu dữ liệu') }}
        </button>
      </div>
    </form>
  </div>
</template>
