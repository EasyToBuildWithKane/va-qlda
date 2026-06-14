<script setup>
import { reactive, ref, watch, onMounted, onUnmounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import FilterVisibilityDropdown from '@/shared/ui/FilterVisibilityDropdown.vue';
import ColumnVisibilityDropdown from '@/shared/ui/ColumnVisibilityDropdown.vue';
import DatagridPaginationFooter from '@/shared/ui/DatagridPaginationFooter.vue';
import { useVisibleFilterControls } from '@/shared/composables/useVisibleFilterControls';
import { useVisibleColumns } from '@/shared/composables/useVisibleColumns';
import { exportKbArticlesWorkbook, exportKbArticlesCsv, fetchKbArticlesForExport } from '@/composables/useKbExport';
import { useToast } from '@/shared/composables/useToast';
import { datetime } from '@/composables/useFormat';

const PER_PAGE_OPTIONS = [10, 15, 20, 30];

const KB_FILTER_CONTROLS = [
    { key: 'tag', label: 'Thẻ' },
    { key: 'status', label: 'Trạng thái', default: false },
];

const props = defineProps({
    articles: { type: Object, required: true },
    categories: { type: Object, required: true },
    tags: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({}) },
    favoriteArticles: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
});

const toast = useToast();

const filterForm = reactive({
    q: props.filters.q ?? '',
    category_id: props.filters.category_id ?? '',
    tag: props.filters.tag ?? '',
    status: props.filters.status ?? '',
});

const perPage = ref(Number(props.filters.per_page) || props.articles.meta?.per_page || 15);

const CARD_COLUMNS = [
    { key: 'status', label: 'Trạng thái', default: true },
    { key: 'category', label: 'Danh mục', default: true },
    { key: 'excerpt', label: 'Mô tả', default: true },
    { key: 'views', label: 'Lượt xem', default: true },
    { key: 'tags', label: 'Thẻ', default: false },
    { key: 'author', label: 'Tác giả', default: false },
    { key: 'updated', label: 'Cập nhật', default: false },
];

const {
    visibleFilters,
    showFilterPanelDd,
    persistVisibleFilters,
    openFilterPanel,
    hasFilterRow,
    enabledFilterControlCount,
    FILTER_CONTROLS,
} = useVisibleFilterControls(KB_FILTER_CONTROLS, 'va-qlda.knowledge-base.visible-filters');

const {
    visibleCols,
    showColDd,
    persistVisibleColumns,
    openColPanel,
    isColVisible,
} = useVisibleColumns(CARD_COLUMNS, 'va-qlda.knowledge-base.columns');

const filterDdRef = ref(null);
const colDdRef = ref(null);
const exportDdRef = ref(null);
const showExportDd = ref(false);
const exporting = ref(false);

function routeParams(resetPage = false) {
    const params = {
        q: filterForm.q || undefined,
        category_id: filterForm.category_id || undefined,
        tag: filterForm.tag || undefined,
        status: filterForm.status || undefined,
        per_page: perPage.value,
    };
    if (resetPage) params.page = 1;
    return Object.fromEntries(Object.entries(params).filter(([, v]) => v !== '' && v != null));
}

function load(resetPage = false) {
    router.get(route('knowledge-base.index'), routeParams(resetPage), {
        preserveState: true,
        replace: true,
        preserveScroll: true,
    });
}

let qTimer = null;
watch(() => filterForm.q, () => {
    clearTimeout(qTimer);
    qTimer = setTimeout(() => load(true), 350);
});

watch(
    () => [filterForm.tag, filterForm.status],
    () => load(true),
);

function onPerPageChange(n) {
    perPage.value = n;
    load(true);
}

function selectCategory(id) {
    filterForm.category_id = filterForm.category_id === String(id) ? '' : String(id);
    load(true);
}

const onDocClick = (e) => {
    if (e.target.closest?.('[data-filter-visibility-panel]')) return;
    if (filterDdRef.value && !filterDdRef.value.contains(e.target)) showFilterPanelDd.value = false;
    if (colDdRef.value && !colDdRef.value.contains(e.target)) showColDd.value = false;
    if (exportDdRef.value && !exportDdRef.value.contains(e.target)) showExportDd.value = false;
};
onMounted(() => document.addEventListener('mousedown', onDocClick));
onUnmounted(() => document.removeEventListener('mousedown', onDocClick));

function openFilter() {
    openFilterPanel(() => { showColDd.value = false; showExportDd.value = false; });
}
function openCol() {
    openColPanel(() => { showFilterPanelDd.value = false; showExportDd.value = false; });
}
function toggleExport() {
    showExportDd.value = !showExportDd.value;
    if (showExportDd.value) {
        showFilterPanelDd.value = false;
        showColDd.value = false;
    }
}

