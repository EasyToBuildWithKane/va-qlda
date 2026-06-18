<script setup>
import { computed, inject } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import MoneyInput from '@/modules/contract/components/MoneyInput.vue';
import VendorFieldLabel from '@/modules/contract/components/VendorFieldLabel.vue';
import { formatVndDisplay } from '@/modules/contract/composables/useContractFormat.js';

defineProps({
    editingId: { type: [Number, null], default: null },
    billingCycleLabel: { type: String, default: 'Chưa chọn' },
});

const emit = defineEmits(['submit', 'cancel']);

const form = inject('contractFinanceForm');

const totalPreview = computed(() => {
    const n = Number(form?.total);
    if (!n || Number.isNaN(n)) return null;
    return formatVndDisplay(n);
});
</script>

<template>
  <div class="card overflow-hidden p-0">
    <div class="border-b border-slate-100 bg-slate-50/80 px-4 py-3 sm:px-5">
      <h4 class="font-display text-sm font-semibold text-slate-800">
        {{ editingId ? 'Sửa dữ liệu tài chính' : 'Thêm dữ liệu tài chính' }}
      </h4>
      <p class="mt-0.5 text-xs text-slate-500">
        Chi phí năm = tổng tiền hợp đồng — nhập trực tiếp; các ô SL/đơn giá/phí chỉ để mô tả thêm.
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
              tooltip="Thời hạn cam kết (tháng) — thông tin tham chiếu, không tự tính tổng."
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
              tooltip="Phí định kỳ hàng tháng — thông tin tham chiếu, không thay cho tổng HĐ."
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
          <div class="min-w-0 sm:col-span-2 lg:col-span-3">
            <VendorFieldLabel
              for-id="finance-total"
              label="Chi phí năm / Tổng tiền hợp đồng"
              compact
              tooltip="Một giá trị duy nhất: vừa là chi phí năm trên tổng quan, vừa là tổng cam kết dòng tài chính này."
            />
            <MoneyInput
              id="finance-total"
              v-model="form.total"
              placeholder="Nhập chi phí năm / tổng HĐ"
            />
            <p
              v-if="totalPreview?.secondary"
              class="mt-1 text-[11px] text-slate-500"
            >
              Hiển thị tổng quan: {{ totalPreview.primary }} · {{ totalPreview.secondary }}
            </p>
            <p
              v-if="form.errors.total"
              class="mt-1 text-xs text-rose-600"
            >
              {{ form.errors.total }}
            </p>
          </div>
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
