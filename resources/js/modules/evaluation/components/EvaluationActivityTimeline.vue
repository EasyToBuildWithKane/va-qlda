<script setup>
import { computed, ref, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import { datetime } from '@/composables/useFormat';
import { EMPTY_LABELS } from '@/shared/utils/emptyDisplay';

const PAGE_SIZE = 5;

const props = defineProps({
    activity: { type: Array, default: () => [] },
});

const ACTIVITY_META = {
    'evaluation.criteria_created': {
        name: 'add',
        tone: 'bg-emerald-500',
        ring: 'ring-emerald-200 bg-emerald-50 text-emerald-700',
        title: 'Tạo mới',
        verb: 'đã tạo tiêu chí này',
    },
    'evaluation.criteria_updated': {
        name: 'edit',
        tone: 'bg-amber-500',
        ring: 'ring-amber-200 bg-amber-50 text-amber-800',
        title: 'Cập nhật',
        verb: 'đã sửa các nội dung sau',
    },
    'evaluation.criteria_deleted': {
        name: 'trash',
        tone: 'bg-rose-500',
        ring: 'ring-rose-200 bg-rose-50 text-rose-700',
        title: 'Xóa',
        verb: 'đã xóa tiêu chí này',
    },
    'evaluation.template_created': {
        name: 'add',
        tone: 'bg-emerald-500',
        ring: 'ring-emerald-200 bg-emerald-50 text-emerald-700',
        title: 'Tạo mới',
        verb: 'đã tạo mẫu đánh giá này',
    },
    'evaluation.template_updated': {
        name: 'edit',
        tone: 'bg-amber-500',
        ring: 'ring-amber-200 bg-amber-50 text-amber-800',
        title: 'Cập nhật',
        verb: 'đã sửa các nội dung sau',
    },
    'evaluation.template_deleted': {
        name: 'trash',
        tone: 'bg-rose-500',
        ring: 'ring-rose-200 bg-rose-50 text-rose-700',
        title: 'Xóa',
        verb: 'đã xóa mẫu đánh giá này',
    },
    'evaluation.template_duplicated': {
        name: 'copy',
        tone: 'bg-sky-500',
        ring: 'ring-sky-200 bg-sky-50 text-sky-700',
        title: 'Nhân bản',
        verb: 'đã nhân bản mẫu đánh giá này',
    },
};

const FILTERS = [
    { key: 'all', label: 'Tất cả' },
    { key: 'created', label: 'Tạo' },
    { key: 'updated', label: 'Sửa' },
    { key: 'deleted', label: 'Xóa' },
];

const filterKey = ref('all');
const page = ref(1);

const items = computed(() => (props.activity ?? []).filter((a) => a && a.id != null));

const filtered = computed(() => {
    if (filterKey.value === 'all') return items.value;
    const suffix = filterKey.value;
    return items.value.filter((a) => String(a.action || '').endsWith(`_${suffix}`)
        || String(a.action || '').includes(`.${suffix}`)
        || (suffix === 'created' && String(a.action || '').includes('duplicated')));
});

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PAGE_SIZE)));

const paged = computed(() => {
    const start = (page.value - 1) * PAGE_SIZE;
    return filtered.value.slice(start, start + PAGE_SIZE);
});

const rangeLabel = computed(() => {
    if (!filtered.value.length) return '';
    const start = (page.value - 1) * PAGE_SIZE + 1;
    const end = Math.min(page.value * PAGE_SIZE, filtered.value.length);
    return `${start}–${end} / ${filtered.value.length}`;
});

watch([filterKey, () => props.activity], () => {
    page.value = 1;
});

watch(totalPages, (n) => {
    if (page.value > n) page.value = n;
});

function metaFor(item) {
    return ACTIVITY_META[item.action] || {
        name: 'clock',
        tone: 'bg-slate-400',
        ring: 'ring-slate-200 bg-slate-50 text-slate-600',
        title: 'Hoạt động',
        verb: item.label || 'đã thao tác',
    };
}

function changeEntries(item) {
    const changes = item.changes || item.meta?.changes;
    if (!Array.isArray(changes) || !changes.length) return [];
    return changes
        .map((c) => {
            if (typeof c === 'string') return { label: c, from: null, to: null };
            if (!c?.label) return null;
            return {
                label: c.label,
                from: c.from ?? null,
                to: c.to ?? null,
            };
        })
        .filter(Boolean);
}

function scoreSummary(item) {
    return item.score_summary || item.meta?.score_summary || '';
}

