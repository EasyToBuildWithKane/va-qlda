<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import AppIcon from '@/Components/AppIcon.vue';
import Modal from '@/Components/Ui/Modal.vue';
import { useToast } from '@/shared/composables/useToast';
import { datetime } from '@/composables/useFormat';
import { TEMPLATE_EXPORT_COLUMN_OPTIONS } from '@/modules/evaluation-template/config/columns.js';
import {
    downloadTemplateImportFile,
    parseTemplateImportFile,
    validateTemplateRows,
    templateRowToPayload,
} from '@/modules/evaluation-template/composables/useEvaluationTemplateImport.js';
import {
    exportTemplateWorkbook,
    exportTemplateCsv,
    EXPORT_FORMATS,
} from '@/modules/evaluation-template/composables/useEvaluationTemplateExport.js';

const props = defineProps({
    show: { type: Boolean, default: false },
    initialTab: { type: String, default: 'import' },
    canManage: { type: Boolean, default: false },
    positions: { type: Array, default: () => [] },
    criteriaOptions: { type: Array, default: () => [] },
    rows: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
    visibleCols: { type: Object, default: () => ({}) },
    exportLogs: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'logs-updated']);
const toast = useToast();

const tab = ref('import');

// Import
const importStep = ref('guide');
const parsing = ref(false);
const importing = ref(false);
const parseErrors = ref([]);
const previewRows = ref([]);
const previewTab = ref('valid');

// Export
const scope = ref('filtered');
const format = ref('xlsx');
const columns = ref({});
const loadingAll = ref(false);
const allRows = ref(null);
const exporting = ref(false);
const localLogs = ref([]);

const validRows = computed(() => previewRows.value.filter((r) => r._valid));
const invalidRows = computed(() => previewRows.value.filter((r) => !r._valid));
const hasActiveFilters = computed(() => Object.values(props.filters || {}).some((v) => !!v));
const filteredCount = computed(() => props.rows.length);
const allCount = computed(() => allRows.value?.length ?? null);
const exportRowCount = computed(() => (
    scope.value === 'all' ? (allCount.value ?? filteredCount.value) : filteredCount.value
));
const displayLogs = computed(() => (localLogs.value.length ? localLogs.value : props.exportLogs));

const tabs = computed(() => {
    const list = [
        { key: 'export', label: 'Xuất' },
        { key: 'history', label: 'Lịch sử export' },
    ];
    if (props.canManage) {
        list.unshift({ key: 'import', label: 'Nhập' });
    }
    return list;
});

function resetImport() {
    importStep.value = 'guide';
    parsing.value = false;
    importing.value = false;
    parseErrors.value = [];
    previewRows.value = [];
    previewTab.value = 'valid';
}

function resetExport() {
    scope.value = 'filtered';
    format.value = 'xlsx';
    const cols = {};
    TEMPLATE_EXPORT_COLUMN_OPTIONS.forEach((c) => {
        cols[c.key] = c.core || !!props.visibleCols?.[c.key];
    });
    columns.value = cols;
    allRows.value = null;
    loadingAll.value = false;
    exporting.value = false;
    localLogs.value = [...(props.exportLogs || [])];
}

function resetAll() {
    const preferred = props.initialTab || (props.canManage ? 'import' : 'export');
    tab.value = tabs.value.some((t) => t.key === preferred) ? preferred : tabs.value[0]?.key || 'export';
    resetImport();
    resetExport();
}

watch(() => props.show, (open) => {
    if (open) resetAll();
});

watch(() => props.exportLogs, (logs) => {
    if (!exporting.value) localLogs.value = [...(logs || [])];
});

function close() {
    emit('close');
}

function downloadTemplate() {
    downloadTemplateImportFile({
        positions: props.positions,
        criteriaOptions: props.criteriaOptions,
    });
}

async function onFileChange(e) {
    const file = e.target.files?.[0];
    e.target.value = '';
    if (!file) return;

    parsing.value = true;
    parseErrors.value = [];
    try {
        const result = await parseTemplateImportFile(file, {
            positions: props.positions,
            criteriaOptions: props.criteriaOptions,
        });
        parseErrors.value = result.errors || [];
        previewRows.value = validateTemplateRows(result.rows || []);
        if (!previewRows.value.length && !parseErrors.value.length) {
            parseErrors.value = ['Không có dòng dữ liệu hợp lệ trong file.'];
            return;
        }
        importStep.value = 'preview';
        previewTab.value = validRows.value.length ? 'valid' : 'invalid';
    } catch {
        toast.error('Không đọc được file. Kiểm tra định dạng .xlsx.');
    } finally {
        parsing.value = false;
    }
}