async function runExport(format) {
    showExportDd.value = false;
    if (exporting.value) return;
    exporting.value = true;
    try {
        const params = routeParams(false);
        if (format === 'excel') {
            await exportKbArticlesWorkbook({ params });
            toast.success('Đã xuất Excel.');
        } else {
            const { articles, filters } = await fetchKbArticlesForExport(params);
            exportKbArticlesCsv(articles, filters);
            toast.success('Đã xuất CSV.');
        }
    } catch {
        toast.error('Xuất dữ liệu thất bại. Vui lòng thử lại.');
    } finally {
        exporting.value = false;
    }
}
</script>

<template>
  <Head title="Tri thức" />
  <AppLayout>
    <PageHeader
      title="Cơ sở tri thức"
      subtitle="Tài liệu nội bộ, HOWTO và kinh nghiệm thực tế"
    >
      <Link
        v-if="can.create"
        href="/knowledge-base/articles/create"
        class="btn-primary inline-flex h-9 items-center gap-1.5 px-3 text-sm"
      >
        <AppIcon
          name="plus"
          :size="16"
        />
        Viết bài
      </Link>
    </PageHeader>

    <div class="flex flex-col gap-4 lg:flex-row">
      <aside class="card w-full shrink-0 p-4 lg:w-56">
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">
          Danh mục
        </p>
        <ul class="space-y-1 text-sm">
          <li>
            <button
              type="button"
              class="w-full rounded-btn px-2 py-1.5 text-left hover:bg-slate-50"
              :class="!filterForm.category_id ? 'bg-brand/5 font-medium text-brand' : 'text-slate-600'"
              @click="filterForm.category_id = ''; load(true)"
            >
              Tất cả
            </button>
          </li>
          <li
            v-for="cat in categories.data"
            :key="cat.id"
          >
            <button
              type="button"
              class="w-full rounded-btn px-2 py-1.5 text-left hover:bg-slate-50"
              :class="filterForm.category_id === String(cat.id) ? 'bg-brand/5 font-medium text-brand' : 'text-slate-600'"
              @click="selectCategory(cat.id)"
            >
              {{ cat.name }}
            </button>
          </li>
        </ul>
        <p
          v-if="favoriteArticles.length"
          class="mb-2 mt-4 text-xs font-semibold uppercase tracking-wide text-slate-400"
        >
          Yêu thích
        </p>
        <ul
          v-if="favoriteArticles.length"
          class="space-y-1 text-sm"
        >
          <li
            v-for="fav in favoriteArticles"
            :key="fav.id"
          >
            <Link
              :href="`/knowledge-base/articles/${fav.slug}`"
              class="block rounded-btn px-2 py-1.5 text-slate-600 hover:bg-slate-50"
            >
              {{ fav.title }}
            </Link>
          </li>
        </ul>
      </aside>

      <div class="min-w-0 flex-1 space-y-3">
        <div class="card overflow-visible">
          <div class="flex flex-col gap-2.5 border-b border-slate-100 bg-slate-50/40 px-4 py-3.5 sm:px-5 lg:flex-row lg:items-center">
            <DatagridToolbarSearch
              v-model="filterForm.q"
              input-id="kb-search"
              placeholder="Tiêu đề, mô tả, thẻ…"
            />

            <div class="flex shrink-0 flex-wrap items-center gap-2">
              <div
                ref="filterDdRef"
                class="relative"
              >
                <button
                  type="button"
                  class="inline-flex h-9 shrink-0 items-center gap-1 rounded-btn border px-2.5 text-xs font-medium transition select-none"
                  :class="showFilterPanelDd
                    ? 'border-brand/40 bg-brand/5 text-brand'
                    : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-slate-800'"
                  :title="`Hiển thị bộ lọc (${enabledFilterControlCount}/${FILTER_CONTROLS.length})`"
                  @click="openFilter"
                >
                  <AppIcon
                    name="filter"
                    :size="15"
                  />
                  <span>Lọc</span>
                </button>
                <FilterVisibilityDropdown
                  v-model="visibleFilters"
                  :show="showFilterPanelDd"
                  :anchor-ref="filterDdRef"
                  :controls="FILTER_CONTROLS"
                  @persist="persistVisibleFilters"
                />
              </div>

              <div
                ref="colDdRef"
                class="relative"
              >
                <button
                  type="button"
                  class="inline-flex h-9 shrink-0 items-center gap-1 rounded-btn border px-2.5 text-xs font-medium transition select-none"
                  :class="showColDd
                    ? 'border-brand/40 bg-brand/5 text-brand'
                    : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-slate-800'"
                  title="Trường hiển thị trên thẻ bài"
                  @click="openCol"
                >
                  <AppIcon
                    name="columns"
                    :size="15"
                  />
                  <span>Cột</span>
                </button>
                <ColumnVisibilityDropdown
                  v-model="visibleCols"
                  :show="showColDd"
                  :columns="CARD_COLUMNS"
                  @persist="persistVisibleColumns"
                />
              </div>

              <div
                ref="exportDdRef"
                class="relative"
              >
                <button
                  type="button"
                  class="inline-flex h-9 shrink-0 items-center gap-1 rounded-btn border px-2.5 text-xs font-medium transition select-none"
                  :class="showExportDd
                    ? 'border-brand/40 bg-brand/5 text-brand'
                    : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-slate-800'"
                  :disabled="exporting"
                  title="Xuất danh sách đang lọc (tối đa 200)"
                  @click="toggleExport"
                >
                  <AppIcon
                    name="export"
                    :size="15"
                  />
                  <span>{{ exporting ? 'Đang xuất…' : 'Xuất' }}</span>
                </button>
                <div
                  v-if="showExportDd"
                  class="absolute right-0 z-30 mt-1 min-w-[9rem] rounded-card border border-slate-200 bg-white py-1 shadow-lg"
                >
                  <button
                    type="button"
                    class="block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50"
                    @click="runExport('csv')"
                  >
                    CSV
                  </button>
                  <button
                    type="button"
                    class="block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50"
                    @click="runExport('excel')"
                  >
                    Excel
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div
            v-if="hasFilterRow"
            class="flex flex-wrap items-center gap-2 border-b border-slate-100 bg-slate-50/30 px-4 py-2.5 sm:px-5"
          >
            <select
              v-if="visibleFilters.tag && tags.length"
              v-model="filterForm.tag"
              class="input h-9 w-48 text-sm"
              aria-label="Lọc theo thẻ"
            >
              <option value="">
                Tất cả thẻ
              </option>
              <option
                v-for="t in tags"
                :key="t.id"
                :value="t.slug"
              >
                {{ t.name }}
              </option>
            </select>
            <select
              v-if="visibleFilters.status && options.statuses?.length"
              v-model="filterForm.status"
              class="input h-9 w-48 text-sm"
              aria-label="Trạng thái bài viết"
            >
              <option value="">
                Mọi trạng thái
              </option>
              <option
                v-for="s in options.statuses"
                :key="s.value"
                :value="s.value"
              >
                {{ s.label }}
              </option>
            </select>
          </div>
        </div>

        <div
          v-if="!articles.data?.length"
          class="card p-8 text-center text-sm text-slate-500"
        >
          Chưa có bài viết phù hợp.
        </div>

        <Link
          v-for="a in articles.data"
          :key="a.id"
          :href="`/knowledge-base/articles/${a.slug}`"
          class="card block p-4 transition hover:border-brand/30"
        >
          <div
            v-if="isColVisible('status') || isColVisible('category')"
            class="mb-1 flex flex-wrap items-center gap-2"
          >
            <Badge
              v-if="isColVisible('status') && a.status"
              :label="a.status.label"
              color="slate"
            />
            <span
              v-if="isColVisible('category') && a.category"
              class="text-xs text-slate-400"
            >{{ a.category.name }}</span>
          </div>
          <h2 class="font-display text-base font-semibold text-slate-800">
            {{ a.title }}
          </h2>
          <p
            v-if="isColVisible('excerpt') && a.excerpt"
            class="mt-1 line-clamp-2 text-sm text-slate-500"
          >
            {{ a.excerpt }}
          </p>
          <p
            v-if="isColVisible('tags') && a.tags?.length"
            class="mt-2 text-xs text-slate-400"
          >
            {{ a.tags.map((t) => t.name).join(' · ') }}
          </p>
          <p class="mt-2 flex flex-wrap gap-3 text-xs text-slate-400">
            <span v-if="isColVisible('views')">{{ a.view_count }} lượt xem</span>
            <span v-if="isColVisible('author') && a.author">{{ a.author.full_name }}</span>
            <span v-if="isColVisible('updated') && a.updated_at">{{ datetime(a.updated_at) }}</span>
          </p>
        </Link>

        <DatagridPaginationFooter
          v-if="articles.meta"
          variant="bar"
          :meta="articles.meta"
          :per-page="perPage"
          :per-page-options="PER_PAGE_OPTIONS"
          @update:per-page="onPerPageChange"
        />
      </div>
    </div>
  </AppLayout>
</template>
