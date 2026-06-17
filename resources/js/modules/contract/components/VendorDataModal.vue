<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Modal from '@/Components/Ui/Modal.vue';
import {
    downloadVendorTemplate,
    parseVendorFile,
    createPreviewRows,
    revalidateVendorRow,
    vendorRowToPayload,
    exportVendorWorkbook,
    exportPreviewErrorRows,
    fetchAndExportAll,
    fetchImportLogs,
    reconcileVendors,
    exportReconcileWorkbook,
} from '@/modules/contract/composables/useVendorData.js';
import { useToast } from '@/shared/composables/useToast';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    rows: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
    initialTab: { type: String, default: 'import' },
    activeFilters: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update:modelValue', 'fix']);

const toast = useToast();
const tab = ref('import');
const importStep = ref('guide');
const previewTab = ref('valid');
const parsing = ref(false);
const importing = ref(false);
const exportingAll = ref(false);
const overwrite = ref(false);
const previewRows = ref([]);
const parseErrors = ref([]);
const fileInputRef = ref(null);
const importLogs = ref([]);
const logsLoading = ref(false);
const exportScope = ref('page');

const validRows = computed(() => previewRows.value.filter((r) => r._valid));
const errorRows = computed(() => previewRows.value.filter((r) => !r._valid));
const displayedImportRows = computed(() =>
    previewTab.value === 'valid' ? validRows.value : errorRows.value,
);
const canSubmit = computed(() => validRows.value.length > 0 && !importing.value && !parsing.value);
const isDirty = computed(() => previewRows.value.length > 0);
const reconcile = computed(() => reconcileVendors(props.rows));

watch(() => props.modelValue, (v) => {
    if (v) {
        tab.value = props.initialTab || 'import';
        if (tab.value === 'import') loadImportLogs();
    }
});

watch(() => props.initialTab, (v) => {
    if (props.modelValue) tab.value = v || 'import';
});

watch(tab, (v) => {
    if (v === 'import') loadImportLogs();
});

onMounted(() => {
    if (props.modelValue && tab.value === 'import') loadImportLogs();
});

function reset() {
    importStep.value = 'guide';
    previewTab.value = 'valid';
    previewRows.value = [];
    parseErrors.value = [];
    parsing.value = false;
    importing.value = false;
    overwrite.value = false;
    if (fileInputRef.value) fileInputRef.value.value = '';
}

function close() {
    reset();
    emit('update:modelValue', false);
}

async function loadImportLogs() {
    if (logsLoading.value) return;
    logsLoading.value = true;
    try {
        importLogs.value = await fetchImportLogs();
    } catch {
        // non-critical
    } finally {
        logsLoading.value = false;
    }
}

async function onFile(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    parsing.value = true;
    previewRows.value = [];
    parseErrors.value = [];
    try {
        const { rows, errors } = await parseVendorFile(file);
        parseErrors.value = errors;
        if (rows.length) {
            previewRows.value = createPreviewRows(rows);
            importStep.value = 'preview';
            previewTab.value = errorRows.value.length > 0 ? 'error' : 'valid';
        } else if (!errors.length) {
            parseErrors.value = ['Không đọc được dữ liệu. Hãy dùng file mẫu chính thức.'];
        }
        if (errors.length) toast.error(`File có ${errors.length} cảnh báo — xem danh sách bên dưới.`);
    } catch {
        parseErrors.value = ['Không đọc được file Excel. Kiểm tra định dạng file.'];
    } finally {
        parsing.value = false;
    }
}

function onCellBlur() {
    previewRows.value.forEach((row) => revalidateVendorRow(row, previewRows.value));
}

function resetImport() {
    importStep.value = 'guide';
    previewRows.value = [];
    parseErrors.value = [];
    if (fileInputRef.value) fileInputRef.value.value = '';
}

function submitImport() {
    if (!props.canManage || !canSubmit.value) return;
    const payload = validRows.value.map(vendorRowToPayload);
    importing.value = true;
    router.post(route('contracts.vendors.import'), { rows: payload, overwrite: overwrite.value }, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success(`Đã nhập ${payload.length} nhà cung cấp${overwrite.value ? ' (có ghi đè)' : ''}.`);
            close();
        },
        onError: () => {
            toast.error('Nhập thất bại — kiểm tra dữ liệu và thử lại.');
        },
        onFinish: () => {
            importing.value = false;
        },
    });
}

