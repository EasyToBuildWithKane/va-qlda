<script setup>
import { computed, inject, ref } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import VendorFieldLabel from '@/modules/contract/components/VendorFieldLabel.vue';
import FieldTooltip from '@/shared/ui/FieldTooltip.vue';
import { normalizeSearchKey } from '@/shared/utils/normalizeSearchKey';

const props = defineProps({
    vendor: { type: Object, default: null },
    categories: { type: Array, default: () => [] },
    inputClass: { type: String, default: 'input h-9 w-full text-sm' },
});

const form = inject('vendorForm');
const newCategoryName = ref('');
const addHint = ref('');

const selectedCategoryIds = computed(() =>
    (form.category_ids ?? []).map((id) => Number(id)).filter((id) => !Number.isNaN(id)),
);

const customCategoryNames = computed(() =>
    (form.category_names ?? []).map((n) => String(n).trim()).filter(Boolean),
);

const selectedCount = computed(() => selectedCategoryIds.value.length + customCategoryNames.value.length);

function isCategorySelected(id) {
    return selectedCategoryIds.value.includes(Number(id));
}

function toggleCategory(id) {
    const n = Number(id);
    if (Number.isNaN(n)) return;
    const current = selectedCategoryIds.value;
    form.category_ids = current.includes(n)
        ? current.filter((x) => x !== n)
        : [...current, n];
}

function removeCustomCategory(name) {
    const key = normalizeSearchKey(name);
    form.category_names = customCategoryNames.value.filter((n) => normalizeSearchKey(n) !== key);
}

function addCustomCategory() {
    addHint.value = '';
    const name = newCategoryName.value.trim();
    if (!name) {
        addHint.value = 'Nhập tên loại dịch vụ cần thêm.';
        return;
    }
    if (name.length > 255) {
        addHint.value = 'Tên loại dịch vụ tối đa 255 ký tự.';
        return;
    }

    const key = normalizeSearchKey(name);
    const existing = props.categories.find((c) => normalizeSearchKey(c.name) === key);
    if (existing) {
        if (!isCategorySelected(existing.id)) {
            toggleCategory(existing.id);
        }
        newCategoryName.value = '';
        addHint.value = `«${existing.name}» đã có trong danh mục — đã chọn.`;
        return;
    }

    if (customCategoryNames.value.some((n) => normalizeSearchKey(n) === key)) {
        newCategoryName.value = '';
        addHint.value = 'Loại dịch vụ này đã được thêm.';
        return;
    }

    if (selectedCount.value >= 50) {
        addHint.value = 'Mỗi nhà cung cấp tối đa 50 loại dịch vụ.';
        return;
    }

    form.category_names = [...customCategoryNames.value, name];
    newCategoryName.value = '';
}
</script>

