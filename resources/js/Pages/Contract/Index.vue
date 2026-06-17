<script setup>
import {
    computed, reactive, ref, watch, onMounted, onBeforeUnmount,
} from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import ContractExplorer from '@/modules/contract/components/ContractExplorer.vue';
import ContractFormModal from '@/modules/contract/components/ContractFormModal.vue';
import ContractDataModal from '@/modules/contract/components/ContractDataModal.vue';
import ContractPortfolioSummaryBar from '@/modules/contract/components/ContractPortfolioSummaryBar.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import DatagridToolbarActionButton from '@/shared/ui/DatagridToolbarActionButton.vue';
import DatagridFilterField from '@/shared/ui/DatagridFilterField.vue';
import FilterVisibilityDropdown from '@/shared/ui/FilterVisibilityDropdown.vue';
import { useVisibleFilterControls } from '@/shared/composables/useVisibleFilterControls';
import { useFixedDropdownAnchor } from '@/shared/composables/useFixedDropdownAnchor';
import { useToast } from '@/shared/composables/useToast';
import { useContractExplorer } from '@/modules/contract/composables/useContractExplorer.js';
import { downloadContractExport } from '@/modules/contract/composables/useContractExport.js';

const props = defineProps({
    contracts: { type: Object, required: true }, // resource collection { data: [...] }
    vendors: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({}) },
    can: { type: Object, default: () => ({}) },
});

const toast = useToast();

const contractList = computed(() => {
    const raw = props.contracts?.data ?? props.contracts;
    if (Array.isArray(raw)) return raw;
    if (raw && typeof raw === 'object') return Object.values(raw);
    return [];
});
const { tree } = useContractExplorer(contractList, () => props.vendors);

const FILTER_CONTROLS = [
    { key: 'status', label: 'Trạng thái', default: false },
    { key: 'payment_status', label: 'Thanh toán', default: false },
    { key: 'vendor', label: 'Nhà cung cấp', default: false },
    { key: 'category', label: 'Nhóm dịch vụ', default: false },
];
const FILTER_CONTROL_CLASS = 'input h-10 w-full text-sm';

const filters = reactive({
    q: props.filters.q ?? '',
    status: props.filters.status ?? '',
    payment_status: props.filters.payment_status ?? '',
    vendor_id: props.filters.vendor_id ?? '',
    category_id: props.filters.category_id ?? '',
});

const {
    visibleFilters,
    showFilterPanelDd,
    enabledFilterControlCount,
    hasFilterRow,
    persistVisibleFilters,
    openFilterPanel,
    FILTER_CONTROLS: FILTER_CONTROL_LIST,
} = useVisibleFilterControls(FILTER_CONTROLS, 'va-qlda.contracts.visible-filters.v1');

const appliedFilterCount = computed(() => [
    filters.status, filters.payment_status, filters.vendor_id, filters.category_id,
].filter((v) => v !== '' && v != null).length);

let debounce;
function navigate() {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get('/contracts', {
            q: filters.q || undefined,
            status: filters.status || undefined,
            payment_status: filters.payment_status || undefined,
            vendor_id: filters.vendor_id || undefined,
            category_id: filters.category_id || undefined,
        }, { preserveState: true, preserveScroll: true, replace: true });
    }, 350);
}

watch(() => filters.q, navigate);
watch(
    () => [filters.status, filters.payment_status, filters.vendor_id, filters.category_id],
    navigate,
);

function clearFilters() {
    filters.q = '';
    filters.status = '';
    filters.payment_status = '';
    filters.vendor_id = '';
    filters.category_id = '';
}

function onQuickFilter({ status, payment_status: payment }) {
    filters.status = status ?? '';
    filters.payment_status = payment ?? '';
}

// ── Dropdown "Dữ liệu" (xuất nhanh + mở modal nhập/xuất/đối soát) ──
const filterPanelDdRef = ref(null);
const dataMenuRef = ref(null);
const dataMenu = ref(false);
const showForm = ref(false);
const showData = ref(false);

