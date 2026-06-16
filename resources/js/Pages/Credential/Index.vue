<script setup>
import { reactive, ref, watch, onMounted, onBeforeUnmount } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Badge from '@/shared/ui/Badge.vue';
import CredentialSummaryBar from '@/modules/credential/components/CredentialSummaryBar.vue';
import CredentialHelpBanner from '@/modules/credential/components/CredentialHelpBanner.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import DatagridToolbarActionButton from '@/shared/ui/DatagridToolbarActionButton.vue';
import DatagridFilterField from '@/shared/ui/DatagridFilterField.vue';
import FilterVisibilityDropdown from '@/shared/ui/FilterVisibilityDropdown.vue';
import DatagridPaginationFooter from '@/shared/ui/DatagridPaginationFooter.vue';
import CredentialDataModal from '@/modules/credential/CredentialDataModal.vue';
import { useVisibleFilterControls } from '@/shared/composables/useVisibleFilterControls';
import { date } from '@/composables/useFormat';

const props = defineProps({
    credentials: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({}) },
    can: { type: Object, default: () => ({}) },
});

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
const dataModal = ref(false);
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
    hasFilterRow,
    persistVisibleFilters,
    openFilterPanel,
} = useVisibleFilterControls(FILTER_CONTROLS, 'va-qlda.credentials.visible-filters.v1');

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
    if (filterPanelDdRef.value && !filterPanelDdRef.value.contains(e.target)) {
        showFilterPanelDd.value = false;
    }
}
onMounted(() => document.addEventListener('mousedown', onToolbarClickOutside));
onBeforeUnmount(() => document.removeEventListener('mousedown', onToolbarClickOutside));

function statusTone(st) {
    return st?.badgeColor || 'slate';
}
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

    <CredentialHelpBanner
      title="Danh sách tài khoản & tài sản số"
      intro="Tra cứu nhanh theo tên, username hoặc nhà cung cấp. Thẻ KPI phía dưới lọc nhanh — bấm vào thẻ để áp dụng bộ lọc."
      :steps="[
        'Dùng ô tìm kiếm hoặc Lọc để thu hẹp danh sách.',
        'Bấm tên tài khoản để mở hồ sơ — xem mật khẩu tại tab Bảo mật (có audit).',
        'Nút Dữ liệu: nhập/xuất Excel hoặc đối soát hồ sơ thiếu phụ trách / MFA.',
      ]"
    />

    <CredentialSummaryBar
      :summary="summary"
      :active-kpi="filters.kpi"
      @quick-filter="onQuickFilter"
    />

    <div class="card overflow-hidden">
      <div class="border-b border-slate-100 px-5 py-4">
        <div class="flex w-full min-w-0 flex-wrap items-center gap-2 lg:flex-nowrap">
          <div class="min-w-0 w-full basis-full lg:flex-1 lg:basis-auto">
            <DatagridToolbarSearch
              v-model="filters.q"
              hide-label
              stretch
              inline-actions
              input-height="h-10"
              placeholder="Tìm tên, username, URL, nhà cung cấp…"
              aria-label="Tìm tài khoản"
            />
          </div>
          <div
            ref="filterPanelDdRef"
            class="relative flex shrink-0 items-center gap-2"
          >
            <DatagridToolbarActionButton
              icon="filter"
              :active="showFilterPanelDd"
              @click="openFilterPanel()"
            >
              Lọc
            </DatagridToolbarActionButton>
            <DatagridToolbarActionButton
              icon="upload"
              @click="dataModal = true"
            >
              Dữ liệu
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
        </div>
        <div
          v-if="hasFilterRow"
          class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6"
        >
          <DatagridFilterField v-if="visibleFilters.status">
            <select
              v-model="filters.status"
              :class="FILTER_CONTROL_CLASS"
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
              @change="applyFilters()"
            >
              <option value="">
                Loại tài khoản
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
              @change="applyFilters()"
            >
              <option value="">
                Người phụ trách
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
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
            <tr>
              <th class="px-5 py-3">
                Tên
              </th>
              <th class="px-5 py-3">
                Loại / Hệ thống
              </th>
              <th class="px-5 py-3">
                Username
              </th>
              <th class="px-5 py-3">
                Phụ trách
              </th>
              <th class="px-5 py-3">
                Hết hạn
              </th>
              <th class="px-5 py-3">
                Trạng thái
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in credentials.data"
              :key="row.id"
              class="border-t border-slate-100 hover:bg-slate-50/80"
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
                    v-for="b in row.badges"
                    :key="b"
                    tone="slate"
                    class="text-[10px]"
                  >
                    {{ b }}
                  </Badge>
                </div>
              </td>
              <td class="px-5 py-3 text-slate-600">
                <div>{{ row.credential_type?.label }}</div>
                <div class="text-xs">
                  {{ row.system_category?.label }}
                </div>
              </td>
              <td class="px-5 py-3 font-mono text-xs">
                {{ row.username || '—' }}
              </td>
              <td class="px-5 py-3">
                {{ row.owner?.display_name || '—' }}
              </td>
              <td class="px-5 py-3 text-xs">
                {{ row.expires_at ? date(row.expires_at) : '—' }}
              </td>
              <td class="px-5 py-3">
                <Badge :tone="statusTone(row.status)">
                  {{ row.status?.label }}
                </Badge>
              </td>
            </tr>
            <tr v-if="!credentials.data?.length">
              <td
                colspan="6"
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
    />
  </AppLayout>
</template>
