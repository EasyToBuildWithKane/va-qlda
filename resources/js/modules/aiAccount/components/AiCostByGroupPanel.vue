<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import VndAmount from '@/modules/aiAccount/components/VndAmount.vue';
import DatagridToolbarActionButton from '@/shared/ui/DatagridToolbarActionButton.vue';
import ColumnVisibilityDropdown from '@/shared/ui/ColumnVisibilityDropdown.vue';
import { useVisibleColumns } from '@/shared/composables/useVisibleColumns';
import { GROUP_COST_COLUMNS } from '@/modules/aiAccount/config/groupCostColumns';
import { exportAiGroupCost } from '@/modules/aiAccount/composables/useAiGroupCostExport';
import { useToast } from '@/shared/composables/useToast';

const COLS_KEY = 'va-qlda.ai-cost-report.group-cost-columns';

const props = defineProps({
    rows: { type: Array, default: () => [] },
    cards: { type: Object, default: null },
    options: { type: Object, default: () => ({}) },
    filterNote: { type: String, default: '' },
    showAccountLink: { type: Boolean, default: true },
});

const toast = useToast();
const showExportDd = ref(false);
const colDdRef = ref(null);
const exportDdRef = ref(null);

const {
    visibleCols,
    showColDd,
    persistVisibleColumns,
    openColPanel,
    isColVisible,
} = useVisibleColumns(GROUP_COST_COLUMNS, COLS_KEY);

const colVisible = computed(() =>
    Object.fromEntries(GROUP_COST_COLUMNS.map((c) => [c.key, isColVisible(c.key)])),
);

const totalMonthly = computed(() =>
    props.cards?.monthly_cost_running
    ?? props.rows.reduce((s, r) => s + (r.cost_monthly ?? 0), 0),
);

const totals = computed(() => ({
    accounts: props.rows.reduce((s, r) => s + (r.total_accounts ?? 0), 0),
    active: props.rows.reduce((s, r) => s + (r.active_accounts ?? 0), 0),
    expiring: props.rows.reduce((s, r) => s + (r.expiring_soon ?? 0), 0),
    expired: props.rows.reduce((s, r) => s + (r.expired ?? 0), 0),
    pending: props.rows.reduce((s, r) => s + (r.proposal_monthly_pending_sync ?? 0), 0),
}));

function labelFor(row) {
    const opt = props.options?.group_function?.find((o) => o.value === row.group);
    return opt?.label ?? row.group_label ?? row.group;
}

function openCol() {
    openColPanel(() => {
        showExportDd.value = false;
    });
}

function openExport() {
    showExportDd.value = !showExportDd.value;
    if (showExportDd.value) showColDd.value = false;
}

function runExport(format) {
    showExportDd.value = false;
    if (!props.rows.length) {
        toast.warning('Không có dữ liệu nhóm để xuất.');
        return;
    }
    const name = exportAiGroupCost({
        rows: props.rows,
        cards: props.cards,
        options: props.options,
        filterNote: props.filterNote,
        format,
    });
    if (name) toast.success(`Đã xuất ${name}`);
}

function onDocClick(e) {
    if (e.target.closest?.('[data-column-visibility-panel]')) return;
    if (colDdRef.value && !colDdRef.value.contains(e.target)) showColDd.value = false;
    if (exportDdRef.value && !exportDdRef.value.contains(e.target)) showExportDd.value = false;
}

onMounted(() => document.addEventListener('mousedown', onDocClick));
onUnmounted(() => document.removeEventListener('mousedown', onDocClick));
</script>

