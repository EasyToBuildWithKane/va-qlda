<script setup>
import {
    computed, reactive, ref, watch, onMounted, onBeforeUnmount,
} from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Badge from '@/shared/ui/Badge.vue';
import EvaluationSummaryBar from '@/modules/evaluation/components/EvaluationSummaryBar.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import DatagridToolbarActionButton from '@/shared/ui/DatagridToolbarActionButton.vue';
import DatagridFilterField from '@/shared/ui/DatagridFilterField.vue';
import FilterVisibilityDropdown from '@/shared/ui/FilterVisibilityDropdown.vue';
import DatagridPaginationFooter from '@/shared/ui/DatagridPaginationFooter.vue';
import { useVisibleFilterControls } from '@/shared/composables/useVisibleFilterControls';
import { useConfirmDelete } from '@/composables/useConfirmClose';
import { EMPTY_LABELS } from '@/shared/utils/emptyDisplay';
import { date } from '@/composables/useFormat';

const props = defineProps({
    configs: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
    departments: { type: Array, default: () => [] },
    templates: { type: Array, default: () => [] },
    templateTypeOptions: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
});

const confirmDelete = useConfirmDelete();
const filterPanelDdRef = ref(null);
const perPage = ref(Number(props.filters.per_page) || 20);

const FILTER_CONTROLS = [
    { key: 'department_code', label: 'Phòng ban', default: false },
    { key: 'template_type', label: 'Loại mẫu', default: false },
    { key: 'status', label: 'Trạng thái', default: false },
];

const FILTER_CONTROL_CLASS = 'input h-10 w-full text-sm';

const filters = reactive({
    q: props.filters.q || '',
    department_code: props.filters.department_code || '',
    template_type: props.filters.template_type || '',
    status: props.filters.status || '',
});

const {
    visibleFilters,
    showFilterPanelDd,
    enabledFilterControlCount,
    hasFilterRow,
    persistVisibleFilters,
    openFilterPanel,
} = useVisibleFilterControls(FILTER_CONTROLS, 'va-workspace.evaluation.visible-filters.v1');

const rows = computed(() => props.configs?.data || []);

let searchTimer = null;
watch(() => filters.q, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => applyFilters(), 350);
});

watch(perPage, () => applyFilters({ page: 1 }));

function applyFilters(extra = {}) {
    router.get(route('workspace.evaluation.index'), {
        q: filters.q || undefined,
        department_code: filters.department_code || undefined,
        template_type: filters.template_type || undefined,
        status: filters.status || undefined,
        per_page: perPage.value,
        ...extra,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function onQuickFilter(payload) {
    filters.status = payload.status || '';
    filters.template_type = payload.template_type || '';
    applyFilters({ page: 1 });
}

function onDelete(row) {
    confirmDelete(
        `Xoá cấu hình «${row.config_name}»? Thao tác không thể hoàn tác trên giao diện.`,
        () => router.delete(route('workspace.evaluation.destroy', row.id), { preserveScroll: true }),
    );
}

function formatRange(from, to) {
    const a = from ? date(from) : null;
    const b = to ? date(to) : null;
    if (!a && !b) return EMPTY_LABELS.period;
    if (a && !b) return `${a} trở đi`;
    if (!a && b) return `đến ${b}`;
    return `${a} – ${b}`;
}

function onDocClick(e) {
    if (showFilterPanelDd.value && filterPanelDdRef.value && !filterPanelDdRef.value.contains(e.target)) {
        const panel = document.querySelector('[data-filter-visibility-panel]');
        if (panel && panel.contains(e.target)) return;
        showFilterPanelDd.value = false;
    }
}

onMounted(() => document.addEventListener('mousedown', onDocClick));
onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onDocClick);
    clearTimeout(searchTimer);
});
</script>

