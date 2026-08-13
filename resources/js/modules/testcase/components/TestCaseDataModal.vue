<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Modal from '@/Components/Ui/Modal.vue';
import { useToast } from '@/shared/composables/useToast';
import { displayOrEmpty } from '@/shared/utils/emptyDisplay';
import {
    downloadTestCaseTemplate,
    parseTestCaseFile,
    validateTestCaseRows,
    testCaseRowToPayload,
} from '@/composables/useTestCaseImport';
import { exportTestCasesWorkbook } from '@/composables/useTestCaseExport';
import { reconcileTestCases } from '@/composables/useTestCaseReconcile';

const props = defineProps({
    show: { type: Boolean, default: false },
    projectId: { type: Number, required: true },
    projectCode: { type: String, default: 'DA' },
    projectName: { type: String, default: '' },
    testCases: { type: Array, default: () => [] },
    testSuites: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
    initialTab: { type: String, default: 'import' },
});

const emit = defineEmits(['close', 'imported']);
const toast = useToast();

const activeTab = ref(props.initialTab);
watch(() => props.initialTab, (v) => { activeTab.value = v; });
watch(() => props.show, (v) => {
    if (!v) {
        parseErrors.value = [];
        previewRows.value = [];
        invalidRows.value = [];
        selectedFile.value = null;
        parsing.value = false;
        importing.value = false;
        step.value = 'guide';
    }
});

const tabs = [
    { key: 'import', label: 'Nhập', icon: 'upload' },
    { key: 'export', label: 'Xuất', icon: 'download' },
    { key: 'reconcile', label: 'Đối soát', icon: 'review-reports' },
];

// ── IMPORT ──────────────────────────────────────────────────────────────────
const step = ref('guide'); // 'guide' | 'preview'
const selectedFile = ref(null);
const parsing = ref(false);
const importing = ref(false);
const parseErrors = ref([]);
const previewRows = ref([]);
const invalidRows = ref([]);
const previewTab = ref('valid');

const validRows = computed(() => previewRows.value.filter((r) => !r._errors?.length));

async function onFileChange(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    selectedFile.value = file;
    parsing.value = true;
    parseErrors.value = [];
    previewRows.value = [];
    invalidRows.value = [];

    try {
        const { rows, errors } = await parseTestCaseFile(file, {
            employees: props.employees,
            suites: props.testSuites,
        });
        parseErrors.value = errors;
        if (rows.length > 0) {
            const { validRows: valid, invalidRows: invalid } = validateTestCaseRows(rows);
            previewRows.value = [...valid, ...invalid];
            invalidRows.value = invalid;
            step.value = 'preview';
        }
    } catch {
        parseErrors.value = ['Không đọc được file. Vui lòng chọn file .xlsx hoặc .xls hợp lệ.'];
    } finally {
        parsing.value = false;
    }
}

function backToGuide() {
    step.value = 'guide';
    selectedFile.value = null;
    parseErrors.value = [];
    previewRows.value = [];
    invalidRows.value = [];
}

function submitImport() {
    if (!validRows.value.length) return;
    importing.value = true;

    const payload = validRows.value.map((r) => testCaseRowToPayload(r, props.projectId));

    router.post(
        route('test-cases.import'),
        { rows: payload },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(`Đã nhập ${payload.length} test case thành công.`);
                emit('imported');
                emit('close');
            },
            onError: () => toast.error('Nhập thất bại. Vui lòng kiểm tra lại dữ liệu.'),
            onFinish: () => { importing.value = false; },
        },
    );
}

// ── EXPORT ──────────────────────────────────────────────────────────────────
const exportScope = ref('all');

function runExport() {
    const list = props.testCases;
    if (!list.length) {
        toast.warning('Không có test case để xuất.');
        return;
    }
    exportTestCasesWorkbook({ list, projectCode: props.projectCode, projectName: props.projectName });
    toast.success('Đã xuất file Excel.');
}

// ── RECONCILE ────────────────────────────────────────────────────────────────
const reconcileResult = computed(() => reconcileTestCases(props.testCases));
const reconcileIssues = computed(() => reconcileResult.value.issues);
const reconcileSummary = computed(() => reconcileResult.value.summary);

