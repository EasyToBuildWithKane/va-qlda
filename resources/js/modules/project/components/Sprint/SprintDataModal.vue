<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Modal from '@/Components/Ui/Modal.vue';
import { useToast } from '@/shared/composables/useToast';
import {
    SPRINT_IMPORT_HEADERS,
    downloadSprintImportTemplate,
    parseSprintImportFile,
    validateSprintImportRows,
    createSprintPreviewRows,
    revalidateSprintPreviewRow,
    sprintRowToPayload,
    exportSprintWorkbook,
} from '@/composables/useSprintData';

const props = defineProps({
    show: { type: Boolean, default: false },
    projectId: { type: Number, required: true },
    projectCode: { type: String, default: 'PRJ' },
    projectName: { type: String, default: '' },
    sprints: { type: Array, default: () => [] },
    tasks: { type: Array, default: () => [] },
    filteredTasks: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
    enums: { type: Object, default: () => ({}) },
    issues: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
    sprintById: { type: Map, default: () => new Map() },
    canManage: { type: Boolean, default: false },
    initialTab: { type: String, default: 'import' },
});

const emit = defineEmits(['close', 'imported', 'fix']);
const toast = useToast();

const tab = ref(props.initialTab);

watch(() => props.show, (open) => {
    if (open) tab.value = props.initialTab;
});
const importStep = ref('guide');
const previewTab = ref('valid');
const parsing = ref(false);
const importing = ref(false);
const fileError = ref('');
const parseErrors = ref([]);
const previewRows = ref([]);
const fileInputRef = ref(null);
const exportScope = ref('filtered');

const importCtx = computed(() => ({
    sprints: props.sprints,
    employees: props.employees,
    statusOptions: props.enums.taskStatus || [],
    priorityOptions: props.enums.taskPriority || [],
    phaseOptions: props.enums.taskPhase || [],
}));

const validRows = computed(() => previewRows.value.filter((r) => r.valid));
const invalidRows = computed(() => previewRows.value.filter((r) => !r.valid));
const displayedRows = computed(() => (previewTab.value === 'valid' ? validRows.value : invalidRows.value));
const canSubmit = computed(() => validRows.value.length > 0 && !importing.value && props.canManage);

const levelStyle = {
    error: 'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-950/40 dark:text-rose-300',
    warning: 'bg-amber-50 text-amber-800 ring-amber-200 dark:bg-amber-950/40',
    info: 'bg-sky-50 text-sky-700 ring-sky-200 dark:bg-sky-950/40',
};

const resetImport = () => {
    importStep.value = 'guide';
    previewTab.value = 'valid';
    parsing.value = false;
    importing.value = false;
    fileError.value = '';
    parseErrors.value = [];
    previewRows.value = [];
    if (fileInputRef.value) fileInputRef.value.value = '';
};

const close = () => {
    resetImport();
    tab.value = 'import';
    emit('close');
};

const dirty = computed(() => previewRows.value.length > 0);

const onDownloadTemplate = () => {
    downloadSprintImportTemplate({
        projectCode: props.projectCode,
        projectName: props.projectName,
        sprints: props.sprints,
        employees: props.employees,
        statusOptions: importCtx.value.statusOptions,
        priorityOptions: importCtx.value.priorityOptions,
        phaseOptions: importCtx.value.phaseOptions,
    });
};

const onPickFile = () => fileInputRef.value?.click();

const onFileChange = async (e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    fileError.value = '';
    parseErrors.value = [];
    previewRows.value = [];
    parsing.value = true;
    try {
        const ext = file.name.split('.').pop()?.toLowerCase();
        if (!['xlsx', 'xls', 'csv'].includes(ext)) {
            fileError.value = 'Chỉ hỗ trợ .xlsx, .xls, .csv';
            return;
        }
        const { rows, errors } = await parseSprintImportFile(file);
        parseErrors.value = errors;
        if (!rows.length && errors.length) return;
        previewRows.value = createSprintPreviewRows(validateSprintImportRows(rows, importCtx.value));
        previewTab.value = validRows.value.length ? 'valid' : 'invalid';
        importStep.value = 'preview';
    } catch {
        fileError.value = 'Không đọc được file.';
    } finally {
        parsing.value = false;
    }
};

const onRowBlur = (row) => {
    revalidateSprintPreviewRow(row, importCtx.value);
};