const { panelStyle: dataMenuStyle } = useFixedDropdownAnchor(
    () => dataMenuRef.value,
    dataMenu,
    { width: 252, zIndex: 120 },
);

function toggleDataMenu() {
    dataMenu.value = !dataMenu.value;
    if (dataMenu.value) showFilterPanelDd.value = false;
}

function runExport() {
    dataMenu.value = false;
    if (!contractList.value.length) {
        toast.warning('Không có hợp đồng để xuất.');
        return;
    }
    try {
        downloadContractExport(contractList.value);
        toast.success(`Đã xuất ${contractList.value.length} hợp đồng.`);
    } catch {
        toast.error('Xuất file thất bại. Thử lại sau.');
    }
}

function openDataModal() {
    dataMenu.value = false;
    showData.value = true;
}

function onToolbarClickOutside(e) {
    const t = e.target;
    if (t.closest?.('[data-filter-visibility-panel]')) return;
    if (t.closest?.('[data-contract-data-panel]')) return;
    if (filterPanelDdRef.value && !filterPanelDdRef.value.contains(t)) {
        showFilterPanelDd.value = false;
    }
    if (dataMenuRef.value && !dataMenuRef.value.contains(t)) {
        dataMenu.value = false;
    }
}
onMounted(() => document.addEventListener('mousedown', onToolbarClickOutside));
onBeforeUnmount(() => document.removeEventListener('mousedown', onToolbarClickOutside));

function onSaved() {
    router.reload({ only: ['contracts', 'summary'] });
}
</script>