<template>
  <Head title="Cấu hình đánh giá" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Cấu hình đánh giá"
        subtitle="Bộ quy tắc đánh giá theo phòng ban — siêu quản trị"
        icon="award"
      >
        <Link
          v-if="can.manage"
          :href="route('workspace.evaluation.create')"
          class="btn-primary inline-flex h-9 items-center gap-1.5 px-3 text-sm"
        >
          <AppIcon
            name="add"
            :size="15"
          />
          Thêm mới
        </Link>
      </PageHeader>
    </template>

    <EvaluationSummaryBar
      :summary="summary"
      :active-status="filters.status"
      :active-template-type="filters.template_type"
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
              placeholder="Tìm tên cấu hình, phòng ban…"
              aria-label="Tìm cấu hình đánh giá"
            />
          </div>
          <div
            ref="filterPanelDdRef"
            class="relative flex shrink-0 items-center gap-2"
          >
            <DatagridToolbarActionButton
              icon="filter"
              :active="enabledFilterControlCount > 0 || showFilterPanelDd"
              :title="enabledFilterControlCount ? `Lọc (${enabledFilterControlCount})` : 'Lọc'"
              @click="openFilterPanel()"
            >
              Lọc
              <span
                v-if="enabledFilterControlCount"
                class="ml-0.5 inline-flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-brand px-1 text-[10px] font-semibold text-white"
              >{{ enabledFilterControlCount }}</span>
            </DatagridToolbarActionButton>
            <FilterVisibilityDropdown
              v-model="visibleFilters"
              :show="showFilterPanelDd"
              :anchor-ref="filterPanelDdRef"
              :controls="FILTER_CONTROLS"
              input-id-prefix="evaluation-filter-vis"
              @persist="persistVisibleFilters"
            />
          </div>
        </div>

        <div
          v-if="hasFilterRow"
          class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6"
        >
          <DatagridFilterField v-if="visibleFilters.department_code">
            <select
              v-model="filters.department_code"
              :class="FILTER_CONTROL_CLASS"
              @change="applyFilters({ page: 1 })"
            >
              <option value="">
                Phòng ban
              </option>
              <option
                v-for="d in departments"
                :key="d.code"
                :value="d.code"
              >
                {{ d.name }}
              </option>
            </select>
          </DatagridFilterField>
          <DatagridFilterField v-if="visibleFilters.template_type">
            <select
              v-model="filters.template_type"
              :class="FILTER_CONTROL_CLASS"
              @change="applyFilters({ page: 1 })"
            >
              <option value="">
                Loại mẫu
              </option>
              <option
                v-for="opt in templateTypeOptions"
                :key="opt.value"
                :value="opt.value"
              >
                {{ opt.label }}
              </option>
            </select>
          </DatagridFilterField>
          <DatagridFilterField v-if="visibleFilters.status">
            <select
              v-model="filters.status"
              :class="FILTER_CONTROL_CLASS"
              @change="applyFilters({ page: 1 })"
            >
              <option value="">
                Trạng thái
              </option>
              <option value="active">
                Đang bật
              </option>
              <option value="inactive">
                Đã tắt
              </option>
              <option value="effective">
                Đang hiệu lực
              </option>
            </select>
          </DatagridFilterField>
        </div>
      </div>

      <div
        v-if="templates.length"
        class="border-b border-slate-100 bg-slate-50/60 px-5 py-3"
      >
        <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-brand/80">
          Mẫu phiếu chung
        </p>
        <div class="mt-2 flex flex-wrap gap-2">
          <Badge
            v-for="t in templates"
            :key="t.id"
            color="slate"
            :label="`${t.name} · ${t.criteria_count ?? 0} tiêu chí`"
          />
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-sm">
          <thead class="bg-slate-50 text-[11px] uppercase tracking-wide text-slate-500">
            <tr>
              <th class="px-5 py-3 font-medium">
                Phòng ban
              </th>
              <th class="px-5 py-3 font-medium">
                Tên cấu hình
              </th>
              <th class="px-5 py-3 font-medium">
                Loại
              </th>
              <th class="px-5 py-3 font-medium">
                Hiệu lực
              </th>
              <th class="px-5 py-3 font-medium">
                Tiêu chí
              </th>
              <th class="px-5 py-3 font-medium">
                Trạng thái
              </th>
              <th class="px-5 py-3 font-medium text-right">
                Hành động
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-if="rows.length === 0">
              <td
                colspan="7"
                class="px-5 py-10 text-center text-slate-500"
              >
                Chưa có cấu hình đánh giá.
              </td>
            </tr>
            <tr
              v-for="row in rows"
              :key="row.id"
              class="hover:bg-slate-50/80"
            >
              <td class="px-5 py-3">
                <div class="font-medium text-slate-800">
                  {{ row.department_name }}
                </div>
                <div class="font-mono text-xs text-slate-500">
                  {{ row.department_code }}
                </div>
              </td>
              <td class="px-5 py-3">
                <Link
                  :href="route('workspace.evaluation.show', row.id)"
                  class="font-medium text-brand hover:underline"
                >
                  {{ row.config_name }}
                </Link>
              </td>
              <td class="px-5 py-3">
                <Badge
                  :color="row.template_type === 'point_system' ? 'violet' : 'amber'"
                  :label="row.template_type_label"
                />
              </td>
              <td class="px-5 py-3 tabular-nums text-slate-700">
                {{ formatRange(row.effective_from, row.effective_to) }}
              </td>
              <td class="px-5 py-3 tabular-nums">
                {{ row.criteria_count ?? 0 }}
              </td>
              <td class="px-5 py-3">
                <Badge
                  :color="row.is_active ? 'emerald' : 'slate'"
                  :label="row.is_active ? 'Đang bật' : 'Đã tắt'"
                />
              </td>
              <td class="px-5 py-3">
                <div class="flex items-center justify-end gap-1">
                  <Link
                    :href="route('workspace.evaluation.show', row.id)"
                    class="btn-ghost h-8 w-8 p-0"
                    title="Xem"
                  >
                    <AppIcon
                      name="eye"
                      :size="14"
                    />
                  </Link>
                  <Link
                    v-if="can.manage"
                    :href="route('workspace.evaluation.edit', row.id)"
                    class="btn-ghost h-8 w-8 p-0"
                    title="Sửa"
                  >
                    <AppIcon
                      name="edit"
                      :size="14"
                    />
                  </Link>
                  <button
                    v-if="can.manage"
                    type="button"
                    class="btn-ghost h-8 w-8 p-0 text-rose-600"
                    title="Xóa"
                    @click="onDelete(row)"
                  >
                    <AppIcon
                      name="trash"
                      :size="14"
                    />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
          <tfoot>
            <DatagridPaginationFooter
              v-model:per-page="perPage"
              :meta="configs.meta"
              :colspan="7"
              :per-page-options="[10, 20, 25, 50]"
            />
          </tfoot>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