const submitImport = async () => {
    if (!canSubmit.value) return;
    importing.value = true;
    fileError.value = '';
    let ok = 0;
    let err = 0;
    for (const row of validRows.value) {
        try {
            await new Promise((resolve, reject) => {
                router.post(`/projects/${props.projectId}/tasks`, sprintRowToPayload(row), {
                    preserveScroll: true,
                    onSuccess: () => { ok++; resolve(); },
                    onError: () => { err++; reject(); },
                });
            });
        } catch {
            err++;
        }
    }
    importing.value = false;
    if (ok) {
        emit('imported', { ok, err });
        close();
    } else {
        fileError.value = `Không nhập được dòng nào (${err} lỗi).`;
    }
};

const exportTasks = computed(() => {
    if (exportScope.value === 'all') return props.tasks;
    if (exportScope.value === 'filtered') return props.filteredTasks;
    return props.filteredTasks;
});

const runExport = () => {
    const name = exportSprintWorkbook({
        projectCode: props.projectCode,
        projectName: props.projectName,
        tasks: exportTasks.value,
        sprints: props.sprints,
        sprintById: props.sprintById,
        issues: [],
    });
    toast.success(`Đã xuất: ${name}`);
};

const exportReconcileOnly = () => {
    const name = exportSprintWorkbook({
        projectCode: props.projectCode,
        projectName: props.projectName,
        tasks: props.tasks,
        sprints: props.sprints,
        sprintById: props.sprintById,
        issues: props.issues,
    });
    toast.success(`Đã xuất: ${name}`);
};
</script>

