<script setup>
import {
    computed, reactive, ref, watch, onMounted, onUnmounted,
} from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import DatagridToolbarActionButton from '@/shared/ui/DatagridToolbarActionButton.vue';
import DatagridFilterField from '@/shared/ui/DatagridFilterField.vue';
import FilterVisibilityDropdown from '@/shared/ui/FilterVisibilityDropdown.vue';
import ColumnVisibilityDropdown from '@/shared/ui/ColumnVisibilityDropdown.vue';
import DatagridPaginationFooter from '@/shared/ui/DatagridPaginationFooter.vue';
import DatagridSegmentedControl from '@/shared/ui/DatagridSegmentedControl.vue';
import { useVisibleFilterControls } from '@/shared/composables/useVisibleFilterControls';
import { useVisibleColumns } from '@/shared/composables/useVisibleColumns';
import { exportKbArticlesWorkbook, exportKbArticlesCsv, fetchKbArticlesForExport } from '@/composables/useKbExport';
import { useToast } from '@/shared/composables/useToast';
import { datetime } from '@/composables/useFormat';

const VIEW_KEY = 'va-qlda.knowledge-base.view';
const PER_PAGE_OPTIONS = [10, 15, 20, 30];

const VIEW_TABS = [
    { key: 'cards', label: 'Thẻ', icon: 'grid', title: 'Thẻ theo danh mục' },
    { key: 'list', label: 'Danh sách', icon: 'list', title: 'Danh sách dọc' },
];

const FILTER_CONTROL_CLASS = 'input h-10 w-full text-sm';

const KB_FILTER_CONTROLS = [
    { key: 'category', label: 'Danh mục', default: true },
    { key: 'tag', label: 'Thẻ', default: false },
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

const viewMode = ref(
    typeof localStorage !== 'undefined' && localStorage.getItem(VIEW_KEY) === 'list'
        ? 'list'
        : 'cards',
);

function setViewMode(mode) {
    viewMode.value = mode;
    try {
        localStorage.setItem(VIEW_KEY, mode);
    } catch {
        /* ignore */
    }
}

const categorySortIndex = computed(() => {
    const map = new Map();
    (props.categories.data ?? []).forEach((c, i) => map.set(String(c.id), i));
    return map;
});

const articleGroups = computed(() => {
    const items = props.articles.data ?? [];
    const buckets = new Map();
    for (const article of items) {
        const catId = article.category?.id != null ? String(article.category.id) : '_none';
        const name = article.category?.name ?? 'Chưa phân loại';
        if (!buckets.has(catId)) {
            buckets.set(catId, { key: catId, name, items: [] });
        }
        buckets.get(catId).items.push(article);
    }
    const groups = [...buckets.values()];
    groups.sort((a, b) => {
        const ai = categorySortIndex.value.get(a.key) ?? 9999;
        const bi = categorySortIndex.value.get(b.key) ?? 9999;
        if (ai !== bi) return ai - bi;
        return a.name.localeCompare(b.name, 'vi');
    });
    return groups;
});

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
} = useVisibleFilterControls(KB_FILTER_CONTROLS, 'va-qlda.knowledge-base.visible-filters.v2');

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
    () => [filterForm.category_id, filterForm.tag, filterForm.status],
    () => load(true),
);

