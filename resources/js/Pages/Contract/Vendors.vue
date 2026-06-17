<script setup>
import { computed, reactive, ref, watch, onMounted, onBeforeUnmount } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import VendorFormModal from '@/modules/contract/components/VendorFormModal.vue';
import VendorReviewModal from '@/modules/contract/components/VendorReviewModal.vue';
import VendorDataModal from '@/modules/contract/components/VendorDataModal.vue';
import VendorSummaryBar from '@/modules/contract/components/VendorSummaryBar.vue';
import VendorRowActions from '@/modules/contract/components/VendorRowActions.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import DatagridToolbarActionButton from '@/shared/ui/DatagridToolbarActionButton.vue';
import DatagridFilterField from '@/shared/ui/DatagridFilterField.vue';
import FilterVisibilityDropdown from '@/shared/ui/FilterVisibilityDropdown.vue';
import ColumnVisibilityDropdown from '@/shared/ui/ColumnVisibilityDropdown.vue';
import { useVisibleFilterControls } from '@/shared/composables/useVisibleFilterControls';
import { useVisibleColumns } from '@/shared/composables/useVisibleColumns';
import { useFixedDropdownAnchor } from '@/shared/composables/useFixedDropdownAnchor';
import { useDialog } from '@/composables/useDialog';
import { useToast } from '@/shared/composables/useToast';
import { formatMoneyShort } from '@/modules/contract/composables/useContractFormat.js';
import { exportVendorPage } from '@/modules/contract/composables/useVendorExport.js';
import { reconcileVendors } from '@/modules/contract/composables/useVendorData.js';
import { displayOrEmpty, EMPTY_LABELS } from '@/shared/utils/emptyDisplay.js';

const FILTER_CONTROL_CLASS = 'input h-10 w-full text-sm';

const VENDOR_FILTER_CONTROLS = [
    { key: 'scope', label: 'Phạm vi', default: false },
    { key: 'active', label: 'Trạng thái NCC', default: false },
    { key: 'reviewed', label: 'Đánh giá', default: false },
];

const VENDOR_TABLE_COLUMNS = [
    { key: 'tax_code', label: 'Mã số thuế' },
    { key: 'contact', label: 'Liên hệ' },
    { key: 'contracts', label: 'Hợp đồng' },
    { key: 'annual_cost', label: 'Chi phí / năm' },
    { key: 'review_score', label: 'Điểm' },
    { key: 'is_active', label: 'Trạng thái', default: false },
];

const props = defineProps({
    vendors: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({}) },
    can: { type: Object, default: () => ({}) },
});

const dialog = useDialog();
const toast = useToast();
const page = usePage();

const filterPanelDdRef = ref(null);
const colDdRef = ref(null);
const dataMenuRef = ref(null);
const dataMenu = ref(false);
const dataModal = ref(false);
const dataModalInitialTab = ref('import');
const highlightedId = ref(null);

const {
    visibleFilters,
    showFilterPanelDd,
    enabledFilterControlCount,
    hasFilterRow,
    persistVisibleFilters,
    openFilterPanel,
    FILTER_CONTROLS,
} = useVisibleFilterControls(VENDOR_FILTER_CONTROLS, 'va-qlda.vendors.visible-filters.v1');

const {
    visibleCols,
    showColDd,
    persistVisibleColumns,
    openColPanel,
    isColVisible,
    TABLE_COLUMNS,
} = useVisibleColumns(VENDOR_TABLE_COLUMNS, 'va-qlda.vendors.columns.v1');

const filterForm = reactive({
    q: props.filters.q ?? '',
    scope: props.filters.scope ?? '',
    active: props.filters.active ?? '',
    reviewed: props.filters.reviewed ?? '',
});

const vendorList = computed(() => {
    const raw = props.vendors?.data ?? props.vendors;
    if (Array.isArray(raw)) return raw;
    if (raw && typeof raw === 'object') return Object.values(raw);
    return [];
});

const reconcileSummary = computed(() => reconcileVendors(vendorList.value).summary);