<template>
  <Modal
    :show="show"
    :dirty="dirty"
    title="Dữ liệu Sprint & Task"
    max-width="max-w-5xl"
    @close="close"
  >
    <!-- Tabs -->
    <div class="mb-4 flex gap-1 rounded-xl border border-slate-200 bg-slate-50 p-1 dark:border-slate-700 dark:bg-slate-800/50">
      <button
        v-for="t in [
          { key: 'import', label: 'Nhập', icon: 'upload' },
          { key: 'export', label: 'Xuất', icon: 'export' },
          { key: 'reconcile', label: 'Đối soát', icon: 'check' },
        ]"
        :key="t.key"
        type="button"
        class="flex flex-1 items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium transition"
        :class="tab === t.key ? 'bg-white text-brand shadow-sm dark:bg-slate-900' : 'text-slate-500 hover:text-slate-700'"
        @click="tab = t.key"
      >
        <AppIcon
          :name="t.icon"
          :size="15"
        />
        {{ t.label }}
        <span
          v-if="t.key === 'reconcile' && summary.errors"
          class="rounded-full bg-rose-500 px-1.5 text-[10px] text-white"
        >{{ summary.errors }}</span>
      </button>
    </div>

    <!-- ===== NHẬP ===== -->
    <div
      v-if="tab === 'import'"
      class="space-y-4"
    >
      <div
        v-if="!canManage"
        class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
      >
        Bạn không có quyền nhập dữ liệu.
      </div>
      <template v-else>
        <div class="flex items-center gap-2 text-xs">
          <span
            class="rounded-full px-2.5 py-1 font-semibold"
            :class="importStep === 'guide' ? 'bg-brand text-white' : 'bg-slate-100 text-slate-500'"
          >1. Hướng dẫn</span>
          <AppIcon
            name="chevron-right"
            :size="12"
            class="text-slate-300"
          />
          <span
            class="rounded-full px-2.5 py-1 font-semibold"
            :class="importStep === 'preview' ? 'bg-brand text-white' : 'bg-slate-100 text-slate-500'"
          >2. Xem trước & nhập</span>
        </div>

        <div
          v-if="importStep === 'guide'"
          class="space-y-4"
        >
          <div class="rounded-xl border border-brand/20 bg-brand/5 p-4">
            <h3 class="flex items-center gap-2 font-semibold text-slate-800 dark:text-slate-100">
              <AppIcon
                name="info"
                :size="16"
                class="text-brand"
              />
              Quy trình nhập
            </h3>
            <ol class="mt-2 list-decimal space-y-1.5 pl-5 text-sm text-slate-600 dark:text-slate-300">
              <li><strong>Tải file mẫu Excel</strong> — sheet &quot;Huong dan&quot; có 9 mục: quy trình, mô tả từng cột, bảng giá trị, sprint, nhân sự, lỗi thường gặp.</li>
              <li>Đọc sheet <strong>Huong dan</strong> trước khi điền sheet <strong>Nhap lieu</strong> (dòng 8+, xóa dòng mẫu in nghiêng).</li>
              <li>Chọn file → kiểm tra bảng xem trước → <strong>Xác nhận nhập</strong> (tối đa 200 dòng).</li>
            </ol>
          </div>
          <div class="grid gap-3 sm:grid-cols-2">
            <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
              <p class="text-xs font-semibold uppercase text-slate-400">
                Cột bắt buộc
              </p>
              <ul class="mt-2 space-y-1 text-sm text-slate-600">
                <li
                  v-for="h in SPRINT_IMPORT_HEADERS.filter((c) => c.label.includes('*'))"
                  :key="h.key"
                >
                  {{ h.label }}
                </li>
              </ul>
            </div>
            <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
              <p class="text-xs font-semibold uppercase text-slate-400">
                Lưu ý
              </p>
              <p class="mt-2 text-sm text-slate-600">
                Tối đa 200 dòng/lần · Xóa dòng mẫu in nghiêng trước khi upload.
              </p>
            </div>
          </div>
          <div class="flex flex-wrap gap-2">
            <button
              type="button"
              class="btn-primary text-sm"
              @click="onDownloadTemplate"
            >
              <AppIcon
                name="download"
                :size="15"
              /> Tải file mẫu (.xlsx)
            </button>
            <button
              type="button"
              class="btn-ghost border border-slate-200 text-sm dark:border-slate-600"
              @click="onPickFile"
            >
              <AppIcon
                name="upload"
                :size="15"
              /> Chọn file đã điền
            </button>
          </div>
          <input
            ref="fileInputRef"
            type="file"
            accept=".xlsx,.xls,.csv"
            class="hidden"
            @change="onFileChange"
          >
          <p
            v-if="parsing"
            class="text-sm text-slate-500"
          >
            <AppIcon
              name="refresh"
              :size="14"
              class="inline animate-spin"
            /> Đang đọc…
          </p>
          <p
            v-if="fileError"
            class="text-sm text-rose-600"
          >
            {{ fileError }}
          </p>
          <ul
            v-if="parseErrors.length"
            class="list-disc pl-5 text-sm text-amber-700"
          >
            <li
              v-for="(e, i) in parseErrors"
              :key="i"
            >
              {{ e }}
            </li>
          </ul>
        </div>

        <div
          v-else
          class="space-y-3"
        >
          <button
            type="button"
            class="btn-ghost text-xs"
            @click="resetImport"
          >
            ← Quay lại hướng dẫn
          </button>
          <div class="flex gap-2 text-sm">
            <span class="text-emerald-600">{{ validRows.length }} hợp lệ</span>
            <span class="text-rose-600">{{ invalidRows.length }} lỗi</span>
          </div>
          <div class="max-h-64 overflow-auto rounded-xl border border-slate-200 dark:border-slate-700">
            <table class="w-full min-w-[720px] text-xs">
              <thead class="sticky top-0 bg-slate-50 text-[10px] uppercase text-slate-500 dark:bg-slate-800">
                <tr>
                  <th class="px-2 py-1.5">
                    #
                  </th>
                  <th class="px-2 py-1.5">
                    Tiêu đề
                  </th>
                  <th class="px-2 py-1.5">
                    Sprint
                  </th>
                  <th class="px-2 py-1.5">
                    TT
                  </th>
                  <th class="px-2 py-1.5">
                    Ưu tiên
                  </th>
                  <th class="px-2 py-1.5">
                    Lỗi
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="row in displayedRows"
                  :key="row.line"
                  class="border-t border-slate-100 dark:border-slate-800"
                >
                  <td class="px-2 py-1">
                    {{ row.line }}
                  </td>
                  <td class="px-2 py-1">
                    <input
                      v-model="row.title"
                      class="input w-full py-0.5 text-xs"
                      @blur="onRowBlur(row)"
                    >
                  </td>
                  <td class="px-2 py-1">
                    <input
                      v-model="row.sprint_name"
                      class="input w-full py-0.5 text-xs"
                      @blur="onRowBlur(row)"
                    >
                  </td>
                  <td class="px-2 py-1">
                    <input
                      v-model="row.status"
                      class="input w-20 py-0.5 text-xs"
                      @blur="onRowBlur(row)"
                    >
                  </td>
                  <td class="px-2 py-1">
                    <input
                      v-model="row.priority"
                      class="input w-20 py-0.5 text-xs"
                      @blur="onRowBlur(row)"
                    >
                  </td>
                  <td class="px-2 py-1 text-rose-600">
                    {{ row.errors?.join('; ') }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="flex justify-end gap-2 border-t border-slate-100 pt-3 dark:border-slate-800">
            <button
              type="button"
              class="btn-ghost text-sm"
              @click="previewTab = previewTab === 'valid' ? 'invalid' : 'valid'"
            >
              Xem {{ previewTab === 'valid' ? 'lỗi' : 'hợp lệ' }}
            </button>
            <button
              type="button"
              class="btn-primary text-sm"
              :disabled="!canSubmit"
              @click="submitImport"
            >
              {{ importing ? 'Đang nhập…' : `Nhập ${validRows.length} task` }}
            </button>
          </div>
        </div>
      </template>
    </div>

    <!-- ===== XUẤT ===== -->
    <div
      v-else-if="tab === 'export'"
      class="space-y-4"
    >
      <div class="rounded-xl border border-brand/20 bg-brand/5 p-4">
        <h3 class="font-semibold text-slate-800 dark:text-slate-100">
          Xuất Excel định dạng
        </h3>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
          File gồm sheet Tổng quan, Tasks, Sprints — có tiêu đề, màu thương hiệu và bảng dễ đọc.
        </p>
      </div>
      <div>
        <p class="mb-2 text-xs font-semibold uppercase text-slate-400">
          Phạm vi task
        </p>
        <div class="flex flex-wrap gap-2">
          <label
            class="flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm"
            :class="exportScope === 'filtered' ? 'border-brand bg-brand/5' : 'border-slate-200'"
          >
            <input
              v-model="exportScope"
              type="radio"
              value="filtered"
              class="text-brand"
            >
            Kết quả tìm kiếm ({{ filteredTasks.length }})
          </label>
          <label
            class="flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm"
            :class="exportScope === 'all' ? 'border-brand bg-brand/5' : 'border-slate-200'"
          >
            <input
              v-model="exportScope"
              type="radio"
              value="all"
              class="text-brand"
            >
            Toàn bộ dự án ({{ tasks.length }})
          </label>
        </div>
      </div>
      <p class="text-sm text-slate-500">
        Luôn kèm {{ sprints.length }} sprint trong file.
      </p>
      <button
        type="button"
        class="btn-primary text-sm"
        @click="runExport"
      >
        <AppIcon
          name="export"
          :size="15"
        /> Tải file Excel (.xlsx)
      </button>
    </div>

    <!-- ===== ĐỐI SOÁT ===== -->
    <div
      v-else
      class="space-y-4"
    >
      <div class="flex flex-wrap gap-3 text-sm">
        <span class="rounded-lg bg-slate-100 px-3 py-1 dark:bg-slate-800">{{ summary.total }} phát hiện</span>
        <span class="rounded-lg bg-rose-50 px-3 py-1 text-rose-700">{{ summary.errors }} lỗi</span>
        <span class="rounded-lg bg-amber-50 px-3 py-1 text-amber-800">{{ summary.warnings }} cảnh báo</span>
      </div>
      <p
        v-if="!issues.length"
        class="py-8 text-center text-sm text-emerald-600"
      >
        Dữ liệu nhất quán.
      </p>
      <ul
        v-else
        class="max-h-64 space-y-2 overflow-y-auto"
      >
        <li
          v-for="(issue, i) in issues"
          :key="i"
          class="flex items-start justify-between gap-3 rounded-xl px-3 py-2 ring-1 ring-inset"
          :class="levelStyle[issue.level] || levelStyle.info"
        >
          <div>
            <span class="text-[10px] font-bold uppercase">{{ issue.level }}</span>
            <p class="text-sm">
              {{ issue.message }}
            </p>
          </div>
          <button
            v-if="issue.taskId || issue.sprintId"
            type="button"
            class="shrink-0 text-xs font-semibold text-brand hover:underline"
            @click="emit('fix', issue); close()"
          >
            Sửa
          </button>
        </li>
      </ul>
      <button
        v-if="issues.length"
        type="button"
        class="btn-ghost border border-slate-200 text-sm"
        @click="exportReconcileOnly"
      >
        <AppIcon
          name="export"
          :size="14"
        /> Xuất báo cáo đối soát
      </button>
    </div>

    <div class="mt-4 flex justify-end border-t border-slate-100 pt-3 dark:border-slate-800">
      <button
        type="button"
        class="btn-ghost text-sm"
        @click="close"
      >
        Đóng
      </button>
    </div>
  </Modal>
</template>