const LEVEL_STYLE = {
    error: { badge: 'bg-rose-100 text-rose-700', icon: 'alert', label: 'Lỗi' },
    warning: { badge: 'bg-amber-100 text-amber-700', icon: 'alert-triangle', label: 'Cảnh báo' },
    info: { badge: 'bg-sky-100 text-sky-700', icon: 'info', label: 'Thông tin' },
};
</script>

<template>
  <Modal
    :show="show"
    title="Dữ liệu Test case"
    max-width="max-w-3xl"
    fit-viewport
    @close="emit('close')"
  >
    <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
      <!-- Tab bar -->
      <div class="shrink-0 border-b border-slate-100">
        <nav class="flex gap-1">
          <button
            v-for="tab in tabs"
            :key="tab.key"
            type="button"
            class="inline-flex items-center gap-1.5 border-b-2 px-3 pb-3 pt-2.5 text-xs font-semibold transition-colors"
            :class="activeTab === tab.key
              ? 'border-brand text-brand'
              : 'border-transparent text-slate-500 hover:text-slate-700'"
            @click="activeTab = tab.key"
          >
            <AppIcon
              :name="tab.icon"
              :size="13"
            />
            {{ tab.label }}
            <span
              v-if="tab.key === 'reconcile' && reconcileSummary.errors > 0"
              class="ml-0.5 inline-flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-rose-500 px-1 text-[9px] font-bold text-white"
            >
              {{ reconcileSummary.errors }}
            </span>
          </button>
        </nav>
      </div>

      <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain [-webkit-overflow-scrolling:touch]">
        <!-- ── NHẬP ── -->
        <div
          v-if="activeTab === 'import'"
          class="py-1"
        >
          <!-- Guide step -->
          <div
            v-if="step === 'guide'"
            class="space-y-4"
          >
            <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-4">
              <p class="mb-2 text-xs font-semibold text-slate-700">
                Quy trình nhập:
              </p>
              <ol class="space-y-1.5 pl-4 text-xs text-slate-600">
                <li class="list-decimal">
                  Tải file mẫu bên dưới.
                </li>
                <li class="list-decimal">
                  Điền dữ liệu vào sheet <strong>Nhap lieu</strong> từ dòng 8.
                </li>
                <li class="list-decimal">
                  Chọn file → hệ thống phân tích tự động.
                </li>
                <li class="list-decimal">
                  Xem trước kết quả, sửa lỗi nếu có.
                </li>
                <li class="list-decimal">
                  Bấm <strong>Nhập</strong> để lưu.
                </li>
              </ol>
            </div>

            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-lg border border-brand/30 bg-brand/5 px-4 py-2.5 text-sm font-medium text-brand hover:bg-brand/10"
              @click="downloadTestCaseTemplate"
            >
              <AppIcon
                name="download"
                :size="15"
              />
              Tải file mẫu (.xlsx)
            </button>

            <div>
              <label class="label">Chọn file nhập</label>
              <input
                type="file"
                accept=".xlsx,.xls,.csv"
                class="input w-full"
                :disabled="parsing"
                @change="onFileChange"
              >
              <p
                v-if="parsing"
                class="mt-1 text-xs text-slate-500"
              >
                Đang phân tích file…
              </p>
            </div>

            <div v-if="parseErrors.length">
              <p class="mb-1 text-xs font-semibold text-rose-600">
                Lỗi đọc file:
              </p>
              <ul class="space-y-1">
                <li
                  v-for="(err, i) in parseErrors"
                  :key="i"
                  class="text-xs text-rose-600"
                >
                  · {{ err }}
                </li>
              </ul>
            </div>
          </div>

          <!-- Preview step -->
          <div
            v-else
            class="space-y-4"
          >
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <button
                  type="button"
                  class="btn-ghost h-8 gap-1 px-2 text-xs"
                  @click="backToGuide"
                >
                  <AppIcon
                    name="arrow-left"
                    :size="13"
                  />
                  Quay lại
                </button>
                <span class="text-sm text-slate-600">
                  Hợp lệ: <strong class="text-emerald-600">{{ validRows.length }}</strong>
                  <span
                    v-if="invalidRows.length"
                    class="ml-2 text-rose-600"
                  >
                    Lỗi: <strong>{{ invalidRows.length }}</strong>
                  </span>
                </span>
              </div>

              <div class="flex gap-1 rounded-lg border border-slate-200 p-0.5">
                <button
                  type="button"
                  class="rounded px-2.5 py-1 text-xs font-medium transition-colors"
                  :class="previewTab === 'valid' ? 'bg-brand text-white' : 'text-slate-600 hover:bg-slate-100'"
                  @click="previewTab = 'valid'"
                >
                  Hợp lệ ({{ validRows.length }})
                </button>
                <button
                  v-if="invalidRows.length"
                  type="button"
                  class="rounded px-2.5 py-1 text-xs font-medium transition-colors"
                  :class="previewTab === 'error' ? 'bg-rose-500 text-white' : 'text-slate-600 hover:bg-slate-100'"
                  @click="previewTab = 'error'"
                >
                  Lỗi ({{ invalidRows.length }})
                </button>
              </div>
            </div>

            <div class="max-h-72 overflow-auto rounded-xl border border-slate-200">
              <table class="w-full min-w-[600px] text-xs">
                <thead>
                  <tr class="border-b border-slate-200 bg-slate-50 text-left text-[11px] font-semibold text-slate-600">
                    <th class="px-3 py-2">
                      Dòng
                    </th>
                    <th class="px-3 py-2">
                      Tiêu đề
                    </th>
                    <th class="px-3 py-2">
                      Ưu tiên
                    </th>
                    <th class="px-3 py-2">
                      Trạng thái
                    </th>
                    <th class="px-3 py-2">
                      Nhóm kiểm thử
                    </th>
                    <th class="px-3 py-2">
                      Người phụ trách
                    </th>
                    <th
                      v-if="previewTab === 'error'"
                      class="px-3 py-2 text-rose-600"
                    >
                      Lỗi
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <template v-if="previewTab === 'valid'">
                    <tr
                      v-for="row in validRows"
                      :key="row._row"
                      class="border-b border-slate-100 last:border-0 even:bg-slate-50/50"
                    >
                      <td class="px-3 py-1.5 tabular-nums text-slate-400">
                        {{ row._row }}
                      </td>
                      <td class="max-w-[180px] truncate px-3 py-1.5 font-medium text-slate-800">
                        {{ row.title }}
                      </td>
                      <td class="px-3 py-1.5 text-slate-600">
                        {{ row.priority }}
                      </td>
                      <td class="px-3 py-1.5 text-slate-600">
                        {{ row.status }}
                      </td>
                      <td class="px-3 py-1.5 text-slate-600">
                        {{ displayOrEmpty(row.suite_name, 'Chưa nhóm') }}
                      </td>
                      <td class="px-3 py-1.5 text-slate-600">
                        {{ displayOrEmpty(row.owner_name, 'Chưa gán') }}
                      </td>
                    </tr>
                    <tr v-if="!validRows.length">
                      <td
                        colspan="6"
                        class="px-3 py-6 text-center text-slate-400"
                      >
                        Không có dòng hợp lệ.
                      </td>
                    </tr>
                  </template>

                  <template v-else>
                    <tr
                      v-for="row in invalidRows"
                      :key="row._row"
                      class="border-b border-rose-50 bg-rose-50/40 last:border-0"
                    >
                      <td class="px-3 py-1.5 tabular-nums text-slate-400">
                        {{ row._row }}
                      </td>
                      <td class="max-w-[140px] truncate px-3 py-1.5 font-medium text-slate-700">
                        {{ row.title }}
                      </td>
                      <td class="px-3 py-1.5">
                        {{ row.priority }}
                      </td>
                      <td class="px-3 py-1.5">
                        {{ row.status }}
                      </td>
                      <td class="px-3 py-1.5">
                        {{ displayOrEmpty(row.suite_name, 'Chưa nhóm') }}
                      </td>
                      <td class="px-3 py-1.5">
                        {{ displayOrEmpty(row.owner_name, 'Chưa gán') }}
                      </td>
                      <td class="px-3 py-1.5 text-rose-600">
                        {{ (row._errors ?? []).join('; ') }}
                      </td>
                    </tr>
                  </template>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- ── XUẤT ── -->
        <div
          v-else-if="activeTab === 'export'"
          class="space-y-4 py-1"
        >
          <p class="text-sm text-slate-600">
            Xuất danh sách test case của dự án ra file Excel.
          </p>

          <div class="space-y-2">
            <label class="flex cursor-pointer items-center gap-2">
              <input
                v-model="exportScope"
                type="radio"
                value="all"
                class="h-4 w-4 text-brand"
              >
              <span class="text-sm">Tất cả test case ({{ testCases.length }})</span>
            </label>
          </div>

          <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 text-xs text-slate-600">
            File xuất: <strong>VA_TestCase_{{ projectCode }}_{{ new Date().toISOString().slice(0, 10) }}.xlsx</strong>
          </div>

          <button
            type="button"
            class="btn-primary h-9 gap-1.5 px-4 text-sm"
            :disabled="!testCases.length"
            @click="runExport"
          >
            <AppIcon
              name="download"
              :size="14"
            />
            Xuất Excel
          </button>
        </div>

        <!-- ── ĐỐI SOÁT ── -->
        <div
          v-else-if="activeTab === 'reconcile'"
          class="py-1"
        >
          <!-- Summary -->
          <div class="mb-4 grid grid-cols-3 gap-3">
            <div class="rounded-xl border border-rose-100 bg-rose-50 px-3 py-2.5 text-center">
              <p class="font-display text-xl font-bold tabular-nums text-rose-600">
                {{ reconcileSummary.errors }}
              </p>
              <p class="text-[11px] text-rose-600">
                Lỗi
              </p>
            </div>
            <div class="rounded-xl border border-amber-100 bg-amber-50 px-3 py-2.5 text-center">
              <p class="font-display text-xl font-bold tabular-nums text-amber-600">
                {{ reconcileSummary.warnings }}
              </p>
              <p class="text-[11px] text-amber-600">
                Cảnh báo
              </p>
            </div>
            <div class="rounded-xl border border-sky-100 bg-sky-50 px-3 py-2.5 text-center">
              <p class="font-display text-xl font-bold tabular-nums text-sky-600">
                {{ reconcileSummary.info }}
              </p>
              <p class="text-[11px] text-sky-600">
                Thông tin
              </p>
            </div>
          </div>

          <!-- Clean state -->
          <div
            v-if="!reconcileIssues.length"
            class="flex flex-col items-center gap-3 py-12 text-center"
          >
            <span class="grid h-12 w-12 place-items-center rounded-2xl bg-emerald-50 text-emerald-500">
              <AppIcon
                name="done"
                :size="24"
              />
            </span>
            <p class="text-sm font-semibold text-emerald-700">
              Không phát hiện vấn đề nào
            </p>
            <p class="text-xs text-slate-400">
              Danh sách test case đang ổn định.
            </p>
          </div>

          <!-- Issue list -->
          <div
            v-else
            class="max-h-80 space-y-2 overflow-auto"
          >
            <div
              v-for="(issue, idx) in reconcileIssues"
              :key="idx"
              class="flex items-start gap-2.5 rounded-xl border px-3 py-2.5"
              :class="{
                'border-rose-100 bg-rose-50': issue.level === 'error',
                'border-amber-100 bg-amber-50': issue.level === 'warning',
                'border-sky-100 bg-sky-50': issue.level === 'info',
              }"
            >
              <AppIcon
                :name="LEVEL_STYLE[issue.level].icon"
                :size="14"
                class="mt-0.5 shrink-0"
                :class="{
                  'text-rose-500': issue.level === 'error',
                  'text-amber-500': issue.level === 'warning',
                  'text-sky-500': issue.level === 'info',
                }"
              />
              <div class="min-w-0">
                <span
                  class="mb-0.5 inline-block rounded px-1.5 py-0.5 text-[10px] font-semibold"
                  :class="LEVEL_STYLE[issue.level].badge"
                >
                  {{ LEVEL_STYLE[issue.level].label }}
                </span>
                <p class="text-xs text-slate-700">
                  {{ issue.message }}
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="mt-3 flex shrink-0 justify-end gap-2 border-t border-slate-100 pt-3">
        <button
          type="button"
          class="btn-ghost h-9 px-4 text-sm"
          @click="emit('close')"
        >
          Đóng
        </button>

        <button
          v-if="activeTab === 'import' && step === 'preview' && validRows.length > 0 && canManage"
          type="button"
          class="btn-primary h-9 gap-1.5 px-4 text-sm"
          :disabled="importing"
          @click="submitImport"
        >
          <AppIcon
            name="upload"
            :size="14"
          />
          Nhập {{ validRows.length }} test case
        </button>
      </div>
    </div>
  </Modal>
</template>