<template>
  <div class="space-y-4">
    <div class="grid gap-3 lg:grid-cols-3 lg:items-start">
      <!-- Cột 1 · Thông tin chung -->
      <section class="rounded-xl border border-slate-200/80 bg-gradient-to-b from-slate-50/80 to-white p-3 sm:p-3.5">
        <h3 class="mb-3 flex items-center gap-2 border-b border-slate-100 pb-2 text-[11px] font-semibold uppercase tracking-[0.12em] text-brand/80">
          <span class="grid h-5 w-5 place-items-center rounded-md bg-brand/10 text-[10px] font-bold text-brand">1</span>
          Thông tin chung
        </h3>

        <div class="space-y-2.5">
          <div v-if="vendor?.code">
            <VendorFieldLabel
              label="Mã NCC"
              compact
              tooltip="Mã hệ thống tự sinh — dùng tra cứu và xuất báo cáo."
            />
            <p class="rounded-lg border border-slate-100 bg-white px-2.5 py-1.5 font-mono text-sm text-slate-700">
              {{ vendor.code }}
            </p>
          </div>

          <div>
            <VendorFieldLabel
              for-id="vendor-name"
              label="Tên nhà cung cấp"
              required
              compact
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
              compact
              tooltip="MST trên hoá đơn và hợp đồng — dùng đối chiếu thanh toán."
            />
            <input
              id="vendor-tax"
              v-model="form.tax_code"
              :class="inputClass"
              placeholder="VD: 0312345678"
            >
          </div>

          <label class="mt-1 flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200/80 bg-white px-2.5 py-2 text-sm text-slate-600 transition has-[:checked]:border-brand/40 has-[:checked]:bg-brand/5">
            <input
              v-model="form.is_active"
              type="checkbox"
              class="rounded border-slate-300 text-brand focus:ring-brand/30"
            >
            <span class="min-w-0 flex-1 font-medium text-slate-700">Đang hợp tác</span>
            <FieldTooltip text="Bỏ chọn nếu ngừng hợp tác — NCC vẫn giữ lịch sử hợp đồng và đánh giá." />
          </label>
        </div>
      </section>

      <!-- Cột 2 · Liên hệ -->
      <section class="rounded-xl border border-slate-200/80 bg-gradient-to-b from-slate-50/80 to-white p-3 sm:p-3.5">
        <h3 class="mb-3 flex items-center gap-2 border-b border-slate-100 pb-2 text-[11px] font-semibold uppercase tracking-[0.12em] text-brand/80">
          <span class="grid h-5 w-5 place-items-center rounded-md bg-brand/10 text-[10px] font-bold text-brand">2</span>
          Liên hệ
        </h3>

        <div class="space-y-2.5">
          <div>
            <VendorFieldLabel
              for-id="vendor-contact"
              label="Người liên hệ"
              compact
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
              compact
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
              compact
              tooltip="Số hotline hoặc di động liên hệ trực tiếp."
            />
            <input
              id="vendor-phone"
              v-model="form.phone"
              :class="inputClass"
              placeholder="VD: 028 1234 5678"
            >
          </div>
        </div>
      </section>

      <!-- Cột 3 · Địa chỉ & ghi chú -->
      <section class="rounded-xl border border-slate-200/80 bg-gradient-to-b from-slate-50/80 to-white p-3 sm:p-3.5">
        <h3 class="mb-3 flex items-center gap-2 border-b border-slate-100 pb-2 text-[11px] font-semibold uppercase tracking-[0.12em] text-brand/80">
          <span class="grid h-5 w-5 place-items-center rounded-md bg-brand/10 text-[10px] font-bold text-brand">3</span>
          Địa chỉ & ghi chú
        </h3>

        <div class="space-y-2.5">
          <div>
            <VendorFieldLabel
              for-id="vendor-website"
              label="Website"
              compact
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
              compact
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
              compact
              wide
              tooltip="Thông tin nội bộ: điều khoản đặc biệt, lịch sử hợp tác, rủi ro cần nhớ."
            />
            <textarea
              id="vendor-notes"
              v-model="form.notes"
              rows="3"
              class="input w-full resize-none text-sm"
              placeholder="Điều khoản đặc biệt, lịch sử hợp tác…"
            />
          </div>
        </div>
      </section>
    </div>

    <!-- Loại dịch vụ · lưới 3 cột + thêm mới -->
    <section
      class="rounded-xl border border-slate-200/80 bg-gradient-to-b from-brand/[0.03] to-white p-3 sm:p-3.5"
      aria-labelledby="vendor-services-heading"
    >
      <div class="mb-2.5 flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2">
        <div class="min-w-0">
          <h3
            id="vendor-services-heading"
            class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.12em] text-brand/80"
          >
            <AppIcon
              name="vendor"
              :size="14"
              class="text-brand"
            />
            Loại dịch vụ
          </h3>
          <p class="mt-0.5 text-[11px] text-slate-400">
            Chọn sẵn hoặc tự thêm nhóm mới — dùng lọc danh sách và phân loại hồ sơ.
          </p>
        </div>
        <span
          class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium tabular-nums"
          :class="selectedCount
            ? 'bg-brand/10 text-brand'
            : 'bg-slate-100 text-slate-500'"
        >
          {{ selectedCount ? `Đã chọn ${selectedCount}` : 'Chưa chọn' }}
        </span>
      </div>

      <div
        class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3"
        role="group"
        aria-label="Chọn loại dịch vụ"
      >
        <button
          v-for="cat in categories"
          :key="cat.id"
          type="button"
          :aria-pressed="isCategorySelected(cat.id)"
          class="group flex min-h-[2.75rem] items-center gap-2.5 rounded-lg border px-3 py-2 text-left text-sm transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand/40"
          :class="isCategorySelected(cat.id)
            ? 'border-brand bg-brand/10 text-brand shadow-sm ring-1 ring-brand/20'
            : 'border-slate-200/90 bg-white text-slate-700 hover:border-brand/35 hover:bg-brand/[0.04]'"
          @click="toggleCategory(cat.id)"
        >
          <span
            class="grid h-5 w-5 shrink-0 place-items-center rounded-md border transition"
            :class="isCategorySelected(cat.id)
              ? 'border-brand bg-brand text-white'
              : 'border-slate-300 bg-slate-50 text-transparent group-hover:border-brand/40'"
            aria-hidden="true"
          >
            <AppIcon
              name="check"
              :size="12"
            />
          </span>
          <span class="min-w-0 flex-1 truncate font-medium leading-snug">
            {{ cat.name }}
          </span>
        </button>

        <div
          v-for="name in customCategoryNames"
          :key="`new:${name}`"
          class="flex min-h-[2.75rem] items-center gap-2 rounded-lg border border-dashed border-brand/40 bg-brand/5 px-3 py-2 text-sm text-brand ring-1 ring-brand/15"
        >
          <span
            class="grid h-5 w-5 shrink-0 place-items-center rounded-md border border-brand bg-brand text-white"
            aria-hidden="true"
          >
            <AppIcon
              name="check"
              :size="12"
            />
          </span>
          <span class="min-w-0 flex-1 truncate font-medium leading-snug">
            {{ name }}
          </span>
          <span class="shrink-0 rounded-full bg-brand/15 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide">
            Mới
          </span>
          <button
            type="button"
            class="grid h-6 w-6 shrink-0 place-items-center rounded-md text-brand/70 hover:bg-brand/10 hover:text-brand"
            :aria-label="`Gỡ ${name}`"
            @click="removeCustomCategory(name)"
          >
            <AppIcon
              name="close"
              :size="14"
            />
          </button>
        </div>
      </div>

      <p
        v-if="!categories.length && !customCategoryNames.length"
        class="rounded-lg border border-dashed border-slate-200 bg-slate-50/80 px-3 py-4 text-center text-xs text-slate-500"
      >
        Chưa có danh mục — hãy thêm loại dịch vụ bên dưới.
      </p>

      <div class="mt-3 flex flex-col gap-1.5 sm:flex-row sm:items-center">
        <div class="relative min-w-0 flex-1">
          <input
            id="vendor-new-service"
            v-model="newCategoryName"
            type="text"
            maxlength="255"
            class="input h-9 w-full pr-3 text-sm"
            placeholder="Thêm loại dịch vụ mới… (vd: Đào tạo, Bảo trì)"
            @keydown.enter.prevent="addCustomCategory"
          >
        </div>
        <button
          type="button"
          class="btn-ghost inline-flex h-9 shrink-0 items-center justify-center gap-1.5 px-3 text-sm"
          @click="addCustomCategory"
        >
          <AppIcon
            name="plus"
            :size="15"
          />
          Thêm
        </button>
      </div>
      <p
        v-if="addHint"
        class="text-[11px] text-slate-500"
      >
        {{ addHint }}
      </p>
      <p
        v-if="form.errors.category_ids || form.errors.category_names"
        class="text-xs text-rose-600"
      >
        {{ form.errors.category_ids || form.errors.category_names }}
      </p>
    </section>
  </div>
</template>
