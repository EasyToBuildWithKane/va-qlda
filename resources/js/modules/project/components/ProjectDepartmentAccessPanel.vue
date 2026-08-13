<script setup>
import { computed, ref } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import SearchSelect from '@/shared/ui/SearchSelect.vue';
import { matchesSearchQuery } from '@/shared/utils/normalizeSearchKey';
import { PROJECT_COLOR_SOFT, PROJECT_COLOR_SWATCH } from '@/modules/project/utils/projectColors';

const props = defineProps({
    departmentOptions: { type: Array, default: () => [] },
    ownerId: { type: [Number, String, null], default: null },
    relatedIds: { type: Array, default: () => [] },
    ownerError: { type: String, default: null },
    relatedError: { type: String, default: null },
});

const emit = defineEmits(['update:ownerId', 'update:relatedIds', 'touch-owner', 'touch-related']);

const relatedQuery = ref('');

const ownerIdNum = computed(() => {
    if (props.ownerId === null || props.ownerId === '') return null;
    const n = Number(props.ownerId);
    return Number.isFinite(n) && n > 0 ? n : null;
});

const relatedIdSet = computed(() => new Set((props.relatedIds ?? []).map((id) => Number(id)).filter((id) => id > 0)));

const ownerDept = computed(() =>
    props.departmentOptions.find((d) => Number(d.id) === ownerIdNum.value) || null,
);

const partnerOptions = computed(() =>
    props.departmentOptions.filter((d) => Number(d.id) !== ownerIdNum.value),
);

const visiblePartners = computed(() => {
    const q = relatedQuery.value.trim();
    if (!q) return partnerOptions.value;
    return partnerOptions.value.filter((d) => matchesSearchQuery([d.name, d.code], q));
});

const selectedPartners = computed(() =>
    partnerOptions.value.filter((d) => relatedIdSet.value.has(Number(d.id))),
);

const audienceCount = computed(() => (ownerDept.value ? 1 : 0) + selectedPartners.value.length);

const allVisibleSelected = computed(() => {
    if (!visiblePartners.value.length) return false;
    return visiblePartners.value.every((d) => relatedIdSet.value.has(Number(d.id)));
});

function swatch(color) {
    return PROJECT_COLOR_SWATCH[color] || PROJECT_COLOR_SWATCH.slate;
}

function soft(color) {
    return PROJECT_COLOR_SOFT[color] || PROJECT_COLOR_SOFT.slate;
}

function setOwner(id) {
    emit('update:ownerId', id);
    emit('touch-owner');
    const nextOwner = id != null && id !== '' ? Number(id) : null;
    if (nextOwner && relatedIdSet.value.has(nextOwner)) {
        emit('update:relatedIds', (props.relatedIds ?? []).filter((x) => Number(x) !== nextOwner));
        emit('touch-related');
    }
}

function togglePartner(id) {
    const n = Number(id);
    if (!n || n === ownerIdNum.value) return;
    const next = relatedIdSet.value.has(n)
        ? (props.relatedIds ?? []).filter((x) => Number(x) !== n)
        : [...(props.relatedIds ?? []), n];
    emit('update:relatedIds', next);
    emit('touch-related');
}

function selectAllVisible() {
    const current = new Set(relatedIdSet.value);
    visiblePartners.value.forEach((d) => current.add(Number(d.id)));
    emit('update:relatedIds', [...current]);
    emit('touch-related');
}

function clearRelated() {
    emit('update:relatedIds', []);
    emit('touch-related');
}
</script>

