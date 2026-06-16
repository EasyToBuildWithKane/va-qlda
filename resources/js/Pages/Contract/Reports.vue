<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { formatMoney } from '@/modules/contract/composables/useContractFormat.js';
import { downloadReportExcel, downloadReportCsv } from '@/modules/contract/composables/useContractReportExport.js';

const props = defineProps({
    report: { type: Object, required: true },
});

const DIMENSIONS = [
    { key: 'byVendor', label: 'Theo nhà cung cấp', col: 'Nhà cung cấp' },
    { key: 'byCategory', label: 'Theo nhóm dịch vụ', col: 'Nhóm dịch vụ' },
    { key: 'byUnit', label: 'Theo đơn vị sử dụng', col: 'Đơn vị sử dụng' },
    { key: 'byYear', label: 'Theo năm hết hạn', col: 'Năm' },
];

const dim = ref('byVendor');
const current = computed(() => DIMENSIONS.find((d) => d.key === dim.value));
const rows = computed(() => props.report[dim.value] || []);

const totals = computed(() => ({
    count: rows.value.reduce((s, r) => s + (r.count || 0), 0),
    annual: rows.value.reduce((s, r) => s + (Number(r.annual_cost) || 0), 0),
    lifecycle: rows.value.reduce((s, r) => s + (Number(r.lifecycle_cost) || 0), 0),
}));

function exportExcel() {
    downloadReportExcel(rows.value, { dimensionLabel: current.value.col });
}
function exportCsv() {
    downloadReportCsv(rows.value, { dimensionLabel: current.value.col });
}
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
      >
        <button
          type="button"
          class="btn-ghost"
          @click="exportCsv"
        >
          <AppIcon
            name="download"
            :size="15"
          /> CSV
        </button>
        <button
          type="button"
          class="btn-primary"
          @click="exportExcel"
        >
          <AppIcon
            name="export"
            :size="15"
          /> Excel
        </button>
      </PageHeader>
    </template>

    <div class="mx-auto max-w-4xl px-4 py-5">
      <div class="mb-4 flex flex-wrap gap-2">
        <button
          v-for="d in DIMENSIONS"
          :key="d.key"
          type="button"
          class="rounded-full border px-3 py-1 text-sm font-medium transition"
          :class="dim === d.key ? 'border-brand bg-brand/10 text-brand' : 'border-slate-200 text-slate-500 hover:bg-slate-50'"
          @click="dim = d.key"
        >
          {{ d.label }}
        </button>
      </div>

      <div class="overflow-hidden rounded-card border border-slate-200 bg-white">
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
  </AppLayout>
</template>