function submitImport() {
    if (!validRows.value.length) {
        toast.warning('Không có dòng hợp lệ để nhập.');
        return;
    }
    importing.value = true;
    router.post(route('workspace.evaluation-templates.import'), {
        rows: validRows.value.map(templateRowToPayload),
    }, {
        preserveScroll: true,
        onSuccess: () => close(),
        onFinish: () => { importing.value = false; },
    });
}

function toggleColumn(key) {
    const opt = TEMPLATE_EXPORT_COLUMN_OPTIONS.find((c) => c.key === key);
    if (opt?.core) return;
    columns.value = { ...columns.value, [key]: !columns.value[key] };
}

function onScopeChange(value) {
    scope.value = value;
    if (value === 'all' && allRows.value === null && !loadingAll.value) {
        loadingAll.value = true;
        router.get(route('workspace.evaluation-templates.index'), {}, {
            only: ['templates'],
            preserveState: true,
            preserveScroll: true,
            onSuccess: (page) => {
                allRows.value = page.props.templates?.data ?? [];
            },
            onFinish: () => { loadingAll.value = false; },
        });
    }
}

function selectedColumnKeys() {
    return TEMPLATE_EXPORT_COLUMN_OPTIONS
        .map((c) => c.key)
        .filter((key) => columns.value[key]);
}

async function recordExportLog(result) {
    try {
        const { data } = await axios.post(route('workspace.evaluation-templates.export-logs.store'), {
            scope: scope.value,
            format: format.value,
            row_count: result.rowCount,
            columns: selectedColumnKeys(),
            filters: props.filters,
            filename: result.filename,
        }, {
            headers: { Accept: 'application/json' },
        });
        if (data?.log) {
            localLogs.value = [data.log, ...localLogs.value].slice(0, 50);
            emit('logs-updated', localLogs.value);
        }
    } catch {
        // Export file đã thành công — lịch sử là phụ.
    }
}

async function runExport() {
    const list = scope.value === 'all' && allRows.value ? allRows.value : props.rows;
    if (!list.length) {
        toast.warning('Không có dữ liệu để xuất.');
        return;
    }

    const options = { columns: selectedColumnKeys() };
    if (scope.value === 'all') {
        options.scopeLabel = 'Toàn bộ mẫu đánh giá (bỏ qua bộ lọc)';
    }

    exporting.value = true;
    try {
        const result = format.value === 'csv'
            ? exportTemplateCsv(list, options)
            : exportTemplateWorkbook(list, props.filters, props.summary, options);
        await recordExportLog(result);
        toast.success('Đã xuất file.');
        tab.value = 'history';
    } catch {
        toast.error('Không xuất được file. Thử lại.');
    } finally {
        exporting.value = false;
    }
}

function scopeLabel(s) {
    return s === 'all' ? 'Toàn bộ' : 'Theo lọc';
}
</script>

