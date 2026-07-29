<script setup>
import {
    computed, onBeforeUnmount, onMounted, reactive, ref, watch,
} from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import DatagridToolbarActionButton from '@/shared/ui/DatagridToolbarActionButton.vue';
import DatagridFilterField from '@/shared/ui/DatagridFilterField.vue';
import FilterVisibilityDropdown from '@/shared/ui/FilterVisibilityDropdown.vue';
import ColumnVisibilityDropdown from '@/shared/ui/ColumnVisibilityDropdown.vue';
import { useDialog } from '@/composables/useDialog';
import { useVisibleFilterControls } from '@/shared/composables/useVisibleFilterControls';
import { useVisibleColumns } from '@/shared/composables/useVisibleColumns';
import { EMPTY_LABELS } from '@/shared/utils/emptyDisplay.js';
import { date } from '@/composables/useFormat';

const FILTER_CONTROL_CLASS = 'input h-10 w-full text-sm';

const REVIEW_FILTER_CONTROLS = [
    { key: 'score_band', label: 'Mức điểm', default: false },
    { key: 'recommendation', label: 'Đề xuất', default: false },
];

const REVIEW_TABLE_COLUMNS = [
    { key: 'contract', label: 'Hợp đồng', default: true },
    { key: 'criteria', label: '6 tiêu chí', default: true },
    { key: 'recommendation', label: 'Đề xuất', default: true },
    { key: 'note', label: 'Ghi chú', default: true },
    { key: 'service_quality', label: 'Chất lượng DV', default: false },
    { key: 'sla', label: 'SLA', default: false },
    { key: 'speed', label: 'Tốc độ', default: false },
    { key: 'price_satisfaction', label: 'Hài lòng giá', default: false },
    { key: 'stability', label: 'Ổn định', default: false },
    { key: 'attitude', label: 'Thái độ', default: false },
];

const props = defineProps({
    vendorId: { type: Number, required: true },
    reviews: { type: Array, default: () => [] },
    criteria: { type: Array, default: () => [] },
    recommendationOptions: { type: Array, default: () => [] },
    canEvaluate: { type: Boolean, default: false },
});

const emit = defineEmits(['evaluate', 'edit', 'deleted']);

const dialog = useDialog();

const filterPanelDdRef = ref(null);
const colDdRef = ref(null);

const {
    visibleFilters,
    showFilterPanelDd,
    enabledFilterControlCount,
    hasFilterRow,
    persistVisibleFilters,
    openFilterPanel,
    FILTER_CONTROLS,
} = useVisibleFilterControls(REVIEW_FILTER_CONTROLS, 'va-workspace.vendor-reviews.visible-filters.v1');

const {
    visibleCols,
    showColDd,
    persistVisibleColumns,
    openColPanel,
    isColVisible,
    TABLE_COLUMNS,
} = useVisibleColumns(REVIEW_TABLE_COLUMNS, 'va-workspace.vendor-reviews.columns.v2');

const searchInput = ref('');
const debouncedQ = ref('');
let searchTimer = null;

watch(searchInput, (val) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        debouncedQ.value = val.trim().toLowerCase();
    }, 350);
});

onBeforeUnmount(() => {
    clearTimeout(searchTimer);
    document.removeEventListener('mousedown', onToolbarClickOutside);
});

const filters = reactive({
    scoreBand: '',
    recommendation: '',
});

function scoreBandMatch(score, band) {
    if (!band) return true;
    if (score == null) return false;
    if (band === 'low') return score < 7;
    if (band === 'mid') return score >= 7 && score < 8.5;
    if (band === 'high') return score >= 8.5;
    return true;
}

const hasContractReviews = computed(() => props.reviews.some((r) => r.contract?.id));

function sortBaselineReviews(list) {
    return [...list].sort((a, b) => {
        const da = a.reviewed_at || '';
        const db = b.reviewed_at || '';
        if (da !== db) return da.localeCompare(db);
        return (a.id ?? 0) - (b.id ?? 0);
    });
}