const { panelStyle: dataMenuStyle } = useFixedDropdownAnchor(
    () => dataMenuRef.value,
    dataMenu,
    { width: 248, zIndex: 120 },
);

const tableColspan = computed(() =>
    2 + TABLE_COLUMNS.filter((c) => isColVisible(c.key)).length,
);

function vendorRouteParams() {
    return {
        q: filterForm.q || undefined,
        scope: filterForm.scope || undefined,
        active: filterForm.active || undefined,
        reviewed: filterForm.reviewed || undefined,
    };
}

function navigateVendors() {
    router.get(route('contracts.vendors.index'), vendorRouteParams(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

let debounce;
watch(() => filterForm.q, () => {
    clearTimeout(debounce);
    debounce = setTimeout(navigateVendors, 350);
});

watch(
    () => [filterForm.scope, filterForm.active, filterForm.reviewed],
    navigateVendors,
);

watch(
    () => props.filters,
    (f) => {
        filterForm.q = f.q ?? '';
        filterForm.scope = f.scope ?? '';
        filterForm.active = f.active ?? '';
        filterForm.reviewed = f.reviewed ?? '';
    },
    { deep: true },
);

function onQuickFilter({ scope }) {
    filterForm.scope = scope ?? '';
    clearTimeout(debounce);
    navigateVendors();
}

function onToolbarClickOutside(e) {
    if (e.target.closest?.('[data-filter-visibility-panel]')) return;
    if (e.target.closest?.('[data-column-visibility-panel]')) return;
    if (e.target.closest?.('[data-vendor-data-panel]')) return;
    if (filterPanelDdRef.value && !filterPanelDdRef.value.contains(e.target)) {
        showFilterPanelDd.value = false;
    }
    if (colDdRef.value && !colDdRef.value.contains(e.target)) {
        showColDd.value = false;
    }
    if (dataMenuRef.value && !dataMenuRef.value.contains(e.target)) {
        dataMenu.value = false;
    }
}

function toggleDataMenu() {
    dataMenu.value = !dataMenu.value;
    if (dataMenu.value) {
        showFilterPanelDd.value = false;
        showColDd.value = false;
    }
}

function runQuickExport() {
    dataMenu.value = false;
    if (!vendorList.value.length) {
        toast.warning('Không có dữ liệu để xuất trên trang này.');
        return;
    }
    const count = exportVendorPage(vendorList.value, 'xlsx');
    toast.success(`Đã xuất ${count} nhà cung cấp (Excel).`);
}

function openDataModal(tabName = 'import') {
    dataMenu.value = false;
    dataModalInitialTab.value = tabName;
    dataModal.value = true;
}

function onFixIssue(issue) {
    if (!issue.vendorId) return;
    highlightedId.value = issue.vendorId;
    setTimeout(() => {
        const el = document.querySelector(`[data-vendor-id="${issue.vendorId}"]`);
        el?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }, 150);
}

function vendorRowClass(v) {
    if (highlightedId.value === v.id) {
        return 'bg-amber-50 ring-1 ring-inset ring-amber-300';
    }
    return '';
}

onMounted(() => document.addEventListener('mousedown', onToolbarClickOutside));
onBeforeUnmount(() => document.removeEventListener('mousedown', onToolbarClickOutside));

const showForm = ref(false);
const editing = ref(null);

function openCreate() {
    editing.value = null;
    showForm.value = true;
}
function openEdit(v) {
    editing.value = v;
    showForm.value = true;
}

const showReview = ref(false);
const reviewing = ref(null);

function openReview(v) {
    reviewing.value = v;
    showReview.value = true;
}

function onVendorSaved() {
    const created = page.props.flash?.created_vendor;
    router.reload({
        only: ['vendors', 'summary'],
        onSuccess: () => {
            if (created) {
                reviewing.value = { id: created.id, name: created.name };
                showReview.value = true;
            }
        },
    });
}

function onReviewSaved() {
    router.reload({ only: ['vendors', 'summary'] });
}

function scoreTone(score) {
    if (score == null) return 'bg-slate-100 text-slate-500';
    if (score < 7) return 'bg-rose-100 text-rose-700';
    if (score < 8.5) return 'bg-amber-100 text-amber-700';
    return 'bg-emerald-100 text-emerald-700';
}

async function onDelete(v) {
    if (v.contracts_count > 0) {
        toast.error('Không thể xoá: nhà cung cấp đang có hợp đồng.');
        return;
    }
    const ok = await dialog.confirm({
        title: 'Xoá nhà cung cấp?',
        message: `"${v.name}" sẽ bị xoá khỏi danh sách.`,
        confirmText: 'Xoá',
        tone: 'danger',
    });
    if (!ok) return;
    router.delete(route('contracts.vendors.destroy', v.id), { preserveScroll: true });
}
</script>

<template>
  <Head title="Nhà cung cấp" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Nhà cung cấp"
        subtitle="Quản lý NCC dịch vụ, phần mềm, hạ tầng"
        icon="org-teams"
        icon-color="brand"
        :badge="summary.total ?? null"
      >
        <button
          v-if="can.create"
          type="button"
          class="btn-primary inline-flex h-9 items-center gap-1.5 px-3 text-xs"
          @click="openCreate"
        >
          <AppIcon
            name="add"
            :size="15"
          />
          Thêm NCC
        </button>
      </PageHeader>
    </template>

    <VendorSummaryBar
      :summary="summary"
      :active-scope="filterForm.scope"
      @quick-filter="onQuickFilter"
    />

    <div class="card overflow-visible">
      <div class="border-b border-slate-100 px-5 py-4">
        <div class="flex w-full min-w-0 flex-wrap items-center gap-2 lg:flex-nowrap">
          <div class="min-w-0 w-full basis-full lg:min-w-[10rem] lg:flex-1 lg:basis-auto">
            <DatagridToolbarSearch
              v-model="filterForm.q"
              input-id="vendor-search"
              input-name="q"
              hide-label
              stretch
              inline-actions
              input-height="h-10"
              placeholder="Tìm theo tên, mã, mã số thuế, người liên hệ…"
              aria-label="Tìm nhà cung cấp"
            />
          </div>

          <div class="flex shrink-0 items-center gap-2">
            <div
              ref="filterPanelDdRef"
              class="relative shrink-0"
            >
              <DatagridToolbarActionButton
                icon="filter"
                :active="showFilterPanelDd"
                :title="`Hiển thị bộ lọc (${enabledFilterControlCount}/${FILTER_CONTROLS.length})`"
                @click="openFilterPanel(() => { showColDd = false; dataMenu = false; })"
              >
                Lọc
              </DatagridToolbarActionButton>
              <FilterVisibilityDropdown
                v-model="visibleFilters"
                :show="showFilterPanelDd"
                :anchor-ref="filterPanelDdRef"
                :controls="FILTER_CONTROLS"
                input-id-prefix="vendor-filter-vis"
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
                title="Cột hiển thị"
                @click="openColPanel(() => { showFilterPanelDd = false; dataMenu = false; })"
              >
                Cột
              </DatagridToolbarActionButton>
              <ColumnVisibilityDropdown
                v-model="visibleCols"
                :show="showColDd"
                :columns="TABLE_COLUMNS"
                :anchor-ref="colDdRef"
                :fixed-labels="['Nhà cung cấp', 'Thao tác']"
                input-id-prefix="vendor-col-vis"
                @persist="persistVisibleColumns"
              />
            </div>

            <div
              ref="dataMenuRef"
              class="relative shrink-0"
            >
              <DatagridToolbarActionButton
                icon="upload"
                :active="dataMenu"
                title="Nhập · Xuất · Đối soát dữ liệu"
                @click="toggleDataMenu"
              >
                Dữ liệu
                <span
                  v-if="reconcileSummary.errors > 0"
                  class="ml-1 inline-flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white"
                >
                  {{ reconcileSummary.errors }}
                </span>
              </DatagridToolbarActionButton>
            </div>
          </div>
        </div>

        <Teleport to="body">
          <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 scale-95"
            leave-active-class="transition duration-100 ease-in"
            leave-to-class="opacity-0 scale-95"
          >
            <div
              v-if="dataMenu"
              :style="dataMenuStyle"
              class="overflow-hidden rounded-card border border-slate-200 bg-white p-1 shadow-elevation-2"
              data-vendor-data-panel
            >
              <button
                type="button"
                class="flex w-full items-center gap-2.5 rounded-btn px-3 py-2 text-left hover:bg-slate-50"
                @click="runQuickExport"
              >
                <AppIcon
                  name="export"
                  :size="15"
                  class="shrink-0 text-slate-400"
                />
                <div>
                  <span class="block text-sm font-medium text-slate-700">Xuất trang này</span>
                  <span class="block text-[10px] text-slate-400">{{ vendorList.length }} bản ghi · Excel (.xlsx)</span>
                </div>
              </button>
              <hr class="my-1 border-slate-100">
              <button
                type="button"
                class="flex w-full items-center gap-2.5 rounded-btn px-3 py-2 text-left hover:bg-slate-50"
                @click="openDataModal('import')"
              >
                <AppIcon
                  name="upload"
                  :size="15"
                  class="shrink-0 text-slate-400"
                />
                <div>
                  <span class="block text-sm font-medium text-slate-700">Nhập / Xuất / Đối soát…</span>
                  <span class="block text-[10px] text-slate-400">File mẫu, preview, ghi đè</span>
                </div>
              </button>
            </div>
          </Transition>
        </Teleport>

        <Transition name="fade-slide">
          <div
            v-if="hasFilterRow"
            class="grid grid-cols-1 gap-3 border-t border-slate-100 px-0 pt-4 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6"
          >
            <DatagridFilterField v-if="visibleFilters.scope">
              <select
                v-model="filterForm.scope"
                :class="FILTER_CONTROL_CLASS"
                aria-label="Phạm vi"
              >
                <option value="">
                  Phạm vi
                </option>
                <option value="with_contracts">
                  Đang hợp tác (có HĐ)
                </option>
                <option value="low_score">
                  Điểm đánh giá dưới 7
                </option>
              </select>
            </DatagridFilterField>

            <DatagridFilterField v-if="visibleFilters.active">
              <select
                v-model="filterForm.active"
                :class="FILTER_CONTROL_CLASS"
                aria-label="Trạng thái NCC"
              >
                <option value="">
                  Trạng thái NCC
                </option>
                <option value="1">
                  Đang hoạt động
                </option>
                <option value="0">
                  Ngừng hoạt động
                </option>
              </select>
            </DatagridFilterField>

            <DatagridFilterField v-if="visibleFilters.reviewed">
              <select
                v-model="filterForm.reviewed"
                :class="FILTER_CONTROL_CLASS"
                aria-label="Đánh giá"
              >
                <option value="">
                  Đánh giá
                </option>
                <option value="yes">
                  Đã đánh giá
                </option>
                <option value="no">
                  Chưa đánh giá
                </option>
              </select>
            </DatagridFilterField>
          </div>
        </Transition>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
            <tr>
              <th class="min-w-[12rem] px-5 py-3">
                Nhà cung cấp
              </th>
              <th
                v-if="isColVisible('tax_code')"
                class="px-5 py-3"
              >
                Mã số thuế
              </th>
              <th
                v-if="isColVisible('contact')"
                class="min-w-[10rem] px-5 py-3"
              >
                Liên hệ
              </th>
              <th
                v-if="isColVisible('contracts')"
                class="px-5 py-3 text-right"
              >
                Hợp đồng
              </th>
              <th
                v-if="isColVisible('annual_cost')"
                class="px-5 py-3 text-right"
              >
                Chi phí / năm
              </th>
              <th
                v-if="isColVisible('review_score')"
                class="px-5 py-3 text-center"
              >
                Điểm
              </th>
              <th
                v-if="isColVisible('is_active')"
                class="px-5 py-3"
              >
                Trạng thái
              </th>
              <th class="w-14 px-5 py-3 text-right">
                <span class="sr-only">Thao tác</span>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="v in vendorList"
              :key="v.id"
              :data-vendor-id="v.id"
              class="border-t border-slate-100 transition-colors hover:bg-slate-50/80"
              :class="vendorRowClass(v)"
            >
              <td class="px-5 py-3">
                <Link
                  :href="route('contracts.vendors.show', v.id)"
                  class="font-medium text-brand hover:underline"
                >
                  {{ v.name }}
                </Link>
                <p class="font-mono text-xs text-slate-400">
                  {{ v.code }}
                </p>
              </td>
              <td
                v-if="isColVisible('tax_code')"
                class="px-5 py-3 text-slate-600"
              >
                <span
                  class="text-xs"
                  :class="{ 'italic text-slate-400': !v.tax_code }"
                >
                  {{ displayOrEmpty(v.tax_code, EMPTY_LABELS.notUpdated) }}
                </span>
              </td>
              <td
                v-if="isColVisible('contact')"
                class="px-5 py-3"
              >
                <p
                  class="text-slate-700"
                  :class="{ 'text-xs italic text-slate-400': !v.contact_name }"
                >
                  {{ displayOrEmpty(v.contact_name, EMPTY_LABELS.notUpdated) }}
                </p>
                <p
                  v-if="v.email"
                  class="truncate text-xs text-slate-500"
                >
                  {{ v.email }}
                </p>
                <p
                  v-if="v.phone"
                  class="text-xs text-slate-500"
                >
                  {{ v.phone }}
                </p>
                <p
                  v-if="!v.contact_name && !v.email && !v.phone"
                  class="text-xs italic text-slate-400"
                >
                  Chưa có thông tin liên hệ
                </p>
              </td>
              <td
                v-if="isColVisible('contracts')"
                class="px-5 py-3 text-right tabular-nums text-slate-600"
              >
                {{ v.contracts_count ?? 0 }}
              </td>
              <td
                v-if="isColVisible('annual_cost')"
                class="px-5 py-3 text-right font-medium tabular-nums text-slate-700"
              >
                {{ formatMoneyShort(v.total_annual_cost ?? 0) }}
              </td>
              <td
                v-if="isColVisible('review_score')"
                class="px-5 py-3 text-center"
              >
                <span
                  v-if="v.review_score != null"
                  class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold"
                  :class="scoreTone(v.review_score)"
                  :title="v.is_low_score ? 'Điểm đánh giá dưới 7' : 'Điểm đánh giá gần nhất'"
                >
                  {{ v.review_score }}/10
                </span>
                <span
                  v-else
                  class="text-xs italic text-slate-400"
                >
                  Chưa đánh giá
                </span>
              </td>
              <td
                v-if="isColVisible('is_active')"
                class="px-5 py-3"
              >
                <span
                  class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium"
                  :class="v.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'"
                >
                  {{ v.is_active ? 'Đang hoạt động' : 'Ngừng hoạt động' }}
                </span>
              </td>
              <td class="px-5 py-3 text-right">
                <VendorRowActions
                  :vendor="v"
                  :can-evaluate="can.evaluate"
                  @review="openReview"
                  @edit="openEdit"
                  @delete="onDelete"
                />
              </td>
            </tr>
            <tr v-if="!vendorList.length">
              <td
                :colspan="tableColspan"
                class="px-5 py-12 text-center text-sm text-slate-400"
              >
                Chưa có nhà cung cấp nào phù hợp bộ lọc.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <VendorFormModal
      :show="showForm"
      :vendor="editing"
      @close="showForm = false"
      @saved="onVendorSaved"
    />

    <VendorReviewModal
      :show="showReview"
      :vendor="reviewing"
      :criteria="options.criteria || []"
      :recommendation-options="options.recommendation || []"
      @close="showReview = false"
      @saved="onReviewSaved"
    />

    <VendorDataModal
      v-model="dataModal"
      :rows="vendorList"
      :can-manage="can.create"
      :initial-tab="dataModalInitialTab"
      :active-filters="vendorRouteParams()"
      @fix="onFixIssue"
    />
  </AppLayout>
</template>
