<script setup>
import {
    computed, ref, onMounted, onBeforeUnmount,
} from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import ReportSummaryBar from '@/modules/contract/components/ReportSummaryBar.vue';
import DatagridSegmentedControl from '@/shared/ui/DatagridSegmentedControl.vue';
import DatagridToolbarActionButton from '@/shared/ui/DatagridToolbarActionButton.vue';
import { useFixedDropdownAnchor } from '@/shared/composables/useFixedDropdownAnchor';
import { formatMoney } from '@/modules/contract/composables/useContractFormat.js';
import { downloadReportExcel, downloadReportCsv } from '@/modules/contract/composables/useContractReportExport.js';

const props = defineProps({
    report: { type: Object, required: true },
});

const DIMENSIONS = [
    { key: 'byVendor', label: 'Theo nhà cung cấp', icon: 'vendor', col: 'Nhà cung cấp' },
    { key: 'byCategory', label: 'Theo nhóm dịch vụ', icon: 'documents', col: 'Nhóm dịch vụ' },
    { key: 'byUnit', label: 'Theo đơn vị', icon: 'org-teams', col: 'Đơn vị sử dụng' },
    { key: 'byYear', label: 'Theo năm', icon: 'clock', col: 'Năm' },
];

const dim = ref('byVendor');
const current = computed(() => DIMENSIONS.find((d) => d.key === dim.value));
const rows = computed(() => props.report[dim.value] || []);

const totals = computed(() => ({
    count: rows.value.reduce((s, r) => s + (r.count || 0), 0),
    annual: rows.value.reduce((s, r) => s + (Number(r.annual_cost) || 0), 0),
    lifecycle: rows.value.reduce((s, r) => s + (Number(r.lifecycle_cost) || 0), 0),
}));

// ── Xuất dropdown (CSV | Excel) ──
const exportMenuRef = ref(null);
const exportMenu = ref(false);
const { panelStyle: exportMenuStyle } = useFixedDropdownAnchor(
    () => exportMenuRef.value,
    exportMenu,
    { width: 220, zIndex: 120 },
);

function exportExcel() {
    exportMenu.value = false;
    downloadReportExcel(rows.value, { dimensionLabel: current.value.col });
}
function exportCsv() {
    exportMenu.value = false;
    downloadReportCsv(rows.value, { dimensionLabel: current.value.col });
}

function onClickOutside(e) {
    if (e.target.closest?.('[data-report-export-panel]')) return;
    if (exportMenuRef.value && !exportMenuRef.value.contains(e.target)) {
        exportMenu.value = false;
    }
}
onMounted(() => document.addEventListener('mousedown', onClickOutside));
onBeforeUnmount(() => document.removeEventListener('mousedown', onClickOutside));
</script>

<template>
  <Head title="Báo cáo hợp đồng" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Báo cáo hợp đồng"
        subtitle="Tổng hợp theo NCC, dịch vụ, đơn vị, năm — xuất Excel/CSV"
        icon="team-eval"
        icon-color="brand"
      />
    </template>

    <div class="mx-auto max-w-4xl px-4 py-5">
      <ReportSummaryBar :report="report" />

      <div class="card overflow-visible">
        <div class="flex w-full min-w-0 flex-wrap items-center gap-2 border-b border-slate-100 px-5 py-4 lg:flex-nowrap">
          <DatagridSegmentedControl
            v-model="dim"
            :items="DIMENSIONS"
            aria-label="Chiều phân tích báo cáo"
            icon-only-below-sm
          />

          <div
            ref="exportMenuRef"
            class="relative ml-auto shrink-0"
          >
            <DatagridToolbarActionButton
              icon="export"
              :active="exportMenu"
              title="Xuất báo cáo"
              @click="exportMenu = !exportMenu"
            >
              Xuất
            </DatagridToolbarActionButton>
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
              v-if="exportMenu"
              :style="exportMenuStyle"
              class="overflow-hidden rounded-card border border-slate-200 bg-white p-1 shadow-elevation-2"
              data-report-export-panel
            >
              <button
                type="button"
                class="flex w-full items-center gap-2.5 rounded-btn px-3 py-2 text-left hover:bg-slate-50"
                @click="exportExcel"
              >
                <AppIcon
                  name="export"
                  :size="15"
                  class="shrink-0 text-slate-400"
                />
                <span class="text-sm font-medium text-slate-700">Xuất Excel (.xlsx)</span>
              </button>
              <button
                type="button"
                class="flex w-full items-center gap-2.5 rounded-btn px-3 py-2 text-left hover:bg-slate-50"
                @click="exportCsv"
              >
                <AppIcon
                  name="download"
                  :size="15"
                  class="shrink-0 text-slate-400"
                />
                <span class="text-sm font-medium text-slate-700">Xuất CSV (.csv)</span>
              </button>
            </div>
          </Transition>
        </Teleport>

        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
              <tr>
                <th class="px-4 py-2.5 font-semibold">
                  {{ current.col }}
                </th>
                <th class="px-4 py-2.5 text-right font-semibold">
                  Số HĐ
                </th>
                <th class="px-4 py-2.5 text-right font-semibold">
                  Chi phí năm
                </th>
                <th class="px-4 py-2.5 text-right font-semibold">
                  Chi phí vòng đời
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr
                v-for="r in rows"
                :key="r.label"
                class="hover:bg-slate-50/60"
              >
                <td class="px-4 py-2.5 text-slate-700">
                  {{ r.label }}
                </td>
                <td class="px-4 py-2.5 text-right tabular-nums text-slate-600">
                  {{ r.count }}
                </td>
                <td class="px-4 py-2.5 text-right tabular-nums text-slate-700">
                  {{ formatMoney(r.annual_cost) }}
                </td>
                <td class="px-4 py-2.5 text-right tabular-nums text-slate-700">
                  {{ formatMoney(r.lifecycle_cost) }}
                </td>
              </tr>
              <tr v-if="!rows.length">
                <td
                  colspan="4"
                  class="px-4 py-10 text-center text-slate-400"
                >
                  Chưa có dữ liệu.
                </td>
              </tr>
            </tbody>
            <tfoot
              v-if="rows.length"
              class="border-t-2 border-slate-200 bg-slate-50 font-semibold text-slate-800"
            >
              <tr>
                <td class="px-4 py-2.5">
                  Tổng cộng
                </td>
                <td class="px-4 py-2.5 text-right tabular-nums">
                  {{ totals.count }}
                </td>
                <td class="px-4 py-2.5 text-right tabular-nums">
                  {{ formatMoney(totals.annual) }}
                </td>
                <td class="px-4 py-2.5 text-right tabular-nums">
                  {{ formatMoney(totals.lifecycle) }}
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