<template>
  <Modal
    :show="show"
    title="Dữ liệu mẫu đánh giá"
    max-width="max-w-5xl"
    @close="close"
  >
    <div class="mb-4 flex flex-wrap gap-2 border-b border-slate-100 pb-3">
      <button
        v-for="t in tabs"
        :key="t.key"
        type="button"
        class="rounded-lg px-3 py-1.5 text-sm"
        :class="tab === t.key ? 'bg-brand/10 font-semibold text-brand' : 'text-slate-500'"
        @click="tab = t.key"
      >
        {{ t.label }}
        <span
          v-if="t.key === 'history'"
          class="ml-1 rounded-full bg-slate-100 px-1.5 text-[11px] tabular-nums text-slate-600"
        >{{ displayLogs.length }}</span>
      </button>
    </div>

    <!-- Nhập -->
    <div
      v-if="tab === 'import'"
      class="space-y-4"
    >
      <div
        v-if="importStep === 'guide'"
        class="space-y-4"
      >
        <ol class="list-decimal space-y-2 pl-5 text-sm text-slate-600">
          <li>Tải file mẫu Excel (sheet tham chiếu vị trí & tiêu chí).</li>
          <li>Điền tên mẫu, vị trí, danh sách mã tiêu chí (cách nhau bởi ;).</li>
          <li>Chọn file đã điền để xem trước, rồi nhập tối đa 200 dòng.</li>
        </ol>
        <div class="flex flex-wrap gap-2">
          <button
            type="button"
            class="btn-ghost h-10 gap-1.5 text-sm"
            @click="downloadTemplate"
          >
            <AppIcon
              name="download"
              :size="15"
            />
            Tải file mẫu (.xlsx)
          </button>
          <label class="btn-primary inline-flex h-10 cursor-pointer items-center gap-1.5 px-3 text-sm">
            <AppIcon
              name="upload"
              :size="15"
            />
            {{ parsing ? 'Đang đọc…' : 'Chọn file' }}
            <input
              type="file"
              accept=".xlsx,.xls,.csv"
              class="hidden"
              :disabled="parsing"
              @change="onFileChange"
            >
          </label>
        </div>
        <ul
          v-if="parseErrors.length"
          class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"
        >
          <li
            v-for="(err, i) in parseErrors"
            :key="i"
          >
            {{ err }}
          </li>
        </ul>
      </div>

      <div
        v-else
        class="space-y-4"
      >
        <div class="flex flex-wrap items-center gap-2">
          <button
            type="button"
            class="rounded-lg px-3 py-1.5 text-sm"
            :class="previewTab === 'valid' ? 'bg-emerald-50 font-semibold text-emerald-700' : 'text-slate-500'"
            @click="previewTab = 'valid'"
          >
            Hợp lệ ({{ validRows.length }})
          </button>
          <button
            type="button"
            class="rounded-lg px-3 py-1.5 text-sm"
            :class="previewTab === 'invalid' ? 'bg-rose-50 font-semibold text-rose-700' : 'text-slate-500'"
            @click="previewTab = 'invalid'"
          >
            Lỗi ({{ invalidRows.length }})
          </button>
          <button
            type="button"
            class="btn-ghost ml-auto h-9 text-sm"
            @click="importStep = 'guide'"
          >
            Chọn file khác
          </button>
        </div>
        <div class="max-h-80 overflow-auto rounded-xl border border-slate-200">
          <table class="min-w-full text-sm">
            <thead class="sticky top-0 bg-slate-50 text-left text-[11px] uppercase text-slate-500">
              <tr>
                <th class="px-3 py-2">
                  Tên mẫu
                </th>
                <th class="px-3 py-2">
                  Mã
                </th>
                <th class="px-3 py-2">
                  Vị trí
                </th>
                <th class="px-3 py-2">
                  Tiêu chí
                </th>
                <th
                  v-if="previewTab === 'invalid'"
                  class="px-3 py-2"
                >
                  Lỗi
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(row, i) in (previewTab === 'valid' ? validRows : invalidRows)"
                :key="i"
                class="border-t border-slate-100"
              >
                <td class="px-3 py-2 font-medium text-slate-800">
                  {{ row.name }}
                </td>
                <td class="px-3 py-2 font-mono text-xs text-slate-500">
                  {{ row.template_code || 'Tự sinh' }}
                </td>
                <td class="px-3 py-2 text-slate-600">
                  {{ row.position_name || 'Chưa cập nhật' }}
                </td>
                <td class="px-3 py-2 text-slate-600">
                  {{ (row.criteria || []).length }}
                </td>
                <td
                  v-if="previewTab === 'invalid'"
                  class="px-3 py-2 text-xs text-rose-600"
                >
                  {{ (row._errors || []).join('; ') }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
          <button
            type="button"
            class="btn-ghost h-9 text-sm"
            @click="close"
          >
            Huỷ
          </button>
          <button
            type="button"
            class="btn-primary h-9 text-sm disabled:opacity-50"
            :disabled="importing || !validRows.length"
            @click="submitImport"
          >
            {{ importing ? 'Đang nhập…' : `Nhập ${validRows.length} mẫu` }}
          </button>
        </div>
      </div>
    </div>

    <!-- Xuất -->
    <div v-else-if="tab === 'export'">
      <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
        <div class="space-y-4">
          <div>
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">
              Phạm vi
            </p>
            <div class="space-y-2">
              <button
                type="button"
                class="w-full rounded-xl border p-3 text-left transition"
                :class="scope === 'filtered' ? 'border-brand bg-brand/5' : 'border-slate-200'"
                @click="onScopeChange('filtered')"
              >
                <span class="flex items-center justify-between text-sm font-medium text-slate-800">
                  Theo bộ lọc hiện tại
                  <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] tabular-nums text-slate-600">{{ filteredCount }} dòng</span>
                </span>
              </button>
              <button
                type="button"
                class="w-full rounded-xl border p-3 text-left transition"
                :class="scope === 'all' ? 'border-brand bg-brand/5' : 'border-slate-200'"
                @click="onScopeChange('all')"
              >
                <span class="flex items-center justify-between text-sm font-medium text-slate-800">
                  Xuất tất cả
                  <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] tabular-nums text-slate-600">
                    <AppIcon
                      v-if="loadingAll"
                      name="refresh"
                      :size="11"
                      class="inline animate-spin"
                    />
                    <template v-else>{{ allCount ?? (hasActiveFilters ? '…' : filteredCount) }} dòng</template>
                  </span>
                </span>
                <span class="mt-1 block text-xs text-slate-500">
                  Bỏ qua bộ lọc, xuất toàn bộ mẫu đánh giá.
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
                :class="format === opt.value ? 'border-brand bg-brand/5' : 'border-slate-200'"
                @click="format = opt.value"
              >
                <span class="text-sm font-medium text-slate-800">{{ opt.label }}</span>
                <span class="mt-1 block text-xs text-slate-500">{{ opt.description }}</span>
              </button>
            </div>
          </div>
        </div>
        <div>
          <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">
            Chọn trường để export
          </p>
          <div class="max-h-72 space-y-1.5 overflow-y-auto rounded-xl border border-slate-200 p-3">
            <label
              v-for="col in TEMPLATE_EXPORT_COLUMN_OPTIONS"
              :key="col.key"
              class="flex items-center gap-2 text-sm"
              :class="col.core ? 'text-slate-400' : 'text-slate-700'"
            >
              <input
                type="checkbox"
                :checked="!!columns[col.key]"
                :disabled="!!col.core"
                @change="toggleColumn(col.key)"
              >
              {{ col.label }}
              <span
                v-if="col.core"
                class="text-[10px] uppercase text-slate-400"
              >bắt buộc</span>
            </label>
          </div>
        </div>
      </div>
      <div class="mt-5 flex items-center justify-between gap-3 border-t border-slate-100 pt-4">
        <p class="text-sm text-slate-500">
          Sẽ xuất <strong class="text-slate-800">{{ exportRowCount }}</strong> mẫu, định dạng <strong>.{{ format }}</strong>.
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
            class="btn-primary gap-1.5 text-sm disabled:opacity-50"
            :disabled="loadingAll || exporting"
            @click="runExport"
          >
            <AppIcon
              name="export"
              :size="14"
            />
            Xuất file
          </button>
        </div>
      </div>
    </div>

    <!-- Lịch sử -->
    <div
      v-else
      class="max-h-80 overflow-y-auto"
    >
      <div
        v-if="!displayLogs.length"
        class="rounded-xl border border-dashed border-slate-200 px-4 py-10 text-center text-sm text-slate-400"
      >
        Chưa có lịch sử xuất mẫu đánh giá.
      </div>
      <ul
        v-else
        class="space-y-2"
      >
        <li
          v-for="log in displayLogs"
          :key="log.id"
          class="rounded-xl border border-slate-200 px-4 py-3"
        >
          <div class="flex flex-wrap items-start justify-between gap-2">
            <div>
              <p class="text-sm font-medium text-slate-800">
                {{ log.exporter_name }} · {{ scopeLabel(log.scope) }} · .{{ log.format }}
              </p>
              <p class="mt-0.5 text-xs text-slate-500">
                {{ log.row_count }} dòng
                <template v-if="log.filename">
                  · {{ log.filename }}
                </template>
              </p>
              <p
                v-if="Array.isArray(log.columns) && log.columns.length"
                class="mt-1 text-[11px] text-slate-400"
              >
                Cột: {{ log.columns.join(', ') }}
              </p>
            </div>
            <time class="shrink-0 text-xs tabular-nums text-slate-400">
              {{ log.created_at ? datetime(log.created_at) : '' }}
            </time>
          </div>
        </li>
      </ul>
    </div>
  </Modal>
</template>