<template>
  <Head title="Danh mục hợp đồng" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Danh mục hợp đồng"
        subtitle="Khám phá theo nhà cung cấp → nhóm dịch vụ → hợp đồng"
        icon="documents"
        icon-color="brand"
        :badge="summary.total ?? null"
      >
        <button
          v-if="can.create"
          type="button"
          class="btn-primary"
          @click="showForm = true"
        >
          <AppIcon
            name="add"
            :size="15"
          /> Tạo hợp đồng
        </button>
      </PageHeader>
    </template>

    <ContractPortfolioSummaryBar
      :summary="summary"
      :active-status="filters.status"
      :active-payment="filters.payment_status"
      @quick-filter="onQuickFilter"
    />

    <div class="card overflow-visible">
      <div class="relative z-20 border-b border-slate-100 px-5 py-4">
        <div class="flex w-full min-w-0 flex-wrap items-center gap-2 lg:flex-nowrap">
          <div class="min-w-0 w-full basis-full lg:min-w-[10rem] lg:flex-1 lg:basis-auto">
            <DatagridToolbarSearch
              v-model="filters.q"
              input-id="contract-search"
              input-name="q"
              hide-label
              stretch
              inline-actions
              input-height="h-10"
              placeholder="Tìm theo tên, mã, đơn vị sử dụng…"
              aria-label="Tìm hợp đồng"
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
                :title="`Hiển thị bộ lọc (${enabledFilterControlCount}/${FILTER_CONTROL_LIST.length})`"
                @click="openFilterPanel(() => { dataMenu = false; })"
              >
                Lọc
              </DatagridToolbarActionButton>
              <FilterVisibilityDropdown
                v-model="visibleFilters"
                :show="showFilterPanelDd"
                :anchor-ref="filterPanelDdRef"
                :controls="FILTER_CONTROL_LIST"
                input-id-prefix="contract-filter-vis"
                @persist="persistVisibleFilters"
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
              data-contract-data-panel
            >
              <button
                type="button"
                class="flex w-full items-center gap-2.5 rounded-btn px-3 py-2 text-left hover:bg-slate-50"
                @click="runExport"
              >
                <AppIcon
                  name="export"
                  :size="15"
                  class="shrink-0 text-slate-400"
                />
                <div>
                  <span class="block text-sm font-medium text-slate-700">Xuất danh sách này</span>
                  <span class="block text-[10px] text-slate-400">{{ contractList.length }} hợp đồng · Excel (.xlsx)</span>
                </div>
              </button>
              <hr class="my-1 border-slate-100">
              <button
                type="button"
                class="flex w-full items-center gap-2.5 rounded-btn px-3 py-2 text-left hover:bg-slate-50"
                @click="openDataModal"
              >
                <AppIcon
                  name="upload"
                  :size="15"
                  class="shrink-0 text-slate-400"
                />
                <div>
                  <span class="block text-sm font-medium text-slate-700">Nhập / Xuất / Đối soát…</span>
                  <span class="block text-[10px] text-slate-400">Mở bảng điều khiển dữ liệu</span>
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
            <DatagridFilterField v-if="visibleFilters.status">
              <select
                v-model="filters.status"
                :class="FILTER_CONTROL_CLASS"
                aria-label="Trạng thái"
              >
                <option value="">
                  Trạng thái
                </option>
                <option
                  v-for="o in options.status"
                  :key="o.value"
                  :value="o.value"
                >
                  {{ o.label }}
                </option>
              </select>
            </DatagridFilterField>

            <DatagridFilterField v-if="visibleFilters.payment_status">
              <select
                v-model="filters.payment_status"
                :class="FILTER_CONTROL_CLASS"
                aria-label="Thanh toán"
              >
                <option value="">
                  Thanh toán
                </option>
                <option
                  v-for="o in options.paymentStatus"
                  :key="o.value"
                  :value="o.value"
                >
                  {{ o.label }}
                </option>
              </select>
            </DatagridFilterField>

            <DatagridFilterField v-if="visibleFilters.vendor">
              <select
                v-model="filters.vendor_id"
                :class="FILTER_CONTROL_CLASS"
                aria-label="Nhà cung cấp"
              >
                <option value="">
                  Nhà cung cấp
                </option>
                <option
                  v-for="v in vendors"
                  :key="v.id"
                  :value="v.id"
                >
                  {{ v.name }}
                </option>
              </select>
            </DatagridFilterField>

            <DatagridFilterField v-if="visibleFilters.category">
              <select
                v-model="filters.category_id"
                :class="FILTER_CONTROL_CLASS"
                aria-label="Nhóm dịch vụ"
              >
                <option value="">
                  Nhóm dịch vụ
                </option>
                <option
                  v-for="c in categories"
                  :key="c.id"
                  :value="c.id"
                >
                  {{ c.name }}
                </option>
              </select>
            </DatagridFilterField>

            <div
              v-if="appliedFilterCount || filters.q"
              class="col-span-full flex justify-end"
            >
              <button
                type="button"
                class="text-xs font-medium text-brand hover:underline"
                @click="clearFilters"
              >
                Đặt lại bộ lọc
              </button>
            </div>
          </div>
        </Transition>
      </div>

      <div class="p-4 sm:p-5">
        <ContractExplorer :tree="tree" />
      </div>
    </div>

    <ContractFormModal
      :show="showForm"
      :vendors="vendors"
      :categories="categories"
      :contracts="contractList"
      :employees="options.employees || []"
      :departments="options.departments || []"
      :status-options="options.status || []"
      :payment-options="options.paymentStatus || []"
      :billing-options="options.billingCycle || []"
      @close="showForm = false"
      @saved="onSaved"
    />

    <ContractDataModal
      :show="showData"
      :can-manage="can.import"
      :vendors="vendors"
      :categories="categories"
      :employees="options.employees || []"
      :departments="options.departments || []"
      :status-options="options.status || []"
      :payment-options="options.paymentStatus || []"
      :billing-options="options.billingCycle || []"
      @close="showData = false"
      @imported="onSaved"
    />
  </AppLayout>
</template>

<style scoped>
.fade-slide-enter-active,
.fade-slide-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}

.fade-slide-enter-from,
.fade-slide-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}
</style>