function sortContractReviews(list) {
    return [...list].sort((a, b) => {
        const da = a.reviewed_at || '';
        const db = b.reviewed_at || '';
        if (da !== db) return db.localeCompare(da);
        return (b.id ?? 0) - (a.id ?? 0);
    });
}

function matchesReviewFilters(r) {
    if (!scoreBandMatch(r.total_score, filters.scoreBand)) return false;
    if (filters.recommendation && r.recommendation?.value !== filters.recommendation) return false;
    const q = debouncedQ.value;
    if (!q) return true;
    const hay = [
        r.reviewer?.name,
        r.note,
        r.recommendation?.label,
        r.reviewed_at,
        r.contract?.name,
        r.contract?.code,
    ].filter(Boolean).join(' ').toLowerCase();
    return hay.includes(q);
}

const baselineReviewsFiltered = computed(() =>
    sortBaselineReviews(props.reviews.filter((r) => r.is_baseline && matchesReviewFilters(r))),
);

const contractReviewsFiltered = computed(() =>
    sortContractReviews(props.reviews.filter((r) => !r.is_baseline && matchesReviewFilters(r))),
);

const filteredReviewCount = computed(
    () => baselineReviewsFiltered.value.length + contractReviewsFiltered.value.length,
);

const reviewSections = computed(() => [
    {
        key: 'baseline',
        eyebrow: 'Hồ sơ NCC',
        title: 'Đánh giá gốc',
        hint: 'Tách biệt với đánh giá gắn từng hợp đồng',
        rows: baselineReviewsFiltered.value,
    },
    {
        key: 'contract',
        eyebrow: 'Theo hợp đồng',
        title: 'Đánh giá hợp đồng',
        hint: 'Ghi nhận từ tab Đánh giá NCC trên chi tiết hợp đồng',
        rows: contractReviewsFiltered.value,
    },
]);

async function onDeleteReview(review) {
    const ok = await dialog.confirm({
        title: 'Xoá đánh giá?',
        message: 'Bản ghi đánh giá sẽ bị xoá khỏi nhật ký. Thao tác không hoàn tác.',
        confirmText: 'Xoá',
        tone: 'danger',
    });
    if (!ok) return;
    router.delete(`/contracts/vendors/${props.vendorId}/reviews/${review.id}`, {
        preserveScroll: true,
        onSuccess: () => emit('deleted'),
    });
}

function scoreTone(score) {
    if (score == null) return 'slate';
    if (score < 7) return 'rose';
    if (score < 8.5) return 'amber';
    return 'emerald';
}

function scoreBadgeColor(score) {
    const t = scoreTone(score);
    return t === 'rose' ? 'rose' : (t === 'amber' ? 'amber' : (t === 'emerald' ? 'emerald' : 'slate'));
}

function criteriaScore(r, key) {
    const v = r[key];
    return v != null ? Number(v) : null;
}

function criteriaBarWidth(score) {
    if (score == null) return 0;
    return Math.min(100, Math.max(0, (score / 10) * 100));
}

const visibleColumnCount = computed(() => {
    let n = 3;
    if (isColVisible('contract') && hasContractReviews.value) n += 1;
    if (isColVisible('criteria')) n += 1;
    n += props.criteria.filter((c) => isColVisible(c.key)).length;
    if (isColVisible('recommendation')) n += 1;
    if (isColVisible('note')) n += 1;
    n += 1;
    return n;
});

function onToolbarClickOutside(e) {
    if (e.target.closest?.('[data-filter-visibility-panel]')) return;
    if (e.target.closest?.('[data-column-visibility-panel]')) return;
    if (filterPanelDdRef.value && !filterPanelDdRef.value.contains(e.target)) {
        showFilterPanelDd.value = false;
    }
    if (colDdRef.value && !colDdRef.value.contains(e.target)) {
        showColDd.value = false;
    }
}

onMounted(() => document.addEventListener('mousedown', onToolbarClickOutside));
</script>

