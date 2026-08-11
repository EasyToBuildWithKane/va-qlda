<script setup>
import { computed, inject, ref, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import VendorFieldLabel from '@/modules/contract/components/VendorFieldLabel.vue';
import SearchMultiSelect from '@/shared/ui/SearchMultiSelect.vue';
import { normalizeSearchKey } from '@/shared/utils/normalizeSearchKey';

const props = defineProps({
    vendor: { type: Object, default: null },
    categories: { type: Array, default: () => [] },
    cooperationStatuses: { type: Array, default: () => [] },
    inputClass: { type: String, default: 'input h-9 w-full text-sm' },
});

const form = inject('vendorForm');

const DEFAULT_COOPERATION = [
    { value: 'active', label: 'Đang hợp tác', color: 'emerald', hint: 'Đang có quan hệ hợp tác / cung cấp dịch vụ.' },
    { value: 'potential', label: 'Tiềm năng', color: 'sky', hint: 'NCC tiềm năng — chưa ký hoặc đang xem xét.' },
    { value: 'research', label: 'Nghiên cứu', color: 'violet', hint: 'Đang nghiên cứu, khảo sát thị trường.' },
    { value: 'inactive', label: 'Ngừng hợp tác', color: 'slate', hint: 'Ngừng hợp tác — vẫn giữ lịch sử hợp đồng và đánh giá.' },
];

const cooperationOptions = computed(() => (
    props.cooperationStatuses?.length ? props.cooperationStatuses : DEFAULT_COOPERATION
));

function statusToneClass(color) {
    const map = {
        emerald: 'border-emerald-300 bg-emerald-50/80 ring-1 ring-emerald-200/80',
        sky: 'border-sky-300 bg-sky-50/80 ring-1 ring-sky-200/80',
        violet: 'border-violet-300 bg-violet-50/80 ring-1 ring-violet-200/80',
        slate: 'border-slate-300 bg-slate-50 ring-1 ring-slate-200/80',
    };
    return map[color] || map.slate;
}

/** Mục vừa tạo trong phiên (id tạm `new:…`) để hiện chip trong dropdown. */
const sessionOptions = ref([]);

watch(
    () => props.categories,
    () => {
        sessionOptions.value = [];
    },
);

const NEW_PREFIX = 'new:';

function toNewId(name) {
    return `${NEW_PREFIX}${name}`;
}

function parseNewName(value) {
    const s = String(value ?? '');
    return s.startsWith(NEW_PREFIX) ? s.slice(NEW_PREFIX.length) : null;
}

const categoryOptions = computed(() => {
    const base = (props.categories ?? []).map((c) => ({
        id: c.id,
        name: c.name,
    }));
    const known = new Set(base.map((c) => normalizeSearchKey(c.name)));
    const extras = sessionOptions.value.filter((o) => !known.has(normalizeSearchKey(o.name)));
    return [...base, ...extras];
});

const selectedServiceValues = computed({
    get() {
        const ids = (form.category_ids ?? []).map((id) => Number(id)).filter((id) => !Number.isNaN(id));
        const names = (form.category_names ?? []).map((n) => String(n).trim()).filter(Boolean);
        return [...ids, ...names.map(toNewId)];
    },
    set(vals) {
        const ids = [];
        const names = [];
        for (const v of vals ?? []) {
            const newName = parseNewName(v);
            if (newName !== null) {
                if (newName) names.push(newName);
            } else {
                const n = Number(v);
                if (!Number.isNaN(n)) ids.push(n);
            }
        }
        form.category_ids = [...new Set(ids)];
        form.category_names = [...new Set(names)];
    },
});

const selectedCount = computed(() => selectedServiceValues.value.length);

function onCreateCategory(rawName) {
    const name = String(rawName ?? '').trim();
    if (!name || name.length > 255) return;

    const key = normalizeSearchKey(name);
    const existing = (props.categories ?? []).find((c) => normalizeSearchKey(c.name) === key);
    if (existing) {
        const cur = selectedServiceValues.value;
        if (!cur.map(String).includes(String(existing.id))) {
            selectedServiceValues.value = [...cur, existing.id];
        }
        return;
    }

    const tempId = toNewId(name);
    if (!sessionOptions.value.some((o) => normalizeSearchKey(o.name) === key)) {
        sessionOptions.value = [...sessionOptions.value, { id: tempId, name }];
    }

    const cur = selectedServiceValues.value;
    if (!cur.map(String).includes(String(tempId))) {
        selectedServiceValues.value = [...cur, tempId];
    }
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

          <div>
            <VendorFieldLabel
              label="Trạng thái hợp tác"
              compact
              wide
              tooltip="Đang hợp tác · Tiềm năng · Nghiên cứu · Ngừng hợp tác — dùng lọc danh sách và theo dõi pipeline NCC."
            />
            <div
              class="grid grid-cols-1 gap-1.5 sm:grid-cols-2"
              role="radiogroup"
              aria-label="Trạng thái hợp tác"
            >
              <label
                v-for="opt in cooperationOptions"
                :key="opt.value"
                class="flex cursor-pointer items-start gap-2 rounded-lg border px-2.5 py-2 text-sm transition"
                :class="form.cooperation_status === opt.value
                  ? statusToneClass(opt.color)
                  : 'border-slate-200/90 bg-white text-slate-600 hover:border-slate-300'"
              >
                <input
                  v-model="form.cooperation_status"
                  type="radio"
                  class="mt-0.5 border-slate-300 text-brand focus:ring-brand/30"
                  :value="opt.value"
                >
                <span class="min-w-0">
                  <span class="block font-medium leading-snug text-slate-800">{{ opt.label }}</span>
                  <span class="mt-0.5 block text-[11px] leading-snug text-slate-400">{{ opt.hint }}</span>
                </span>
              </label>
            </div>
            <p
              v-if="form.errors.cooperation_status"
              class="mt-1 text-xs text-rose-600"
            >
              {{ form.errors.cooperation_status }}
            </p>
          </div>
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

    <!-- Loại dịch vụ · multi-select dropdown -->
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
            Chọn nhiều từ danh mục — gõ tên mới rồi chọn «Thêm» để tạo loại dịch vụ.
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

      <VendorFieldLabel
        for-id="vendor-services"
        label="Nhóm dịch vụ"
        compact
        wide
        tooltip="Chọn một hoặc nhiều nhóm dịch vụ. Gõ tên chưa có trong danh mục rồi bấm Thêm «…» để tạo mới khi lưu NCC."
      />
      <SearchMultiSelect
        id="vendor-services"
        v-model="selectedServiceValues"
        :options="categoryOptions"
        value-key="id"
        label-key="name"
        placeholder="Tìm & chọn loại dịch vụ…"
        search-placeholder="Tìm hoặc gõ tên mới…"
        :max-chips="8"
        control-size="md"
        :panel-z-index="120"
        creatable
        create-label="Thêm «{query}»"
        @create="onCreateCategory"
      />
      <p
        v-if="form.errors.category_ids || form.errors.category_names"
        class="mt-1.5 text-xs text-rose-600"
      >
        {{ form.errors.category_ids || form.errors.category_names }}
      </p>
    </section>
  </div>
</template>