function setupFacts(item) {
    const meta = item.meta || {};
    const facts = [];
    if (meta.scope === 'general') facts.push({ label: 'Phạm vi', value: 'Tiêu chí chung' });
    else if (meta.scope === 'department') {
        facts.push({
            label: 'Phạm vi',
            value: meta.department_code
                ? `Theo phòng ban (${meta.department_code})`
                : 'Theo phòng ban',
        });
    }
    if (meta.category) facts.push({ label: 'Loại', value: meta.category });
    if (meta.allow_half_score != null) {
        facts.push({ label: 'Chấm 0.5', value: meta.allow_half_score ? 'Có' : 'Không' });
    }
    if (meta.is_active != null) {
        facts.push({
            label: 'Trạng thái',
            value: meta.is_active ? 'Đang hoạt động' : 'Ngưng hoạt động',
        });
    }
    if (meta.score_levels_count != null) {
        facts.push({ label: 'Số mức', value: `${meta.score_levels_count} mức` });
    }
    return facts;
}

function hasDetail(item) {
    return changeEntries(item).length > 0
        || setupFacts(item).length > 0
        || Boolean(scoreSummary(item));
}

function goPrev() {
    if (page.value > 1) page.value -= 1;
}

function goNext() {
    if (page.value < totalPages.value) page.value += 1;
}
</script>

