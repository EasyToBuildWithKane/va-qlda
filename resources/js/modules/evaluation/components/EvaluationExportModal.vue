<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Modal from '@/Components/Ui/Modal.vue';
import { useToast } from '@/shared/composables/useToast';
import { EVALUATION_TABLE_COLUMNS } from '@/modules/evaluation/config/columns.js';
import {
    exportEvaluationWorkbook,
    exportEvaluationCsv,
    EXPORT_FORMATS,
} from '@/modules/evaluation/composables/useEvaluationExport.js';

const props = defineProps({
    show: { type: Boolean, default: false },
    rows: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
    visibleCols: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['close']);
const toast = useToast();

const scope = ref('filtered');
const format = ref('xlsx');
const columns = ref({});
const loadingAll = ref(false);
const allRows = ref(null);

const hasActiveFilters = computed(() => Object.values(props.filters || {}).some((v) => !!v));

const filteredCount = computed(() => props.rows.length);
const allCount = computed(() => allRows.value?.length ?? null);

const exportRowCount = computed(() => (scope.value === 'all' ? (allCount.value ?? filteredCount.value) : filteredCount.value));

function resetOptions() {
    scope.value = 'filtered';
    format.value = 'xlsx';
    columns.value = { ...props.visibleCols };
    allRows.value = null;
    loadingAll.value = false;
}

watch(() => props.show, (open) => {
    if (open) resetOptions();
});

function close() {
    emit('close');
}

function toggleColumn(key) {
    columns.value = { ...columns.value, [key]: !columns.value[key] };
}

function onScopeChange(value) {
    scope.value = value;
    if (value === 'all' && allRows.value === null && !loadingAll.value) {
        loadingAll.value = true;
        router.get(route('workspace.evaluation.index'), {}, {
            only: ['criteria'],
            preserveState: true,
            preserveScroll: true,
            onSuccess: (page) => {
                allRows.value = page.props.criteria?.data ?? [];
            },
            onFinish: () => { loadingAll.value = false; },
        });
    }
}

function selectedColumnKeys() {
    return EVALUATION_TABLE_COLUMNS
        .map((c) => c.key)
        .filter((key) => columns.value[key]);
}

function runExport() {
    const list = scope.value === 'all' && allRows.value ? allRows.value : props.rows;
    if (!list.length) {
        toast.warning('Không có dữ liệu để xuất.');
        return;
    }

    const options = { columns: selectedColumnKeys() };
    if (scope.value === 'all') {
        options.scopeLabel = 'Toàn bộ tiêu chí (bỏ qua bộ lọc)';
    }

    try {
        if (format.value === 'csv') {
            exportEvaluationCsv(list, options);
        } else {
            exportEvaluationWorkbook(list, props.filters, props.summary, options);
        }
        toast.success('Đã xuất file.');
        close();
    } catch {
        toast.error('Không xuất được file. Thử lại.');
    }
}
</script>

<template>
  <Modal
    :show="show"
    title="Xuất dữ liệu tiêu chí đánh giá"
    max-width="max-w-2xl"
    @close="close"
  >
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
      <!-- Left: scope + format -->
      <div class="space-y-4">
        <div>
          <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">
            Phạm vi
          </p>
          <div class="space-y-2">
            <button
              type="button"
              class="w-full rounded-xl border p-3 text-left transition"
              :class="scope === 'filtered' ? 'border-brand bg-brand/5' : 'border-slate-200 dark:border-slate-700'"
              @click="onScopeChange('filtered')"
            >
              <span class="flex items-center justify-between text-sm font-medium text-slate-800 dark:text-slate-100">
                Theo bộ lọc hiện tại
                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] tabular-nums text-slate-600 dark:bg-slate-800">{{ filteredCount }} dòng</span>
              </span>
              <span class="mt-1 block text-xs text-slate-500 dark:text-slate-400">
                Xuất đúng danh sách đang hiển thị (đã áp dụng tìm kiếm/lọc).
              </span>
            </button>

            <button
              v-if="hasActiveFilters"
              type="button"
              class="w-full rounded-xl border p-3 text-left transition"
              :class="scope === 'all' ? 'border-brand bg-brand/5' : 'border-slate-200 dark:border-slate-700'"
              @click="onScopeChange('all')"
            >
              <span class="flex items-center justify-between text-sm font-medium text-slate-800 dark:text-slate-100">
                Toàn bộ (bỏ lọc)
                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] tabular-nums text-slate-600 dark:bg-slate-800">
                  <AppIcon
                    v-if="loadingAll"
                    name="refresh"
                    :size="11"
                    class="inline animate-spin"
                  />
                  <template v-else>{{ allCount ?? '…' }} dòng</template>
                </span>
              </span>
              <span class="mt-1 block text-xs text-slate-500 dark:text-slate-400">
                Bỏ qua mọi bộ lọc/tìm kiếm đang áp dụng, xuất toàn bộ tiêu chí trong phạm vi được xem.
              </span>
            </button>
          </div>
        </div>

        <div>
          <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">
            Định dạng
          </p>
          <div class="space-y-2">
            <button
              v-for="opt in EXPORT_FORMATS"
              :key="opt.value"
              type="button"
              class="w-full rounded-xl border p-3 text-left transition"
              :class="format === opt.value ? 'border-brand bg-brand/5' : 'border-slate-200 dark:border-slate-700'"
              @click="format = opt.value"
            >
              <span class="text-sm font-medium text-slate-800 dark:text-slate-100">{{ opt.label }}</span>
              <span class="mt-1 block text-xs text-slate-500 dark:text-slate-400">{{ opt.description }}</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Right: column checklist -->
      <div>
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">
          Cột xuất
        </p>
        <div class="space-y-1.5 rounded-xl border border-slate-200 p-3 dark:border-slate-700">
          <label class="flex items-center gap-2 text-sm text-slate-400">
            <input
              type="checkbox"
              checked
              disabled
            >
            Cột lõi (Mã, Tên, Loại, Thang điểm…)
          </label>
          <label
            v-for="col in EVALUATION_TABLE_COLUMNS"
            :key="col.key"
            class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200"
          >
            <input
              type="checkbox"
              :checked="!!columns[col.key]"
              @change="toggleColumn(col.key)"
            >
            {{ col.label }}
          </label>
        </div>
      </div>
    </div>

    <div class="mt-5 flex items-center justify-between gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
      <p class="text-sm text-slate-500 dark:text-slate-400">
        Sẽ xuất <strong class="text-slate-800 dark:text-slate-100">{{ exportRowCount }}</strong> tiêu chí, định dạng <strong>.{{ format }}</strong>.
      </p>
      <div class="flex gap-2">
        <button
          type="button"
          class="btn-ghost text-sm"
          @click="close"
        >
          Huỷ
        </button>
        <button
          type="button"
          class="btn-primary text-sm disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="loadingAll"
          @click="runExport"
        >
          <AppIcon
            name="export"
            :size="14"
          /> Xuất file
        </button>
      </div>
    </div>
  </Modal>
</template>