function downloadErrorRows() {
    exportPreviewErrorRows(errorRows.value);
}

function runExportPage() {
    if (!props.rows.length) {
        toast.warning('Không có dữ liệu để xuất.');
        return;
    }
    exportVendorWorkbook(props.rows);
    toast.success(`Đã xuất ${props.rows.length} nhà cung cấp (trang hiện tại).`);
}

async function runExportAll() {
    exportingAll.value = true;
    try {
        const count = await fetchAndExportAll(props.activeFilters);
        if (count === 0) toast.warning('Không có dữ liệu để xuất.');
        else toast.success(`Đã xuất ${count} nhà cung cấp (toàn bộ).`);
    } catch {
        toast.error('Xuất toàn bộ thất bại. Thử lại sau.');
    } finally {
        exportingAll.value = false;
    }
}

function runExport() {
    if (exportScope.value === 'all') runExportAll();
    else runExportPage();
}

const RECONCILE_ICON = { error: 'alert', warning: 'warning', info: 'info' };
const RECONCILE_COLOR = { error: 'text-rose-600', warning: 'text-amber-600', info: 'text-sky-600' };
const RECONCILE_BORDER = { error: 'border-rose-200', warning: 'border-amber-200', info: 'border-sky-200' };
const RECONCILE_BG = { error: 'bg-rose-50', warning: 'bg-amber-50', info: 'bg-sky-50' };

function fixIssue(issue) {
    emit('fix', issue);
    close();
}

function exportReconcileReport() {
    if (!reconcile.value.issues.length) {
        toast.info('Không có vấn đề nào để xuất.');
        return;
    }
    exportReconcileWorkbook(reconcile.value);
    toast.success('Đã xuất báo cáo đối soát.');
}
</script>

