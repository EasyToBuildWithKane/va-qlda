<script setup>
import { computed, inject, ref, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import VendorFieldLabel from '@/modules/contract/components/VendorFieldLabel.vue';
import { normalizeSearchKey } from '@/shared/utils/normalizeSearchKey';

const props = defineProps({
    vendor: { type: Object, default: null },
    categories: { type: Array, default: () => [] },
    cooperationStatuses: { type: Array, default: () => [] },
    inputClass: { type: String, default: 'input h-9 w-full text-sm' },
});

const form = inject('vendorForm');
const newCategoryName = ref('');
const addHint = ref('');

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

/** Tag vừa tạo trong phiên (id tạm `new:…`). */
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
        isNew: false,
    }));
    const known = new Set(base.map((c) => normalizeSearchKey(c.name)));
    const extras = sessionOptions.value
        .filter((o) => !known.has(normalizeSearchKey(o.name)))
        .map((o) => ({ ...o, isNew: true }));
    return [...base, ...extras];
});

const selectedIds = computed(() =>
    (form.category_ids ?? []).map((id) => Number(id)).filter((id) => !Number.isNaN(id)),
);

const selectedNames = computed(() =>
    (form.category_names ?? []).map((n) => String(n).trim()).filter(Boolean),
);

const selectedCount = computed(() => selectedIds.value.length + selectedNames.value.length);

function isSelected(opt) {
    const newName = parseNewName(opt.id);
    if (newName !== null) {
        return selectedNames.value.some((n) => normalizeSearchKey(n) === normalizeSearchKey(newName));
    }
    return selectedIds.value.includes(Number(opt.id));
}

function toggleTag(opt) {
    const newName = parseNewName(opt.id);
    if (newName !== null) {
        const key = normalizeSearchKey(newName);
        if (selectedNames.value.some((n) => normalizeSearchKey(n) === key)) {
            form.category_names = selectedNames.value.filter((n) => normalizeSearchKey(n) !== key);
        } else {
            form.category_names = [...selectedNames.value, newName];
        }
        return;
    }

    const id = Number(opt.id);
    if (Number.isNaN(id)) return;
    if (selectedIds.value.includes(id)) {
        form.category_ids = selectedIds.value.filter((x) => x !== id);
    } else {
        form.category_ids = [...selectedIds.value, id];
    }
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
    const existing = (props.categories ?? []).find((c) => normalizeSearchKey(c.name) === key);
    if (existing) {
        if (!selectedIds.value.includes(Number(existing.id))) {
            form.category_ids = [...selectedIds.value, Number(existing.id)];
        }
        newCategoryName.value = '';
        addHint.value = `«${existing.name}» đã có — đã chọn tag.`;
        return;
    }

    if (selectedNames.value.some((n) => normalizeSearchKey(n) === key)
        || sessionOptions.value.some((o) => normalizeSearchKey(o.name) === key)) {
        const existingSession = sessionOptions.value.find((o) => normalizeSearchKey(o.name) === key);
        const tempId = toNewId(existingSession?.name || name);
        const opt = categoryOptions.value.find((o) => String(o.id) === String(tempId))
            || { id: tempId, name };
        if (!isSelected(opt)) toggleTag(opt);
        newCategoryName.value = '';
        addHint.value = 'Tag này đã được thêm.';
        return;
    }

    if (selectedCount.value >= 50) {
        addHint.value = 'Mỗi nhà cung cấp tối đa 50 loại dịch vụ.';
        return;
    }

    const tempId = toNewId(name);
    sessionOptions.value = [...sessionOptions.value, { id: tempId, name }];
    form.category_names = [...selectedNames.value, name];
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

    <!-- Loại dịch vụ · tag chọn nhiều -->
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
            Bấm tag để chọn / bỏ chọn — có thể thêm loại mới bên dưới.
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
        class="flex flex-wrap gap-2"
        role="group"
        aria-label="Chọn loại dịch vụ"
      >
        <button
          v-for="opt in categoryOptions"
          :key="opt.id"
          type="button"
          :aria-pressed="isSelected(opt)"
          class="inline-flex max-w-full items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand/40"
          :class="isSelected(opt)
            ? (opt.isNew
              ? 'border-dashed border-brand bg-brand/10 text-brand ring-1 ring-brand/20'
              : 'border-brand bg-brand text-white shadow-sm')
            : 'border-slate-200 bg-white text-slate-600 hover:border-brand/40 hover:bg-brand/[0.04] hover:text-brand'"
          @click="toggleTag(opt)"
        >
          <AppIcon
            v-if="isSelected(opt)"
            name="check"
            :size="13"
            class="shrink-0"
          />
          <span class="truncate">{{ opt.name }}</span>
          <span
            v-if="opt.isNew"
            class="shrink-0 rounded-full px-1.5 py-px text-[10px] font-semibold uppercase tracking-wide"
            :class="isSelected(opt) ? 'bg-brand/15 text-brand' : 'bg-slate-100 text-slate-500'"
          >
            Mới
          </span>
        </button>
      </div>

      <p
        v-if="!categoryOptions.length"
        class="rounded-lg border border-dashed border-slate-200 bg-slate-50/80 px-3 py-4 text-center text-xs text-slate-500"
      >
        Chưa có danh mục — hãy thêm loại dịch vụ bên dưới.
      </p>

      <div class="mt-3 flex flex-col gap-1.5 sm:flex-row sm:items-center">
        <input
          id="vendor-new-service"
          v-model="newCategoryName"
          type="text"
          maxlength="255"
          class="input h-9 w-full flex-1 text-sm"
          placeholder="Thêm tag mới… (vd: Đào tạo, Bảo trì)"
          @keydown.enter.prevent="addCustomCategory"
        >
        <button
          type="button"
          class="btn-ghost inline-flex h-9 shrink-0 items-center justify-center gap-1.5 px-3 text-sm"
          @click="addCustomCategory"
        >
          <AppIcon
            name="plus"
            :size="15"
          />
          Thêm tag
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