<template>
  <div class="card overflow-visible">
    <div class="border-b border-slate-100 px-5 py-3">
      <div class="flex w-full min-w-0 flex-wrap items-center gap-2 lg:flex-nowrap">
        <div class="min-w-0 w-full basis-full lg:flex-1 lg:basis-auto">
          <DatagridToolbarSearch
            v-model="searchInput"
            input-id="vendor-review-search"
            placeholder="Tìm người đánh giá, đề xuất, ghi chú…"
            hide-label
            stretch
            inline-actions
            input-height="h-10"
          />
        </div>
        <div
          ref="filterPanelDdRef"
          class="relative flex shrink-0 items-center gap-2"
        >
          <DatagridToolbarActionButton
            icon="filter"
            :active="showFilterPanelDd"
            :title="`Hiển thị bộ lọc (${enabledFilterControlCount}/${FILTER_CONTROLS.length})`"
            @click="openFilterPanel(() => { showColDd = false; })"
          >
            Lọc
          </DatagridToolbarActionButton>
          <FilterVisibilityDropdown
            v-model="visibleFilters"
            :show="showFilterPanelDd"
            :anchor-ref="filterPanelDdRef"
            :controls="FILTER_CONTROLS"
            input-id-prefix="vendor-review-filter-vis"
            @persist="persistVisibleFilters"
          />
          <div
            ref="colDdRef"
            class="relative"
          >
            <DatagridToolbarActionButton
              icon="columns"
              :active="showColDd"
              title="Cột hiển thị"
              @click="openColPanel(() => { showFilterPanelDd = false; })"
            >
              Cột
            </DatagridToolbarActionButton>
            <ColumnVisibilityDropdown
              v-model="visibleCols"
              :show="showColDd"
              :columns="TABLE_COLUMNS"
              :anchor-ref="colDdRef"
              input-id-prefix="vendor-review-col-vis"
              @persist="persistVisibleColumns"
            />
          </div>
          <button
            v-if="canEvaluate"
            type="button"
            class="btn-primary inline-flex h-10 shrink-0 items-center gap-1.5 px-3 text-xs"
            @click="emit('evaluate')"
          >
            <AppIcon
              name="performance"
              :size="15"
            />
            Thêm đánh giá
          </button>
        </div>
      </div>

      <div
        v-if="hasFilterRow"
        class="mt-3 grid grid-cols-1 gap-3 border-t border-slate-100 pt-3 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6"
      >
        <DatagridFilterField v-if="visibleFilters.score_band">
          <select
            v-model="filters.scoreBand"
            :class="FILTER_CONTROL_CLASS"
            aria-label="Mức điểm"
          >
            <option value="">
              Mức điểm
            </option>
            <option value="high">
              Tốt (≥ 8,5)
            </option>
            <option value="mid">
              Khá (7 – 8,4)
            </option>
            <option value="low">
              Cần cải thiện (&lt; 7)
            </option>
          </select>
        </DatagridFilterField>
        <DatagridFilterField v-if="visibleFilters.recommendation">
          <select
            v-model="filters.recommendation"
            :class="FILTER_CONTROL_CLASS"
            aria-label="Đề xuất"
          >
            <option value="">
              Đề xuất
            </option>
            <option
              v-for="opt in recommendationOptions"
              :key="opt.value"
              :value="opt.value"
            >
              {{ opt.label }}
            </option>
          </select>
        </DatagridFilterField>
      </div>
    </div>

    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
          <tr>
            <th class="px-5 py-3 font-semibold">
              Ngày đánh giá
            </th>
            <th class="px-5 py-3 font-semibold">
              Người đánh giá
            </th>
            <th
              v-if="isColVisible('contract') && hasContractReviews"
              class="min-w-[10rem] px-5 py-3 font-semibold"
            >
              Hợp đồng
            </th>
            <th class="px-5 py-3 font-semibold text-right">
              Điểm tổng
            </th>
            <th
              v-if="isColVisible('criteria')"
              class="min-w-[14rem] px-5 py-3 font-semibold"
            >
              6 tiêu chí
            </th>
            <th
              v-for="col in criteria.filter((c) => isColVisible(c.key))"
              :key="col.key"
              class="px-5 py-3 text-right font-semibold"
            >
              {{ col.label }}
            </th>
            <th
              v-if="isColVisible('recommendation')"
              class="px-5 py-3 font-semibold"
            >
              Đề xuất
            </th>
            <th
              v-if="isColVisible('note')"
              class="min-w-[12rem] px-5 py-3 font-semibold"
            >
              Ghi chú
            </th>
            <th class="w-24 px-5 py-3 font-semibold text-right">
              Thao tác
            </th>
          </tr>
        </thead>
        <template
          v-for="section in reviewSections"
          :key="section.key"
        >
          <tbody v-if="section.rows.length">
            <tr class="border-t border-slate-200 bg-gradient-to-r from-slate-50 to-white">
              <td
                :colspan="visibleColumnCount"
                class="px-5 py-3"
              >
                <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-brand/80">
                  {{ section.eyebrow }}
                </p>
                <div class="mt-0.5 flex flex-wrap items-baseline justify-between gap-2">
                  <p class="font-display text-sm font-semibold text-slate-800">
                    {{ section.title }}
                  </p>
                  <p
                    v-if="section.hint"
                    class="text-[11px] text-slate-500"
                  >
                    {{ section.hint }}
                  </p>
                </div>
              </td>
            </tr>
            <tr
              v-for="r in section.rows"
              :key="r.id"
              class="border-t border-slate-100 transition hover:bg-slate-50/80"
              :class="r.is_baseline ? 'bg-brand/[0.03]' : ''"
            >
              <td class="whitespace-nowrap px-5 py-3 text-slate-800">
                <span>{{ r.reviewed_at ? date(r.reviewed_at) : EMPTY_LABELS.period }}</span>
                <div
                  v-if="r.is_baseline"
                  class="mt-1"
                >
                  <Badge
                    label="Đánh giá gốc"
                    color="brand"
                  />
                </div>
              </td>
              <td class="px-5 py-3">
                <p class="font-medium text-slate-800">
                  {{ r.reviewer?.name ?? 'Chưa gán người đánh giá' }}
                </p>
              </td>
              <td
                v-if="isColVisible('contract') && hasContractReviews"
                class="px-5 py-3 text-sm"
              >
                <template v-if="r.contract?.id">
                  <Link
                    :href="`/contracts/${r.contract.id}`"
                    class="font-medium text-brand hover:underline"
                  >
                    {{ r.contract.name }}
                  </Link>
                  <p class="font-mono text-[11px] text-slate-400">
                    {{ r.contract.code }}
                    <span
                      v-if="r.contract.is_addendum"
                      class="text-slate-500"
                    > · Phụ lục</span>
                  </p>
                </template>
                <span
                  v-else-if="r.is_baseline"
                  class="text-xs text-slate-500"
                >Không gắn hợp đồng</span>
              </td>
              <td class="px-5 py-3 text-right">
                <Badge
                  v-if="r.total_score != null"
                  :label="`${r.total_score}/10`"
                  :color="scoreBadgeColor(r.total_score)"
                />
                <span
                  v-else
                  class="text-xs italic text-slate-400"
                >Chưa có điểm tổng</span>
              </td>
              <td
                v-if="isColVisible('criteria')"
                class="px-5 py-3"
              >
                <ul
                  class="space-y-1.5"
                  :aria-label="`Tiêu chí lần đánh giá ${r.id}`"
                >
                  <li
                    v-for="c in criteria"
                    :key="c.key"
                    class="flex items-center gap-2 text-[11px]"
                  >
                    <span class="w-24 shrink-0 truncate text-slate-500">{{ c.label }}</span>
                    <div class="h-1.5 min-w-[5rem] flex-1 overflow-hidden rounded-full bg-slate-100">
                      <div
                        class="h-full rounded-full transition-all"
                        :class="{
                          'bg-rose-500': criteriaScore(r, c.key) != null && criteriaScore(r, c.key) < 7,
                          'bg-amber-500': criteriaScore(r, c.key) != null && criteriaScore(r, c.key) >= 7 && criteriaScore(r, c.key) < 8.5,
                          'bg-emerald-500': criteriaScore(r, c.key) != null && criteriaScore(r, c.key) >= 8.5,
                          'bg-slate-200': criteriaScore(r, c.key) == null,
                        }"
                        :style="{ width: `${criteriaBarWidth(criteriaScore(r, c.key))}%` }"
                      />
                    </div>
                    <span class="w-8 shrink-0 tabular-nums text-slate-600">
                      {{ criteriaScore(r, c.key) != null ? criteriaScore(r, c.key) : '·' }}
                    </span>
                  </li>
                </ul>
              </td>
              <td
                v-for="col in criteria.filter((c) => isColVisible(c.key))"
                :key="`${r.id}-${col.key}`"
                class="px-5 py-3 text-right tabular-nums text-slate-700"
              >
                {{ criteriaScore(r, col.key) != null ? criteriaScore(r, col.key) : EMPTY_LABELS.notUpdated }}
              </td>
              <td
                v-if="isColVisible('recommendation')"
                class="px-5 py-3"
              >
                <Badge
                  v-if="r.recommendation?.label"
                  :label="r.recommendation.label"
                  :color="r.recommendation.color ?? 'slate'"
                />
                <span
                  v-else
                  class="text-xs italic text-slate-400"
                >{{ EMPTY_LABELS.notUpdated }}</span>
              </td>
              <td
                v-if="isColVisible('note')"
                class="max-w-xs px-5 py-3 text-slate-600"
              >
                <p class="line-clamp-3 whitespace-pre-wrap text-sm leading-relaxed">
                  {{ r.note || EMPTY_LABELS.notUpdated }}
                </p>
              </td>
              <td class="whitespace-nowrap px-5 py-3 text-right">
                <div
                  v-if="r.can?.update || r.can?.delete"
                  class="inline-flex items-center gap-1"
                >
                  <button
                    v-if="r.can?.update"
                    type="button"
                    class="btn-ghost inline-flex h-8 items-center gap-1 px-2 text-xs"
                    title="Chỉnh sửa"
                    @click="emit('edit', r)"
                  >
                    <AppIcon
                      name="edit"
                      :size="14"
                    />
                  </button>
                  <button
                    v-if="r.can?.delete"
                    type="button"
                    class="btn-ghost inline-flex h-8 items-center gap-1 px-2 text-xs text-rose-600 hover:bg-rose-50"
                    title="Xoá"
                    @click="onDeleteReview(r)"
                  >
                    <AppIcon
                      name="delete"
                      :size="14"
                    />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </template>
        <tbody v-if="!filteredReviewCount">
          <tr>
            <td
              :colspan="visibleColumnCount"
              class="px-5 py-14 text-center"
            >
              <p class="text-sm font-medium text-slate-600">
                {{ reviews.length ? 'Không có bản ghi khớp bộ lọc.' : 'Chưa có lịch sử đánh giá.' }}
              </p>
              <p
                v-if="!reviews.length && canEvaluate"
                class="mt-1 text-xs text-slate-500"
              >
                Bấm «Thêm đánh giá» để ghi nhận lần đánh giá đầu tiên.
              </p>
              <button
                v-if="canEvaluate && !reviews.length"
                type="button"
                class="btn-primary mt-4 inline-flex h-9 items-center gap-1.5 px-3 text-xs"
                @click="emit('evaluate')"
              >
                <AppIcon
                  name="performance"
                  :size="15"
                />
                Đánh giá NCC
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <p
      v-if="filteredReviewCount && filteredReviewCount !== reviews.length"
      class="border-t border-slate-100 px-5 py-2 text-[11px] text-slate-500"
    >
      Hiển thị {{ filteredReviewCount }} / {{ reviews.length }} lần đánh giá
    </p>
  </div>
</template>