<template>
  <Modal
    :show="modelValue"
    title="Dữ liệu nhà cung cấp"
    max-width="max-w-3xl"
    :dirty="isDirty"
    close-confirm-title="Huỷ nhập liệu?"
    close-confirm-message="Dữ liệu đã tải lên chưa được nhập. Bạn có chắc muốn thoát?"
    @close="close"
  >
    <div class="mb-5 flex gap-1 border-b border-slate-200 pb-0">
      <button
        v-for="t in [
          { key: 'import', label: 'Nhập', icon: 'upload' },
          { key: 'export', label: 'Xuất', icon: 'export' },
          { key: 'reconcile', label: 'Đối soát', icon: 'check-list' },
        ]"
        :key="t.key"
        type="button"
        class="-mb-px flex items-center gap-1.5 rounded-t-md border-b-2 px-3 py-2 text-xs font-medium transition-colors"
        :class="tab === t.key
          ? 'border-brand text-brand'
          : 'border-transparent text-slate-500 hover:text-slate-700'"
        @click="tab = t.key"
      >
        <AppIcon
          :name="t.icon"
          :size="13"
        />
        {{ t.label }}
        <span
          v-if="t.key === 'reconcile' && reconcile.summary.errors > 0"
          class="ml-0.5 rounded-full bg-rose-100 px-1.5 py-0.5 text-[10px] font-semibold text-rose-600"
        >
          {{ reconcile.summary.errors }}
        </span>
      </button>
    </div>

    <div
      v-if="tab === 'import'"
      class="space-y-4"
    >
      <template v-if="importStep === 'guide'">
        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
          <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
            Quy trình nhập
          </p>
          <ol class="list-decimal space-y-1.5 pl-4 text-sm text-slate-700">
            <li>Tải file mẫu (.xlsx) — đọc sheet <strong>Huong dan</strong> trước.</li>
            <li>Điền sheet <strong>Nhap lieu</strong> từ dòng 8, tối đa <strong>200 dòng</strong>.</li>
            <li>Cột bắt buộc (*): <strong>Tên NCC</strong>.</li>
            <li>Preview → sửa lỗi inline → Nhập (hoặc xuất dòng lỗi sửa offline).</li>
          </ol>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <button
            type="button"
            class="btn-ghost inline-flex h-9 items-center gap-1.5 px-3 text-xs"
            @click="downloadVendorTemplate"
          >
            <AppIcon
              name="download"
              :size="14"
            />
            Tải file mẫu (.xlsx)
          </button>
          <label class="btn-primary inline-flex h-9 cursor-pointer items-center gap-1.5 px-3 text-xs">
            <AppIcon
              name="upload"
              :size="14"
            />
            {{ parsing ? 'Đang đọc…' : 'Chọn file' }}
            <input
              ref="fileInputRef"
              type="file"
              accept=".xlsx,.xls,.csv"
              class="sr-only"
              :disabled="parsing"
              @change="onFile"
            >
          </label>
        </div>

        <label
          v-if="canManage"
          class="flex cursor-pointer items-center gap-2 text-sm text-slate-600"
        >
          <input
            v-model="overwrite"
            type="checkbox"
            class="h-4 w-4 rounded border-slate-300 text-brand focus:ring-brand"
          >
          <span>Ghi đè khi trùng Mã NCC / MST / Tên</span>
        </label>

        <ul
          v-if="parseErrors.length"
          class="rounded-lg border border-rose-200 bg-rose-50 p-3"
        >
          <li
            v-for="(err, i) in parseErrors"
            :key="i"
            class="flex items-start gap-1.5 text-xs text-rose-700"
          >
            <AppIcon
              name="alert"
              :size="12"
              class="mt-0.5 shrink-0"
            />
            {{ err }}
          </li>
        </ul>

        <div
          v-if="importLogs.length || logsLoading"
          class="rounded-lg border border-slate-100 bg-slate-50 p-3"
        >
          <p class="mb-2 text-[10px] font-semibold uppercase tracking-wide text-slate-400">
            Lịch sử nhập gần đây
          </p>
          <div
            v-if="logsLoading"
            class="text-xs text-slate-400"
          >
            Đang tải…
          </div>
          <ul
            v-else
            class="space-y-1"
          >
            <li
              v-for="log in importLogs"
              :key="log.id"
              class="flex items-center justify-between text-xs text-slate-600"
            >
              <span class="truncate">
                <span class="font-medium">{{ log.user }}</span>
                nhập <span class="font-medium text-emerald-600">{{ log.imported_count }}</span>
                <template v-if="log.overwritten_count > 0">
                  , ghi đè <span class="font-medium text-amber-600">{{ log.overwritten_count }}</span>
                </template>
              </span>
              <span class="ml-2 shrink-0 text-slate-400">{{ log.created_at_human }}</span>
            </li>
          </ul>
        </div>
      </template>

      <template v-else>
        <div class="flex items-center justify-between">
          <div class="flex gap-1">
            <button
              type="button"
              class="rounded-md px-2.5 py-1 text-xs font-medium transition-colors"
              :class="previewTab === 'valid'
                ? 'bg-emerald-100 text-emerald-700'
                : 'text-slate-500 hover:bg-slate-100'"
              @click="previewTab = 'valid'"
            >
              Hợp lệ
              <span class="ml-1 rounded-full bg-emerald-200 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-800">
                {{ validRows.length }}
              </span>
            </button>
            <button
              type="button"
              class="rounded-md px-2.5 py-1 text-xs font-medium transition-colors"
              :class="previewTab === 'error'
                ? 'bg-rose-100 text-rose-700'
                : 'text-slate-500 hover:bg-slate-100'"
              @click="previewTab = 'error'"
            >
              Lỗi
              <span class="ml-1 rounded-full bg-rose-200 px-1.5 py-0.5 text-[10px] font-semibold text-rose-800">
                {{ errorRows.length }}
              </span>
            </button>
          </div>
          <button
            type="button"
            class="text-xs text-slate-400 hover:text-slate-600"
            @click="resetImport"
          >
            ← Chọn file khác
          </button>
        </div>

        <div
          v-if="displayedImportRows.length"
          class="max-h-64 overflow-auto rounded-lg border border-slate-200"
        >
          <table class="min-w-full text-xs">
            <thead class="sticky top-0 bg-slate-100">
              <tr>
                <th class="px-2 py-2 text-left font-medium text-slate-600">
                  #
                </th>
                <th class="px-2 py-2 text-left font-medium text-slate-600">
                  Tên NCC
                </th>
                <th class="px-2 py-2 text-left font-medium text-slate-600">
                  Mã NCC
                </th>
                <th class="px-2 py-2 text-left font-medium text-slate-600">
                  MST
                </th>
                <th class="px-2 py-2 text-left font-medium text-slate-600">
                  Email
                </th>
                <th
                  v-if="previewTab === 'error'"
                  class="px-2 py-2 text-left font-medium text-rose-600"
                >
                  Lỗi
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in displayedImportRows"
                :key="row._rowIdx"
                class="border-t border-slate-100"
                :class="row._valid ? 'bg-white' : 'bg-rose-50/60'"
              >
                <td class="px-2 py-2 text-slate-400">
                  {{ row._rowIdx }}
                </td>
                <td class="px-2 py-2">
                  <input
                    v-if="previewTab === 'error'"
                    v-model="row.name"
                    class="input h-7 w-full min-w-[100px] text-xs"
                    @blur="onCellBlur"
                  >
                  <span v-else>{{ row.name }}</span>
                </td>
                <td class="px-2 py-2">
                  <input
                    v-if="previewTab === 'error'"
                    v-model="row.code"
                    class="input h-7 w-full min-w-[80px] text-xs"
                    @blur="onCellBlur"
                  >
                  <span v-else>{{ row.code || 'Tự sinh' }}</span>
                </td>
                <td class="px-2 py-2">
                  <input
                    v-if="previewTab === 'error'"
                    v-model="row.tax_code"
                    class="input h-7 w-full min-w-[80px] text-xs"
                    @blur="onCellBlur"
                  >
                  <span v-else>{{ row.tax_code }}</span>
                </td>
                <td class="px-2 py-2">
                  <input
                    v-if="previewTab === 'error'"
                    v-model="row.email"
                    class="input h-7 w-full min-w-[120px] text-xs"
                    @blur="onCellBlur"
                  >
                  <span v-else>{{ row.email }}</span>
                </td>
                <td
                  v-if="previewTab === 'error'"
                  class="px-2 py-2"
                >
                  <ul class="space-y-0.5">
                    <li
                      v-for="(err, ei) in row._errors"
                      :key="ei"
                      class="text-rose-600"
                    >
                      {{ err }}
                    </li>
                  </ul>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div
          v-else
          class="rounded-lg border border-dashed border-emerald-200 bg-emerald-50 py-6 text-center text-sm text-emerald-600"
        >
          Không có dòng {{ previewTab === 'error' ? 'lỗi' : 'hợp lệ' }}.
        </div>

        <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3">
          <button
            v-if="errorRows.length"
            type="button"
            class="btn-ghost inline-flex h-8 items-center gap-1.5 px-3 text-xs"
            @click="downloadErrorRows"
          >
            <AppIcon
              name="export"
              :size="13"
            />
            Xuất {{ errorRows.length }} dòng lỗi
          </button>
          <div class="ml-auto flex items-center gap-2">
            <span class="text-xs text-slate-500">
              {{ validRows.length }} hợp lệ · {{ errorRows.length }} lỗi
            </span>
            <button
              v-if="canManage && canSubmit"
              type="button"
              class="btn-primary inline-flex h-9 items-center gap-1.5 px-4 text-xs"
              :disabled="importing"
              @click="submitImport"
            >
              {{ importing ? 'Đang nhập…' : `Nhập ${validRows.length} bản ghi` }}
            </button>
            <p
              v-else-if="!canManage"
              class="text-xs italic text-slate-400"
            >
              Bạn không có quyền nhập.
            </p>
          </div>
        </div>
      </template>
    </div>

    <div
      v-else-if="tab === 'export'"
      class="space-y-4"
    >
      <div class="space-y-2">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
          Phạm vi xuất
        </p>
        <label
          class="flex cursor-pointer items-start gap-2.5 rounded-lg border p-3 transition-colors"
          :class="exportScope === 'page' ? 'border-brand/40 bg-brand/5' : 'border-slate-200 hover:bg-slate-50'"
        >
          <input
            v-model="exportScope"
            type="radio"
            value="page"
            class="mt-0.5 h-4 w-4 text-brand"
          >
          <div>
            <span class="block text-sm font-medium text-slate-700">Danh sách hiện tại</span>
            <span class="text-xs text-slate-400">{{ rows.length }} bản ghi theo bộ lọc</span>
          </div>
        </label>
        <label
          class="flex cursor-pointer items-start gap-2.5 rounded-lg border p-3 transition-colors"
          :class="exportScope === 'all' ? 'border-brand/40 bg-brand/5' : 'border-slate-200 hover:bg-slate-50'"
        >
          <input
            v-model="exportScope"
            type="radio"
            value="all"
            class="mt-0.5 h-4 w-4 text-brand"
          >
          <div>
            <span class="block text-sm font-medium text-slate-700">Toàn bộ theo bộ lọc</span>
            <span class="text-xs text-slate-400">Tối đa 2.000 bản ghi · Excel styled</span>
          </div>
        </label>
      </div>
      <button
        type="button"
        class="btn-primary inline-flex h-9 items-center gap-1.5 px-4 text-xs"
        :disabled="exportingAll || (!rows.length && exportScope === 'page')"
        @click="runExport"
      >
        {{ exportingAll ? 'Đang tải…' : 'Xuất Excel (.xlsx)' }}
      </button>
    </div>

    <div
      v-else
      class="space-y-4"
    >
      <div class="grid grid-cols-4 gap-2 rounded-lg border border-slate-200 bg-slate-50 p-3">
        <div class="text-center">
          <p class="text-[10px] uppercase tracking-wide text-slate-400">
            Tổng
          </p>
          <p class="font-display text-xl font-semibold text-slate-700">
            {{ reconcile.summary.total }}
          </p>
        </div>
        <div class="text-center">
          <p class="text-[10px] uppercase tracking-wide text-rose-500">
            Lỗi
          </p>
          <p class="font-display text-xl font-semibold text-rose-600">
            {{ reconcile.summary.errors }}
          </p>
        </div>
        <div class="text-center">
          <p class="text-[10px] uppercase tracking-wide text-amber-500">
            Cảnh báo
          </p>
          <p class="font-display text-xl font-semibold text-amber-600">
            {{ reconcile.summary.warnings }}
          </p>
        </div>
        <div class="text-center">
          <p class="text-[10px] uppercase tracking-wide text-sky-500">
            Gợi ý
          </p>
          <p class="font-display text-xl font-semibold text-sky-600">
            {{ reconcile.summary.info }}
          </p>
        </div>
      </div>

      <div
        v-if="!reconcile.issues.length"
        class="flex flex-col items-center justify-center rounded-lg border border-dashed border-emerald-200 bg-emerald-50 py-8 text-center"
      >
        <AppIcon
          name="done-circle"
          :size="28"
          class="mb-2 text-emerald-500"
        />
        <p class="text-sm font-medium text-emerald-700">
          Không có vấn đề nào được phát hiện.
        </p>
      </div>

      <ul
        v-else
        class="max-h-72 space-y-1.5 overflow-y-auto"
      >
        <li
          v-for="(issue, i) in reconcile.issues"
          :key="i"
          class="flex items-start justify-between gap-2 rounded-lg border px-3 py-2.5 text-xs"
          :class="[RECONCILE_BG[issue.level], RECONCILE_BORDER[issue.level]]"
        >
          <div class="flex items-start gap-2">
            <AppIcon
              :name="RECONCILE_ICON[issue.level]"
              :size="13"
              class="mt-0.5 shrink-0"
              :class="RECONCILE_COLOR[issue.level]"
            />
            <span :class="RECONCILE_COLOR[issue.level]">{{ issue.message }}</span>
          </div>
          <button
            v-if="issue.vendorId"
            type="button"
            class="shrink-0 rounded px-2 py-0.5 text-[10px] font-medium text-slate-500 hover:bg-white hover:text-brand"
            @click="fixIssue(issue)"
          >
            Sửa →
          </button>
        </li>
      </ul>

      <div class="flex justify-end border-t border-slate-100 pt-3">
        <button
          type="button"
          class="btn-ghost inline-flex h-8 items-center gap-1.5 px-3 text-xs"
          :disabled="!reconcile.issues.length"
          @click="exportReconcileReport"
        >
          <AppIcon
            name="export"
            :size="13"
          />
          Xuất báo cáo đối soát
        </button>
      </div>
    </div>
  </Modal>
</template>
