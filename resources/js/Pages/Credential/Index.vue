<script setup>
import {
    computed, reactive, ref, watch, onMounted, onBeforeUnmount,
} from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Badge from '@/shared/ui/Badge.vue';
import CredentialSummaryBar from '@/modules/credential/components/CredentialSummaryBar.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import DatagridToolbarActionButton from '@/shared/ui/DatagridToolbarActionButton.vue';
import DatagridFilterField from '@/shared/ui/DatagridFilterField.vue';
import FilterVisibilityDropdown from '@/shared/ui/FilterVisibilityDropdown.vue';
import ColumnVisibilityDropdown from '@/shared/ui/ColumnVisibilityDropdown.vue';
import DatagridPaginationFooter from '@/shared/ui/DatagridPaginationFooter.vue';
import CredentialDataModal from '@/modules/credential/components/CredentialDataModal.vue';
import CredentialExpiryCountdown from '@/modules/credential/components/CredentialExpiryCountdown.vue';
import { CREDENTIAL_TABLE_COLUMNS } from '@/modules/credential/config/columns.js';
import { isCredentialExpiringWithinDays } from '@/modules/credential/utils/credentialExpiry';
import { useSecondTicker } from '@/shared/composables/useSecondTicker';
import { exportCredentialWorkbook } from '@/modules/credential/composables/useCredentialExport.js';
import { useVisibleFilterControls } from '@/shared/composables/useVisibleFilterControls';
import { useVisibleColumns } from '@/shared/composables/useVisibleColumns';
import { useFixedDropdownAnchor } from '@/shared/composables/useFixedDropdownAnchor';
import { useToast } from '@/shared/composables/useToast';
import { useConfirmDelete } from '@/composables/useConfirmClose';
import { date } from '@/composables/useFormat';

const props = defineProps({
    credentials: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({}) },
    can: { type: Object, default: () => ({}) },
});

const toast = useToast();
const confirmDelete = useConfirmDelete();
const { now: expiryNow } = useSecondTicker();

function rowExpiringSoon(row) {
    return isCredentialExpiringWithinDays(row.expires_at, 7);
}

function credentialRowClass(row) {
    if (highlightedId.value === row.id) {
        return 'bg-amber-50 ring-1 ring-inset ring-amber-300';
    }
    if (rowExpiringSoon(row)) {
        return 'bg-amber-50/90 border-l-4 border-l-amber-400';
    }
    return '';
}

const FILTER_CONTROLS = [
    { key: 'status', label: 'Trạng thái', default: false },
    { key: 'credential_type', label: 'Loại', default: false },
    { key: 'system_category', label: 'Hệ thống', default: false },
    { key: 'project', label: 'Dự án', default: false },
    { key: 'department', label: 'Phòng ban', default: false },
    { key: 'owner', label: 'Phụ trách', default: false },
    { key: 'environment', label: 'Môi trường', default: false },
];

const FILTER_CONTROL_CLASS = 'input h-10 w-full text-sm';
const filterPanelDdRef = ref(null);
const colDdRef = ref(null);
const dataMenuRef = ref(null);
const dataModal = ref(false);
const dataMenu = ref(false);
const dataModalInitialTab = ref('import');
const highlightedId = ref(null);
const perPage = ref(Number(props.filters.per_page) || 20);

const filters = reactive({
    q: props.filters.q || '',
    status: props.filters.status || '',
    credential_type: props.filters.credential_type || '',
    system_category: props.filters.system_category || '',
    project_id: props.filters.project_id || '',
    department_id: props.filters.department_id || '',
    owner_id: props.filters.owner_id || '',
    environment: props.filters.environment || '',
    kpi: props.filters.kpi || '',
});

const {
    visibleFilters,
    showFilterPanelDd,
    enabledFilterControlCount,
    hasFilterRow,
    persistVisibleFilters,
    openFilterPanel,
} = useVisibleFilterControls(FILTER_CONTROLS, 'va-workspace.credentials.visible-filters.v1');

const {
    visibleCols,
    showColDd,
    visibleColumnCount,
    persistVisibleColumns,
    openColPanel,
    isColVisible,
    TABLE_COLUMNS,
} = useVisibleColumns(CREDENTIAL_TABLE_COLUMNS, 'va-workspace.credentials.columns.v1');

const tableColspan = computed(() => visibleColumnCount.value + 1);