<template>
  <div class="card overflow-visible">
    <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-700">
      <div class="flex w-full min-w-0 flex-wrap items-center gap-2 lg:flex-nowrap">
        <p class="min-w-0 flex-1 text-sm text-slate-600">
          <span class="font-semibold text-slate-800">{{ rows.length }}</span> nhóm chức năng
        </p>

        <div class="ml-auto flex shrink-0 flex-wrap items-center gap-2">
          <div
            ref="colDdRef"
            class="relative shrink-0"
          >
            <DatagridToolbarActionButton
              icon="columns"
              :active="showColDd"
              title="Cột hiển thị"
              @click="openCol"
            >
              Cột
            </DatagridToolbarActionButton>
            <ColumnVisibilityDropdown
              v-model="visibleCols"
              :show="showColDd"
              :anchor-ref="colDdRef"
              :columns="GROUP_COST_COLUMNS"
              :fixed-labels="['Nhóm chức năng']"
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
              title="Xuất báo cáo chi phí theo nhóm"
              @click="openExport"
            >
              Xuất
            </DatagridToolbarActionButton>
            <div
              v-if="showExportDd"
              class="absolute right-0 top-full z-30 mt-1.5 min-w-[10rem] rounded-xl border border-slate-200 bg-white py-1 shadow-elevation-2 dark:border-slate-700 dark:bg-slate-900"
            >
              <button
                type="button"
                class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800"
                @click="runExport('xlsx')"
              >
                Excel (.xlsx)
              </button>
              <button
                type="button"
                class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800"
                @click="runExport('csv')"
              >
                CSV
              </button>
            </div>
          </div>

          <Link
            v-if="showAccountLink"
            :href="route('ai-accounts.index')"
            class="btn-ghost inline-flex h-10 items-center gap-1.5 border border-slate-200 px-3 text-xs font-medium"
          >
            <AppIcon
              name="back"
              :size="15"
            />
            Danh sách TK
          </Link>
        </div>
      </div>

      <div
        v-if="totalMonthly > 0"
        class="mt-3 flex flex-wrap gap-2"
      >
        <span class="inline-flex items-center gap-1.5 rounded-lg border border-violet-200 bg-violet-50 px-2.5 py-1 text-xs font-medium text-violet-800">
          <AppIcon
            name="cost"
            :size="14"
          />
          Tổng
          <VndAmount
            :amount="totalMonthly"
            suffix="/tháng"
            compact
          />
        </span>
        <span
          v-if="totals.pending > 0"
          class="inline-flex items-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs text-amber-900"
        >
          Chưa lập TK:
          <VndAmount
            :amount="totals.pending"
            suffix="/tháng"
            compact
          />
        </span>
      </div>
    </div>

    <div
      v-if="!rows.length"
      class="px-5 py-12 text-center text-sm text-slate-500"
    >
      Chưa có dữ liệu chi phí theo nhóm. Duyệt phiếu đề xuất để chi phí được tính tự động.
    </div>

    <div
      v-else
      class="overflow-x-auto"
    >
      <table class="w-full min-w-[720px] text-left text-sm">
        <thead class="border-b border-slate-100 bg-slate-50 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
          <tr>
            <th class="px-5 py-3">
              Nhóm chức năng
            </th>
            <th
              v-if="colVisible.stats"
              class="px-4 py-3"
            >
              Thống kê tài khoản
            </th>
            <th
              v-if="colVisible.cost_monthly"
              class="px-4 py-3 text-right"
            >
              Chi phí / tháng
            </th>
            <th
              v-if="colVisible.pending"
              class="px-4 py-3 text-right"
            >
              Phiếu chưa lập TK
            </th>
            <th
              v-if="colVisible.share"
              class="px-4 py-3 text-right"
            >
              Tỷ trọng
            </th>
            <th
              v-if="colVisible.yearly"
              class="px-4 py-3 text-right"
            >
              Ước tính / năm
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr
            v-for="row in rows"
            :key="row.group"
            class="transition-colors hover:bg-slate-50/60"
          >
            <td class="px-5 py-3.5">
              <div class="flex items-center gap-2.5">
                <span
                  class="h-2.5 w-2.5 shrink-0 rounded-full ring-2 ring-white"
                  :style="{ backgroundColor: row.dot_color }"
                />
                <div>
                  <p class="font-medium text-slate-800">
                    {{ labelFor(row) }}
                  </p>
                  <p class="text-[11px] text-slate-400">
                    {{ row.group }}
                  </p>
                </div>
              </div>
            </td>
            <td
              v-if="colVisible.stats"
              class="px-4 py-3.5"
            >
              <div class="flex flex-wrap gap-1.5 text-xs">
                <span class="rounded-md bg-slate-100 px-2 py-0.5 tabular-nums text-slate-700">
                  {{ row.total_accounts }} TK
                </span>
                <span
                  v-if="row.active_accounts > 0"
                  class="rounded-md bg-emerald-50 px-2 py-0.5 text-emerald-700"
                >
                  {{ row.active_accounts }} hoạt động
                </span>
                <span
                  v-if="row.expiring_soon > 0"
                  class="rounded-md bg-amber-50 px-2 py-0.5 text-amber-800"
                >
                  {{ row.expiring_soon }} sắp HH
                </span>
                <span
                  v-if="row.expired > 0"
                  class="rounded-md bg-rose-50 px-2 py-0.5 text-rose-700"
                >
                  {{ row.expired }} hết hạn
                </span>
                <span
                  v-if="row.cancelled > 0"
                  class="rounded-md bg-slate-50 px-2 py-0.5 text-slate-500"
                >
                  {{ row.cancelled }} huỷ
                </span>
              </div>
            </td>
            <td
              v-if="colVisible.cost_monthly"
              class="px-4 py-3.5 text-right"
            >
              <VndAmount
                :amount="row.cost_monthly"
                suffix=" / tháng"
                compact
                class="font-semibold text-violet-700"
              />
            </td>
            <td
              v-if="colVisible.pending"
              class="px-4 py-3.5 text-right"
            >
              <VndAmount
                v-if="row.proposal_monthly_pending_sync"
                :amount="row.proposal_monthly_pending_sync"
                suffix=" / tháng"
                compact
                class="text-amber-800"
              />
              <span
                v-else
                class="text-slate-300"
              >—</span>
            </td>
            <td
              v-if="colVisible.share"
              class="px-4 py-3.5"
            >
              <div class="flex items-center justify-end gap-2">
                <div class="h-1.5 w-16 overflow-hidden rounded-full bg-slate-100">
                  <div
                    class="h-full rounded-full bg-brand transition-all"
                    :style="{ width: `${Math.min(100, row.cost_share_percent ?? 0)}%` }"
                  />
                </div>
                <span class="min-w-[2.5rem] text-right text-xs font-semibold tabular-nums text-slate-700">
                  {{ row.cost_share_percent ?? 0 }}%
                </span>
              </div>
            </td>
            <td
              v-if="colVisible.yearly"
              class="px-4 py-3.5 text-right text-slate-600"
            >
              <VndAmount
                :amount="(row.cost_monthly ?? 0) * 12"
                compact
              />
            </td>
          </tr>
        </tbody>
        <tfoot class="border-t-2 border-slate-200 bg-slate-50/80 text-sm font-semibold text-slate-800">
          <tr>
            <td class="px-5 py-3">
              Tổng cộng
            </td>
            <td
              v-if="colVisible.stats"
              class="px-4 py-3 text-xs font-medium text-slate-600"
            >
              {{ totals.accounts }} TK · {{ totals.active }} hoạt động
              <template v-if="totals.expiring + totals.expired > 0">
                · {{ totals.expiring + totals.expired }} cần chú ý
              </template>
            </td>
            <td
              v-if="colVisible.cost_monthly"
              class="px-4 py-3 text-right"
            >
              <VndAmount
                :amount="totalMonthly"
                suffix=" / tháng"
                compact
                class="text-brand"
              />
            </td>
            <td
              v-if="colVisible.pending"
              class="px-4 py-3 text-right"
            >
              <VndAmount
                v-if="totals.pending > 0"
                :amount="totals.pending"
                compact
              />
              <span
                v-else
                class="font-normal text-slate-300"
              >—</span>
            </td>
            <td
              v-if="colVisible.share"
              class="px-4 py-3 text-right tabular-nums"
            >
              100%
            </td>
            <td
              v-if="colVisible.yearly"
              class="px-4 py-3 text-right"
            >
              <VndAmount
                :amount="totalMonthly * 12"
                compact
              />
            </td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</template>