function onPerPageChange(n) {
    perPage.value = n;
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
    <template #header>
      <PageHeader
        title="Cơ sở tri thức"
        subtitle="Tài liệu nội bộ, HOWTO và kinh nghiệm thực tế"
        icon="knowledge"
        icon-color="brand"
        :badge="articles.meta?.total ?? null"
      >
        <Link
          v-if="can.create"
          href="/knowledge-base/articles/create"
          class="btn-primary inline-flex h-9 shrink-0 items-center gap-1.5 px-3 text-sm"
        >
          <AppIcon
            name="add"
            :size="15"
          />
          Viết bài
        </Link>
      </PageHeader>
    </template>

    <section
      v-if="favoriteArticles.length"
      class="card mb-4 p-4"
      aria-label="Bài viết yêu thích"
    >
      <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">
        Yêu thích
      </p>
      <ul class="flex flex-wrap gap-2">
        <li
          v-for="fav in favoriteArticles"
          :key="fav.id"
        >
          <Link
            :href="`/knowledge-base/articles/${fav.slug}`"
            class="inline-flex max-w-full rounded-btn border border-slate-200 bg-white px-2.5 py-1 text-sm text-slate-700 transition hover:border-brand/30 hover:text-brand"
          >
            <span class="truncate">{{ fav.title }}</span>
          </Link>
        </li>
      </ul>
    </section>

    <div class="card overflow-visible">
      <div class="border-b border-slate-100 px-5 py-4">
        <div class="flex w-full min-w-0 flex-wrap items-center gap-2 lg:flex-nowrap">
          <div class="min-w-0 w-full basis-full lg:min-w-[10rem] lg:flex-1 lg:basis-auto">
            <DatagridToolbarSearch
              v-model="filterForm.q"
              input-id="kb-search"
              placeholder="Tiêu đề, mô tả, thẻ…"
              stretch
              inline-actions
              hide-label
              input-height="h-10"
            />
          </div>

          <div class="flex shrink-0 items-center gap-2">
            <div
              ref="filterDdRef"
              class="relative shrink-0"
            >
              <DatagridToolbarActionButton
                icon="filter"
                :active="showFilterPanelDd"
                :title="`Hiển thị bộ lọc (${enabledFilterControlCount}/${FILTER_CONTROLS.length})`"
                @click="openFilter"
              >
                Lọc
              </DatagridToolbarActionButton>
              <FilterVisibilityDropdown
                v-model="visibleFilters"
                :show="showFilterPanelDd"
                :anchor-ref="filterDdRef"
                :controls="FILTER_CONTROLS"
                input-id-prefix="kb-filter-vis"
                @persist="persistVisibleFilters"
              />
            </div>

            <div
              ref="colDdRef"
              class="relative shrink-0"
            >
              <DatagridToolbarActionButton
                icon="columns"
                :active="showColDd"
                title="Trường hiển thị trên thẻ bài"
                @click="openCol"
              >
                Cột
              </DatagridToolbarActionButton>
              <ColumnVisibilityDropdown
                v-model="visibleCols"
                :show="showColDd"
                :columns="CARD_COLUMNS"
                :anchor-ref="colDdRef"
                input-id-prefix="kb-col-vis"
                @persist="persistVisibleColumns"
              />
            </div>

            <div
              ref="exportDdRef"
              class="relative shrink-0"
            >
              <DatagridToolbarActionButton
                icon="export"
                :active="showExportDd"
                :disabled="exporting"
                title="Xuất danh sách đang lọc (tối đa 200)"
                @click="toggleExport"
              >
                {{ exporting ? 'Đang xuất…' : 'Xuất' }}
              </DatagridToolbarActionButton>
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

          <div class="ml-auto flex shrink-0 items-center gap-2">
            <DatagridSegmentedControl
              :model-value="viewMode"
              :items="VIEW_TABS"
              aria-label="Chế độ hiển thị bài viết"
              icon-only-below-sm
              @update:model-value="setViewMode"
            />
          </div>
        </div>

        <div
          v-if="hasFilterRow"
          class="grid grid-cols-1 gap-3 border-t border-slate-100 pt-4 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6"
        >
          <DatagridFilterField v-if="visibleFilters.category && categories.data?.length">
            <label
              for="kb-filter-category"
              class="sr-only"
            >Danh mục</label>
            <select
              id="kb-filter-category"
              v-model="filterForm.category_id"
              name="category_id"
              :class="FILTER_CONTROL_CLASS"
            >
              <option value="">
                Danh mục
              </option>
              <option
                v-for="cat in categories.data"
                :key="cat.id"
                :value="String(cat.id)"
              >
                {{ cat.name }}
              </option>
            </select>
          </DatagridFilterField>

          <DatagridFilterField v-if="visibleFilters.tag && tags.length">
            <label
              for="kb-filter-tag"
              class="sr-only"
            >Thẻ</label>
            <select
              id="kb-filter-tag"
              v-model="filterForm.tag"
              name="tag"
              :class="FILTER_CONTROL_CLASS"
            >
              <option value="">
                Thẻ
              </option>
              <option
                v-for="t in tags"
                :key="t.id"
                :value="t.slug"
              >
                {{ t.name }}
              </option>
            </select>
          </DatagridFilterField>

          <DatagridFilterField v-if="visibleFilters.status && options.statuses?.length">
            <label
              for="kb-filter-status"
              class="sr-only"
            >Trạng thái</label>
            <select
              id="kb-filter-status"
              v-model="filterForm.status"
              name="status"
              :class="FILTER_CONTROL_CLASS"
            >
              <option value="">
                Trạng thái
              </option>
              <option
                v-for="s in options.statuses"
                :key="s.value"
                :value="s.value"
              >
                {{ s.label }}
              </option>
            </select>
          </DatagridFilterField>
        </div>
      </div>

      <div
        v-if="!articles.data?.length"
        class="p-8 text-center text-sm text-slate-500"
      >
        Chưa có bài viết phù hợp.
      </div>

      <div
        v-else-if="viewMode === 'cards'"
        class="divide-y divide-slate-100"
      >
        <section
          v-for="group in articleGroups"
          :key="group.key"
          class="px-4 py-5 sm:px-5"
        >
          <div class="mb-4 flex items-center gap-3">
            <h2 class="shrink-0 font-display text-sm font-semibold text-slate-800">
              {{ group.name }}
            </h2>
            <div
              class="h-px min-w-0 flex-1 bg-slate-200"
              aria-hidden="true"
            />
            <span class="shrink-0 text-[11px] tabular-nums text-slate-400">
              {{ group.items.length }} bài
            </span>
          </div>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <Link
              v-for="a in group.items"
              :key="a.id"
              :href="`/knowledge-base/articles/${a.slug}`"
              class="flex h-full flex-col rounded-card border border-slate-200/80 bg-white p-4 shadow-sm transition hover:border-brand/30 hover:shadow-md"
            >
              <div
                v-if="isColVisible('status')"
                class="mb-2 flex flex-wrap items-center gap-2"
              >
                <Badge
                  v-if="a.status"
                  :label="a.status.label"
                  color="slate"
                />
              </div>
              <h3 class="font-display text-base font-semibold leading-snug text-slate-800">
                {{ a.title }}
              </h3>
              <p
                v-if="isColVisible('excerpt') && a.excerpt"
                class="mt-2 line-clamp-3 flex-1 text-sm text-slate-500"
              >
                {{ a.excerpt }}
              </p>
              <p
                v-if="isColVisible('tags') && a.tags?.length"
                class="mt-3 text-xs text-slate-400"
              >
                {{ a.tags.map((t) => t.name).join(' · ') }}
              </p>
              <p class="mt-3 flex flex-wrap gap-x-3 gap-y-1 border-t border-slate-100 pt-3 text-xs text-slate-400">
                <span v-if="isColVisible('views')">{{ a.view_count }} lượt xem</span>
                <span v-if="isColVisible('author') && a.author">{{ a.author.full_name }}</span>
                <span v-if="isColVisible('updated') && a.updated_at">{{ datetime(a.updated_at) }}</span>
              </p>
            </Link>
          </div>
        </section>
      </div>

      <template v-else>
        <Link
          v-for="a in articles.data"
          :key="a.id"
          :href="`/knowledge-base/articles/${a.slug}`"
          class="block border-t border-slate-100 p-4 transition first:border-t-0 hover:bg-slate-50/50"
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
      </template>

      <DatagridPaginationFooter
        v-if="articles.meta"
        variant="bar"
        :meta="articles.meta"
        :per-page="perPage"
        :per-page-options="PER_PAGE_OPTIONS"
        @update:per-page="onPerPageChange"
      />
    </div>
  </AppLayout>
</template>