<template>
  <section
    class="overflow-hidden rounded-card border border-slate-200/90 bg-gradient-to-b from-slate-50/90 to-white shadow-[0_1px_2px_rgb(15_23_42_/_0.04)] dark:border-slate-700 dark:from-slate-800/50 dark:to-slate-900"
    aria-label="Phân công phòng ban"
  >
    <header class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 px-4 py-3.5 sm:px-5 dark:border-slate-700">
      <div class="min-w-0">
        <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-brand/80">
          Phân công phòng ban
        </p>
        <h3 class="mt-0.5 font-display text-sm font-semibold text-slate-800 dark:text-slate-100">
          Phụ trách và phòng ban liên đới
        </h3>
        <p class="mt-1 max-w-xl text-xs leading-relaxed text-slate-500 dark:text-slate-400">
          Phòng phụ trách chủ trì dự án. Phòng liên đới là các phòng mà phòng phụ trách kết nối.
          Chỉ thành viên các phòng này (cùng chủ dự án và thành viên được thêm) nhìn thấy dự án trên danh mục.
        </p>
      </div>
      <span
        class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-white px-2.5 py-1 text-[11px] font-semibold tabular-nums text-slate-600 ring-1 ring-slate-200/80 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-600"
      >
        <AppIcon
          name="department"
          :size="13"
          class="text-brand"
        />
        {{ audienceCount }} phòng được xem
      </span>
    </header>

    <div class="grid gap-5 p-4 sm:p-5 lg:grid-cols-5">
      <div class="lg:col-span-2">
        <label class="label flex items-center gap-1.5">
          Phòng ban phụ trách
          <span class="rounded-full bg-brand/10 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-brand">Chủ trì</span>
        </label>
        <SearchSelect
          :model-value="ownerId"
          :options="departmentOptions"
          placeholder="Tìm & chọn phòng chủ trì…"
          search-placeholder="Tìm theo tên hoặc mã…"
          :search-keys="['name', 'code']"
          @update:model-value="setOwner"
        />
        <p
          v-if="ownerError"
          class="mt-1.5 flex items-center gap-1 text-xs text-danger"
        >
          <AppIcon
            name="close"
            :size="12"
          />
          {{ ownerError }}
        </p>
        <div
          v-if="ownerDept"
          class="mt-3 flex items-start gap-3 rounded-xl border border-brand/20 bg-brand-50/60 p-3 dark:border-brand/30 dark:bg-brand/10"
        >
          <span
            class="mt-0.5 h-9 w-1.5 shrink-0 rounded-full"
            :class="swatch(ownerDept.color)"
          />
          <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100">
              {{ ownerDept.name }}
            </p>
            <p class="mt-0.5 font-mono text-[11px] uppercase tracking-wide text-slate-500">
              {{ ownerDept.code }}
            </p>
            <p class="mt-1.5 text-[11px] leading-snug text-slate-500">
              Chịu trách nhiệm điều phối, tiến độ và kết nối các phòng liên đới.
            </p>
          </div>
        </div>
        <p
          v-else
          class="mt-3 rounded-xl border border-dashed border-slate-200 bg-slate-50/80 px-3 py-2.5 text-xs leading-relaxed text-slate-500 dark:border-slate-600 dark:bg-slate-800/40"
        >
          Nếu không chọn, hệ thống gán phòng mặc định (Phòng Công nghệ / PCN hoặc phòng đang hoạt động đầu tiên).
        </p>
      </div>

      <div class="lg:col-span-3">
        <div class="mb-2 flex flex-wrap items-end justify-between gap-2">
          <label class="label mb-0 flex items-center gap-1.5">
            Phòng ban liên đới
            <span class="rounded-full bg-cyan-50 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-cyan-700">Tích nhiều</span>
          </label>
          <div class="flex items-center gap-2 text-[11px]">
            <button
              type="button"
              class="font-medium text-brand hover:underline disabled:text-slate-300 disabled:no-underline"
              :disabled="!visiblePartners.length || allVisibleSelected"
              @click="selectAllVisible"
            >
              Chọn tất cả đang hiện
            </button>
            <span class="text-slate-300">·</span>
            <button
              type="button"
              class="font-medium text-slate-500 hover:text-rose-600 hover:underline disabled:text-slate-300 disabled:no-underline"
              :disabled="!selectedPartners.length"
              @click="clearRelated"
            >
              Bỏ chọn
            </button>
          </div>
        </div>
        <p class="mb-2.5 text-xs leading-relaxed text-slate-500">
          Tích các phòng mà phòng phụ trách phối hợp. Thành viên những phòng này được xem dự án, kể cả khi chưa được thêm vào danh sách thành viên.
        </p>

        <div class="relative mb-3">
          <AppIcon
            name="search"
            :size="15"
            class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"
          />
          <input
            v-model="relatedQuery"
            type="search"
            class="input h-10 w-full pl-9 text-sm"
            placeholder="Tìm phòng ban để tích…"
            aria-label="Tìm phòng ban liên đới"
          >
        </div>

        <div
          v-if="visiblePartners.length"
          class="grid max-h-72 grid-cols-1 gap-2 overflow-y-auto pr-0.5 sm:grid-cols-2"
          role="group"
          aria-label="Danh sách phòng ban liên đới"
        >
          <button
            v-for="d in visiblePartners"
            :key="d.id"
            type="button"
            role="checkbox"
            :aria-checked="relatedIdSet.has(Number(d.id))"
            class="flex items-start gap-2.5 rounded-xl border p-2.5 text-left transition"
            :class="relatedIdSet.has(Number(d.id))
              ? 'border-cyan-300 bg-cyan-50/80 ring-1 ring-cyan-200/80 dark:border-cyan-700 dark:bg-cyan-950/30'
              : 'border-slate-200 bg-white hover:border-cyan-200 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-cyan-800'"
            @click="togglePartner(d.id)"
          >
            <span
              class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-md border transition"
              :class="relatedIdSet.has(Number(d.id))
                ? 'border-cyan-600 bg-cyan-600 text-white'
                : 'border-slate-300 bg-white text-transparent dark:border-slate-600 dark:bg-slate-800'"
            >
              <AppIcon
                name="check"
                :size="12"
              />
            </span>
            <span class="min-w-0 flex-1">
              <span class="flex items-center gap-1.5">
                <span
                  class="h-2 w-2 shrink-0 rounded-full"
                  :class="swatch(d.color)"
                />
                <span class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100">{{ d.name }}</span>
              </span>
              <span class="mt-0.5 block font-mono text-[10px] uppercase tracking-wide text-slate-400">{{ d.code }}</span>
            </span>
          </button>
        </div>
        <p
          v-else-if="partnerOptions.length"
          class="rounded-xl border border-dashed border-slate-200 bg-slate-50/80 px-3 py-6 text-center text-xs text-slate-500 dark:border-slate-600 dark:bg-slate-800/40"
        >
          Không có phòng ban khớp «{{ relatedQuery }}».
        </p>
        <p
          v-else
          class="rounded-xl border border-dashed border-slate-200 bg-slate-50/80 px-3 py-6 text-center text-xs text-slate-500 dark:border-slate-600 dark:bg-slate-800/40"
        >
          Chưa có phòng ban khác để liên đới. Phòng phụ trách vẫn được xem dự án.
        </p>

        <p
          v-if="relatedError"
          class="mt-2 flex items-center gap-1 text-xs text-danger"
        >
          <AppIcon
            name="close"
            :size="12"
          />
          {{ relatedError }}
        </p>
      </div>
    </div>

    <footer class="border-t border-slate-100 bg-slate-50/80 px-4 py-3 sm:px-5 dark:border-slate-700 dark:bg-slate-800/40">
      <div class="flex flex-wrap items-center gap-1.5">
        <span
          v-if="ownerDept"
          class="inline-flex max-w-full items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset ring-black/5"
          :class="soft(ownerDept.color)"
        >
          <span class="text-[10px] font-semibold uppercase tracking-wide opacity-70">Phụ trách</span>
          <span class="truncate">{{ ownerDept.name }}</span>
        </span>
        <span
          v-for="d in selectedPartners"
          :key="'chip-'+d.id"
          class="inline-flex max-w-[12rem] items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset ring-black/5"
          :class="soft(d.color)"
        >
          <span class="truncate">{{ d.name }}</span>
          <button
            type="button"
            class="grid h-3.5 w-3.5 place-items-center rounded-full text-current/60 hover:bg-black/10 hover:text-current"
            :aria-label="`Bỏ ${d.name}`"
            @click="togglePartner(d.id)"
          >
            <AppIcon
              name="close"
              :size="10"
            />
          </button>
        </span>
        <span
          v-if="!ownerDept && !selectedPartners.length"
          class="text-xs text-slate-400"
        >Chưa chọn phòng ban — hệ thống sẽ gán phòng phụ trách mặc định khi lưu.</span>
      </div>
    </footer>
  </section>
</template>