const { panelStyle: dataMenuStyle } = useFixedDropdownAnchor(
    () => dataMenuRef.value,
    dataMenu,
    { width: 248, zIndex: 120 },
);

let debounce;
function applyFilters(extra = {}) {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get(route('credentials.index'), {
            ...filters,
            per_page: perPage.value,
            ...extra,
        }, { preserveState: true, preserveScroll: true, replace: true });
    }, 350);
}

watch(() => filters.q, () => applyFilters());

function onQuickFilter(payload) {
    filters.kpi = payload.kpi ?? '';
    applyFilters({ kpi: filters.kpi });
}

function onToolbarClickOutside(e) {
    const t = e.target;
    if (t.closest?.('[data-filter-visibility-panel]')) return;
    if (t.closest?.('[data-column-visibility-panel]')) return;
    if (t.closest?.('[data-credential-data-panel]')) return;
    if (filterPanelDdRef.value && !filterPanelDdRef.value.contains(t)) {
        showFilterPanelDd.value = false;
    }
    if (colDdRef.value && !colDdRef.value.contains(t)) {
        showColDd.value = false;
    }
    if (dataMenuRef.value && !dataMenuRef.value.contains(t)) {
        dataMenu.value = false;
    }
}

function openFilterPanelSafe() {
    openFilterPanel(() => {
        showColDd.value = false;
        dataMenu.value = false;
    });
}

function openColPanelSafe() {
    openColPanel(() => {
        showFilterPanelDd.value = false;
        dataMenu.value = false;
    });
}

function toggleDataMenu() {
    dataMenu.value = !dataMenu.value;
    if (dataMenu.value) {
        showFilterPanelDd.value = false;
        showColDd.value = false;
    }
}

function runExport() {
    dataMenu.value = false;
    const rows = props.credentials.data ?? [];
    if (!rows.length) {
        toast.warning('Không có dữ liệu để xuất trên trang này.');
        return;
    }
    try {
        exportCredentialWorkbook(rows);
        toast.success(`Đã xuất ${rows.length} tài khoản (trang hiện tại).`);
    } catch {
        toast.error('Xuất file thất bại. Thử lại sau.');
    }
}

function openDataModal(tabName = 'import') {
    dataMenu.value = false;
    dataModalInitialTab.value = tabName;
    dataModal.value = true;
}

function onFixIssue(issue) {
    if (!issue.credentialId) return;
    highlightedId.value = issue.credentialId;
    // Scroll to the highlighted row after Vue renders
    setTimeout(() => {
        const el = document.querySelector(`[data-credential-id="${issue.credentialId}"]`);
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            el.focus?.();
        }
        // Clear highlight after 4 seconds
        setTimeout(() => { highlightedId.value = null; }, 4000);
    }, 100);
}

function removeCredential(row) {
    if (!row.can?.delete) return;
    confirmDelete(
        `Xoá tài khoản «${row.name}»? Thao tác không thể hoàn tác.`,
        () => router.delete(route('credentials.destroy', row.id), { preserveScroll: true }),
    );
}

onMounted(() => document.addEventListener('mousedown', onToolbarClickOutside));
onBeforeUnmount(() => document.removeEventListener('mousedown', onToolbarClickOutside));
</script>

