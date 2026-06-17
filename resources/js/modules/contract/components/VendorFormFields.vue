<script setup>
import { inject } from 'vue';
import VendorFieldLabel from '@/modules/contract/components/VendorFieldLabel.vue';
import FieldTooltip from '@/shared/ui/FieldTooltip.vue';

defineProps({
    vendor: { type: Object, default: null },
    inputClass: { type: String, default: 'input h-10 w-full text-sm' },
});

const form = inject('vendorForm');
</script>

<template>
  <div class="grid gap-5 lg:grid-cols-2">
    <section class="space-y-4">
      <h3 class="border-b border-slate-100 pb-2 text-xs font-semibold uppercase tracking-[0.12em] text-brand/80">
        1 · Thông tin chung
      </h3>

      <div v-if="vendor?.code">
        <VendorFieldLabel
          label="Mã NCC"
          tooltip="Mã hệ thống tự sinh — dùng tra cứu và xuất báo cáo."
        />
        <p class="rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 font-mono text-sm text-slate-700">
          {{ vendor.code }}
        </p>
      </div>

      <div>
        <VendorFieldLabel
          for-id="vendor-name"
          label="Tên nhà cung cấp"
          required
          wide
          tooltip="Tên pháp lý hoặc thương hiệu — bắt buộc. Hiển thị trên hợp đồng, explorer và báo cáo chi phí."
        />
        <input
          id="vendor-name"
          v-model="form.name"
          :class="inputClass"
          placeholder="VD: Công ty TNHH Công nghệ ABC"
        >
        <p
          v-if="form.errors.name"
          class="mt-1 text-xs text-rose-600"
        >
          {{ form.errors.name }}
        </p>
      </div>

      <div>
        <VendorFieldLabel
          for-id="vendor-tax"
          label="Mã số thuế"
          tooltip="MST trên hoá đơn và hợp đồng — dùng đối chiếu thanh toán."
        />
        <input
          id="vendor-tax"
          v-model="form.tax_code"
          :class="inputClass"
          placeholder="VD: 0312345678"
        >
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-slate-600">
          <input
            v-model="form.is_active"
            type="checkbox"
            class="rounded border-slate-300 text-brand focus:ring-brand/30"
          >
          Đang hợp tác
        </label>
        <FieldTooltip text="Bỏ chọn nếu ngừng hợp tác — NCC vẫn giữ lịch sử hợp đồng và đánh giá." />
      </div>
    </section>

    <section class="space-y-4">
      <h3 class="border-b border-slate-100 pb-2 text-xs font-semibold uppercase tracking-[0.12em] text-brand/80">
        2 · Liên hệ & ghi chú
      </h3>

      <div>
        <VendorFieldLabel
          for-id="vendor-contact"
          label="Người liên hệ"
          tooltip="Đầu mối chính phía nhà cung cấp (sales, CS, kỹ thuật…)."
        />
        <input
          id="vendor-contact"
          v-model="form.contact_name"
          :class="inputClass"
          placeholder="VD: Nguyễn Văn A — Sales"
        >
      </div>

      <div>
        <VendorFieldLabel
          for-id="vendor-email"
          label="Email"
          tooltip="Email gửi báo giá, hỗ trợ hoặc đối soát hoá đơn."
        />
        <input
          id="vendor-email"
          v-model="form.email"
          type="email"
          :class="inputClass"
          placeholder="sales@nhacungcap.vn"
        >
        <p
          v-if="form.errors.email"
          class="mt-1 text-xs text-rose-600"
        >
          {{ form.errors.email }}
        </p>
      </div>

      <div>
        <VendorFieldLabel
          for-id="vendor-phone"
          label="Điện thoại"
          tooltip="Số hotline hoặc di động liên hệ trực tiếp."
        />
        <input
          id="vendor-phone"
          v-model="form.phone"
          :class="inputClass"
          placeholder="VD: 028 1234 5678"
        >
      </div>

      <div>
        <VendorFieldLabel
          for-id="vendor-website"
          label="Website"
          tooltip="Trang web chính thức — dùng tham chiếu nhanh khi tra cứu dịch vụ."
        />
        <input
          id="vendor-website"
          v-model="form.website"
          :class="inputClass"
          placeholder="https://nhacungcap.vn"
        >
      </div>

      <div>
        <VendorFieldLabel
          for-id="vendor-address"
          label="Địa chỉ"
          wide
          tooltip="Trụ sở hoặc địa chỉ ghi trên hợp đồng / hoá đơn."
        />
        <input
          id="vendor-address"
          v-model="form.address"
          :class="inputClass"
          placeholder="Số nhà, đường, quận/huyện, tỉnh/thành"
        >
      </div>

      <div>
        <VendorFieldLabel
          for-id="vendor-notes"
          label="Ghi chú"
          wide
          tooltip="Thông tin nội bộ: điều khoản đặc biệt, lịch sử hợp tác, rủi ro cần nhớ."
        />
        <textarea
          id="vendor-notes"
          v-model="form.notes"
          rows="3"
          class="input w-full text-sm"
          placeholder="Điều khoản đặc biệt, lịch sử hợp tác, lưu ý…"
        />
      </div>
    </section>
  </div>
</template>