<template>
  <aside
    class="card overflow-hidden xl:sticky xl:top-5"
    aria-label="Lịch sử hoạt động"
  >
    <div class="border-b border-slate-100 bg-gradient-to-b from-slate-50/90 to-white px-4 py-3.5 sm:px-5">
      <div class="flex items-start justify-between gap-2">
        <div class="min-w-0">
          <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-800">
            <span class="grid h-7 w-7 place-items-center rounded-lg bg-brand/10 text-brand">
              <AppIcon
                name="clock"
                :size="14"
              />
            </span>
            Lịch sử hoạt động
          </h2>
          <p class="mt-1 text-[11px] text-slate-400">
            Ai đã làm gì · thay đổi cụ thể
          </p>
        </div>
        <span
          v-if="items.length"
          class="shrink-0 rounded-full bg-white px-2 py-0.5 text-[11px] font-semibold tabular-nums text-slate-500 ring-1 ring-slate-200/80"
        >
          {{ items.length }}
        </span>
      </div>

      <div
        v-if="items.length"
        class="mt-3 flex flex-wrap gap-1"
        role="tablist"
        aria-label="Lọc loại hoạt động"
      >
        <button
          v-for="f in FILTERS"
          :key="f.key"
          type="button"
          role="tab"
          class="rounded-md px-2 py-1 text-[11px] font-semibold transition-colors"
          :class="filterKey === f.key
            ? 'bg-brand text-white shadow-sm'
            : 'bg-slate-100 text-slate-600 hover:bg-slate-200/80'"
          :aria-selected="filterKey === f.key"
          @click="filterKey = f.key"
        >
          {{ f.label }}
        </button>
      </div>
    </div>

    <ul
      v-if="paged.length"
      class="divide-y divide-slate-100"
    >
      <li
        v-for="item in paged"
        :key="item.id"
        class="px-4 py-4 sm:px-5"
      >
        <div class="flex gap-3">
          <div class="relative z-10 shrink-0">
            <Avatar
              :name="item.actor_name || 'Hệ thống'"
              :src="item.actor_avatar || null"
              :size="40"
            />
            <span
              class="absolute -bottom-0.5 -right-0.5 grid place-items-center rounded-full text-white shadow-sm ring-2 ring-white"
              :class="metaFor(item).tone"
              style="width: 18px; height: 18px"
              aria-hidden="true"
            >
              <AppIcon
                :name="metaFor(item).name"
                :size="10"
              />
            </span>
          </div>

          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-1.5">
              <span
                class="inline-flex items-center rounded-md px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide ring-1"
                :class="metaFor(item).ring"
              >
                {{ metaFor(item).title }}
              </span>
              <time
                class="text-[11px] tabular-nums text-slate-400"
                :datetime="item.created_at"
              >
                {{ item.created_at ? datetime(item.created_at) : EMPTY_LABELS.notUpdated }}
              </time>
            </div>

            <p class="mt-1.5 text-sm leading-snug text-slate-800">
              <span class="font-semibold">{{ item.actor_name || 'Hệ thống' }}</span>
              <span class="text-slate-600">
                {{ ' ' }}{{ metaFor(item).verb }}
              </span>
            </p>

            <!-- Diff thay đổi (ưu tiên) -->
            <div
              v-if="changeEntries(item).length"
              class="mt-2.5 space-y-1.5"
            >
              <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">
                Nội dung đã đổi
              </p>
              <div
                v-for="(entry, i) in changeEntries(item)"
                :key="`${item.id}-c-${i}`"
                class="rounded-lg border border-slate-200 bg-slate-50/80 px-2.5 py-2"
              >
                <p class="text-[11px] font-semibold text-slate-700">
                  {{ entry.label }}
                </p>
                <p
                  v-if="entry.from != null || entry.to != null"
                  class="mt-1 flex flex-wrap items-center gap-1.5 text-[11px] leading-snug"
                >
                  <span
                    v-if="entry.from != null"
                    class="rounded bg-white px-1.5 py-0.5 text-slate-500 line-through decoration-slate-300 ring-1 ring-slate-100"
                  >{{ entry.from }}</span>
                  <AppIcon
                    v-if="entry.from != null && entry.to != null"
                    name="chevron-right"
                    :size="12"
                    class="shrink-0 text-slate-300"
                  />
                  <span
                    v-if="entry.to != null"
                    class="rounded bg-emerald-50 px-1.5 py-0.5 font-medium text-emerald-800 ring-1 ring-emerald-100"
                  >{{ entry.to }}</span>
                </p>
              </div>
            </div>

            <!-- Snapshot tạo mới / meta phụ -->
            <div
              v-else-if="item.action === 'evaluation.criteria_created' && setupFacts(item).length"
              class="mt-2.5 space-y-1.5"
            >
              <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">
                Thiết lập ban đầu
              </p>
              <dl class="grid grid-cols-1 gap-1.5">
                <div
                  v-for="(fact, i) in setupFacts(item)"
                  :key="`${item.id}-f-${i}`"
                  class="flex items-baseline justify-between gap-2 rounded-lg bg-slate-50 px-2.5 py-1.5 ring-1 ring-slate-100"
                >
                  <dt class="shrink-0 text-[11px] text-slate-400">
                    {{ fact.label }}
                  </dt>
                  <dd class="min-w-0 truncate text-right text-[11px] font-medium text-slate-700">
                    {{ fact.value }}
                  </dd>
                </div>
              </dl>
            </div>

            <p
              v-else-if="item.action === 'evaluation.criteria_updated'"
              class="mt-2 rounded-lg border border-dashed border-amber-200/80 bg-amber-50/50 px-2.5 py-2 text-[11px] leading-snug text-amber-900/80"
            >
              Không ghi chi tiết trường nào đổi (bản ghi trước khi hệ thống lưu diff).
            </p>

            <p
              v-if="scoreSummary(item)"
              class="mt-2 rounded-lg bg-white px-2.5 py-1.5 font-mono text-[11px] leading-relaxed text-slate-600 ring-1 ring-slate-100"
            >
              <span class="mr-1 font-sans text-[10px] font-semibold uppercase tracking-wide text-slate-400">Thang điểm</span>
              {{ scoreSummary(item) }}
            </p>

            <p
              v-if="!hasDetail(item) && item.action !== 'evaluation.criteria_updated'"
              class="mt-2 text-[11px] text-slate-400"
            >
              Không có chi tiết bổ sung.
            </p>
          </div>
        </div>
      </li>
    </ul>

    <div
      v-else-if="items.length && !filtered.length"
      class="px-5 py-10 text-center"
    >
      <p class="text-sm font-medium text-slate-600">
        Không có hoạt động thuộc bộ lọc này
      </p>
      <button
        type="button"
        class="mt-2 text-xs font-semibold text-brand hover:underline"
        @click="filterKey = 'all'"
      >
        Xem tất cả
      </button>
    </div>

    <div
      v-else
      class="px-5 py-10 text-center"
    >
      <AppIcon
        name="clock"
        :size="28"
        class="mx-auto mb-2 text-slate-300"
      />
      <p class="text-sm font-medium text-slate-600">
        Chưa có lịch sử hoạt động
      </p>
      <p class="mt-1 text-xs text-slate-400">
        Thao tác tạo hoặc sửa tiêu chí sẽ hiện tại đây.
      </p>
    </div>

    <!-- Phân trang nhỏ khi > 5 -->
    <div
      v-if="filtered.length > PAGE_SIZE"
      class="flex items-center justify-between gap-2 border-t border-slate-100 bg-slate-50/60 px-4 py-2.5 sm:px-5"
    >
      <p class="text-[11px] tabular-nums text-slate-500">
        {{ rangeLabel }}
      </p>
      <div class="flex items-center gap-1">
        <button
          type="button"
          class="grid h-7 w-7 place-items-center rounded-md text-slate-500 transition-colors hover:bg-white hover:text-slate-800 disabled:cursor-not-allowed disabled:opacity-40"
          :disabled="page <= 1"
          aria-label="Trang trước"
          @click="goPrev"
        >
          <AppIcon
            name="chevron-left"
            :size="14"
          />
        </button>
        <span class="min-w-[3.5rem] text-center text-[11px] font-semibold tabular-nums text-slate-600">
          {{ page }}/{{ totalPages }}
        </span>
        <button
          type="button"
          class="grid h-7 w-7 place-items-center rounded-md text-slate-500 transition-colors hover:bg-white hover:text-slate-800 disabled:cursor-not-allowed disabled:opacity-40"
          :disabled="page >= totalPages"
          aria-label="Trang sau"
          @click="goNext"
        >
          <AppIcon
            name="chevron-right"
            :size="14"
          />
        </button>
      </div>
    </div>
  </aside>
</template>