<template>
  <Head title="Tài khoản & Mật khẩu" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Tài khoản & Mật khẩu"
        subtitle="Quản lý tập trung thông tin truy cập và tài sản số"
        icon="vault"
        :badge="summary.total ?? null"
      >
        <Link
          v-if="can.create"
          :href="route('credentials.create')"
          class="btn-primary inline-flex h-9 items-center gap-1.5 px-3 text-xs"
        >
          <AppIcon
            name="add"
            :size="15"
          />
          Thêm tài khoản
        </Link>
      </PageHeader>
    </template>

    <CredentialSummaryBar
      :summary="summary"
      :active-kpi="filters.kpi"
      @quick-filter="onQuickFilter"
    />

    <div class="card overflow-visible">
      <div class="relative z-20 border-b border-slate-100 px-5 py-4">
        <div class="flex w-full min-w-0 flex-wrap items-center gap-2 lg:flex-nowrap">
          <div class="min-w-0 w-full basis-full lg:min-w-[10rem] lg:flex-1 lg:basis-auto">
            <DatagridToolbarSearch
              v-model="filters.q"
              input-id="credential-search"
              hide-label
              stretch
              inline-actions
              input-height="h-10"
              placeholder="Tìm tên, username, URL, nhà cung cấp…"
              aria-label="Tìm tài khoản"
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
                @click="openFilterPanelSafe"
              >
                Lọc
              </DatagridToolbarActionButton>
              <FilterVisibilityDropdown
                v-model="visibleFilters"
                :show="showFilterPanelDd"
                :anchor-ref="filterPanelDdRef"
                :controls="FILTER_CONTROLS"
                input-id-prefix="credential-filter-vis"
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
                @click="openColPanelSafe"
              >
                Cột
              </DatagridToolbarActionButton>
              <ColumnVisibilityDropdown
                v-model="visibleCols"
                :show="showColDd"
                :columns="TABLE_COLUMNS"
                :anchor-ref="colDdRef"
                :fixed-labels="['Tên']"
                input-id-prefix="credential-col-vis"
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
              data-credential-data-panel
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
                  <span class="block text-sm font-medium text-slate-700">Xuất trang này</span>
                  <span class="block text-[10px] text-slate-400">{{ credentials.data?.length ?? 0 }} bản ghi · Excel (.xlsx)</span>
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
                @change="applyFilters()"
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
            <DatagridFilterField v-if="visibleFilters.credential_type">
              <select
                v-model="filters.credential_type"
                :class="FILTER_CONTROL_CLASS"
                aria-label="Loại"
                @change="applyFilters()"
              >
                <option value="">
                  Loại
                </option>
                <option
                  v-for="o in options.credential_type"
                  :key="o.value"
                  :value="o.value"
                >
                  {{ o.label }}
                </option>
              </select>
            </DatagridFilterField>
            <DatagridFilterField v-if="visibleFilters.system_category">
              <select
                v-model="filters.system_category"
                :class="FILTER_CONTROL_CLASS"
                aria-label="Hệ thống"
                @change="applyFilters()"
              >
                <option value="">
                  Hệ thống
                </option>
                <option
                  v-for="o in options.system_category"
                  :key="o.value"
                  :value="o.value"
                >
                  {{ o.label }}
                </option>
              </select>
            </DatagridFilterField>
            <DatagridFilterField v-if="visibleFilters.project">
              <select
                v-model="filters.project_id"
                :class="FILTER_CONTROL_CLASS"
                aria-label="Dự án"
                @change="applyFilters()"
              >
                <option value="">
                  Dự án
                </option>
                <option
                  v-for="p in options.projects"
                  :key="p.id"
                  :value="p.id"
                >
                  {{ p.name }}
                </option>
              </select>
            </DatagridFilterField>
            <DatagridFilterField v-if="visibleFilters.department">
              <select
                v-model="filters.department_id"
                :class="FILTER_CONTROL_CLASS"
                aria-label="Phòng ban"
                @change="applyFilters()"
              >
                <option value="">
                  Phòng ban
                </option>
                <option
                  v-for="d in options.departments"
                  :key="d.id"
                  :value="d.id"
                >
                  {{ d.name }}
                </option>
              </select>
            </DatagridFilterField>
            <DatagridFilterField v-if="visibleFilters.owner">
              <select
                v-model="filters.owner_id"
                :class="FILTER_CONTROL_CLASS"
                aria-label="Phụ trách"
                @change="applyFilters()"
              >
                <option value="">
                  Phụ trách
                </option>
                <option
                  v-for="o in options.owners"
                  :key="o.id"
                  :value="o.id"
                >
                  {{ o.display_name }}
                </option>
              </select>
            </DatagridFilterField>
            <DatagridFilterField v-if="visibleFilters.environment">
              <select
                v-model="filters.environment"
                :class="FILTER_CONTROL_CLASS"
                aria-label="Môi trường"
                @change="applyFilters()"
              >
                <option value="">
                  Môi trường
                </option>
                <option
                  v-for="o in options.environment"
                  :key="o.value"
                  :value="o.value"
                >
                  {{ o.label }}
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
              <th class="px-5 py-3">
                Tên
              </th>
              <th
                v-if="isColVisible('type_system')"
                class="px-5 py-3"
              >
                Loại / Hệ thống
              </th>
              <th
                v-if="isColVisible('username')"
                class="px-5 py-3"
              >
                Username
              </th>
              <th
                v-if="isColVisible('owner')"
                class="px-5 py-3"
              >
                Phụ trách
              </th>
              <th
                v-if="isColVisible('expires_at')"
                class="px-5 py-3"
              >
                Hết hạn
              </th>
              <th
                v-if="isColVisible('status')"
                class="px-5 py-3"
              >
                Trạng thái
              </th>
              <th
                class="w-11 px-2 py-3 text-right"
                aria-label="Thao tác"
              >
                <span class="sr-only">Thao tác</span>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in credentials.data"
              :key="row.id"
              :data-credential-id="row.id"
              class="group border-t border-slate-100 transition-colors hover:bg-slate-50/80"
              :class="credentialRowClass(row)"
            >
              <td class="px-5 py-3">
                <Link
                  :href="route('credentials.show', row.id)"
                  class="font-medium text-brand hover:underline"
                >
                  {{ row.name }}
                </Link>
                <div
                  v-if="row.badges?.length"
                  class="mt-1 flex flex-wrap gap-1"
                >
                  <Badge
                    v-for="b in row.badges.filter(Boolean)"
                    :key="b"
                    :label="b"
                    color="slate"
                    class="text-[10px]"
                  />
                </div>
                <CredentialExpiryCountdown
                  v-if="rowExpiringSoon(row) && !isColVisible('expires_at')"
                  :expires-at="row.expires_at"
                  :now="expiryNow"
                />
              </td>
              <td
                v-if="isColVisible('type_system')"
                class="px-5 py-3 text-slate-600"
              >
                <div>{{ row.credential_type?.label || 'Chưa phân loại' }}</div>
                <div class="text-xs text-slate-500">
                  {{ row.system_category?.label || 'Chưa chọn hệ thống' }}
                </div>
              </td>
              <td
                v-if="isColVisible('username')"
                class="px-5 py-3 font-mono text-xs text-slate-600"
              >
                <span :class="{ 'text-slate-400 italic font-sans': !row.username }">
                  {{ row.username || 'Chưa có username' }}
                </span>
              </td>
              <td
                v-if="isColVisible('owner')"
                class="px-5 py-3"
              >
                <span :class="{ 'text-slate-400 italic text-xs': !row.owner?.display_name }">
                  {{ row.owner?.display_name || 'Chưa gán phụ trách' }}
                </span>
              </td>
              <td
                v-if="isColVisible('expires_at')"
                class="px-5 py-3 text-xs"
              >
                <span
                  :class="{
                    'text-slate-400 italic': !row.expires_at,
                    'font-medium text-amber-900': rowExpiringSoon(row),
                  }"
                >
                  {{ row.expires_at ? date(row.expires_at) : 'Không hết hạn' }}
                </span>
                <CredentialExpiryCountdown
                  v-if="rowExpiringSoon(row)"
                  :expires-at="row.expires_at"
                  :now="expiryNow"
                />
              </td>
              <td
                v-if="isColVisible('status')"
                class="px-5 py-3"
              >
                <Badge
                  v-if="row.status?.label"
                  :label="row.status.label"
                  :color="row.status.color || 'slate'"
                />
                <span
                  v-else
                  class="text-xs italic text-slate-400"
                >Chưa xác định</span>
              </td>
              <td class="px-2 py-3 text-right">
                <button
                  v-if="row.can?.delete"
                  type="button"
                  class="grid h-8 w-8 place-items-center rounded-md text-slate-400 opacity-0 transition hover:bg-rose-50 hover:text-rose-600 group-hover:opacity-100 focus-visible:opacity-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand/30"
                  title="Xoá"
                  :aria-label="`Xoá ${row.name}`"
                  @click="removeCredential(row)"
                >
                  <AppIcon
                    name="delete"
                    :size="15"
                  />
                </button>
              </td>
            </tr>
            <tr v-if="!credentials.data?.length">
              <td
                :colspan="Math.max(tableColspan, 1)"
                class="px-5 py-10 text-center text-slate-500"
              >
                Không có tài khoản phù hợp.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <DatagridPaginationFooter
        v-model:per-page="perPage"
        :meta="credentials.meta"
        @change="applyFilters()"
      />
    </div>

    <CredentialDataModal
      v-model="dataModal"
      :options="options"
      :rows="credentials.data"
      :can-manage="can.create"
      :initial-tab="dataModalInitialTab"
      :active-filters="filters"
      @fix="onFixIssue"
    />
  </AppLayout>
</template>
