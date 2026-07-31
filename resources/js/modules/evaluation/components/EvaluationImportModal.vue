<script setup>
import { ref, computed, inject, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Modal from '@/Components/Ui/Modal.vue';
import SearchSelect from '@/shared/ui/SearchSelect.vue';
import { useConfirmDelete } from '@/composables/useConfirmClose';
import { useModalFormDraft } from '@/composables/useModalFormDraft';
import { draftHasMeaningfulContent } from '@/composables/useModalDraftHelpers';
import {
    downloadEvaluationImportTemplate,
    parseEvaluationImportFile,
    validateImportRows,
    createPreviewRows,
    revalidatePreviewRow,
    rowsToPayload,
    exportPreviewRows,
    IMPORT_MIN_LEVELS,
    IMPORT_MAX_LEVELS,
} from '@/modules/evaluation/composables/useEvaluationImport';

const props = defineProps({
    show: { type: Boolean, default: false },
    departments: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    canCreateGeneral: { type: Boolean, default: false },
    defaultScoreLevels: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'imported']);
const confirmDelete = useConfirmDelete();

const step = ref('guide');
const previewTab = ref('valid');
const parsing = ref(false);
const importing = ref(false);
const fileError = ref('');
const parseErrors = ref([]);
const previewRows = ref([]);
const fileInputRef = ref(null);
const dragOver = ref(false);
const expandedLines = ref(new Set());

const importCtx = computed(() => ({
    departments: props.departments,
    categories: props.categories,
    canCreateGeneral: props.canCreateGeneral,
}));

const departmentOptions = computed(() => props.departments.map((d) => ({ id: d.code, name: `${d.name} (${d.code})` })));
const categoryOptions = computed(() => props.categories.map((c) => ({ id: c, name: c })));

const validRows = computed(() => previewRows.value.filter((r) => r.valid));
const invalidRows = computed(() => previewRows.value.filter((r) => !r.valid));
const displayedRows = computed(() => (previewTab.value === 'valid' ? validRows.value : invalidRows.value));
const canSubmit = computed(() => validRows.value.length > 0 && !importing.value);

const reset = () => {
    step.value = 'guide';
    previewTab.value = 'valid';
    parsing.value = false;
    importing.value = false;
    fileError.value = '';
    parseErrors.value = [];
    previewRows.value = [];
    expandedLines.value = new Set();
    if (fileInputRef.value) fileInputRef.value.value = '';
};

const close = () => {
    reset();
    emit('close');
};

const modalClose = inject('modalClose', () => close());
const importDirty = computed(() => previewRows.value.length > 0 || step.value === 'preview');

const importDraft = useModalFormDraft('evaluation-import', {
    getScope: () => 'global',
    pick: () => ({
        step: step.value,
        previewTab: previewTab.value,
        previewRows: previewRows.value.slice(0, 200),
        parseErrors: parseErrors.value,
    }),
    hasContent: (data) => (data.previewRows?.length ?? 0) > 0
        || draftHasMeaningfulContent({ parseErrors: data.parseErrors }),
});

const applyImportDraft = (data) => {
    if (data.step) step.value = data.step;
    if (data.previewTab) previewTab.value = data.previewTab;
    if (Array.isArray(data.parseErrors)) parseErrors.value = data.parseErrors;
    if (Array.isArray(data.previewRows)) previewRows.value = data.previewRows;
};

const saveDraftOnClose = () => {
    importDraft.saveOnClose({});
};

watch(() => props.show, async (open) => {
    if (!open) return;
    reset();
    const epoch = importDraft.bumpOpenEpoch();
    await importDraft.tryRestore(applyImportDraft, {
        isActive: () => props.show,
        openEpoch: epoch,
    });
});

const onDownloadTemplate = () => {
    downloadEvaluationImportTemplate({
        departments: props.departments,
        categories: props.categories,
        defaultScoreLevels: props.defaultScoreLevels,
    });
};

const onPickFile = () => fileInputRef.value?.click();

const applyParsedRows = (raw, errors = []) => {
    parseErrors.value = errors;
    if (!raw.length) {
        if (!errors.length) fileError.value = 'File không có dữ liệu.';
        return;
    }
    previewRows.value = createPreviewRows(validateImportRows(raw, importCtx.value));
    previewTab.value = validRows.value.length ? 'valid' : 'invalid';
    step.value = 'preview';
};

const processFile = async (file) => {
    if (!file) return;

    fileError.value = '';
    parseErrors.value = [];
    previewRows.value = [];
    parsing.value = true;

    try {
        const ext = file.name.split('.').pop()?.toLowerCase();
        if (!['xlsx', 'xls'].includes(ext)) {
            fileError.value = 'Chỉ hỗ trợ file .xlsx hoặc .xls';
            return;
        }

        const { rows: raw, errors } = await parseEvaluationImportFile(file);
        applyParsedRows(raw, errors);
    } catch (err) {
        console.error(err);
        fileError.value = 'Không đọc được file. Kiểm tra lại định dạng.';
    } finally {
        parsing.value = false;
    }
};

const onFileChange = async (e) => {
    const file = e.target.files?.[0];
    await processFile(file);
};

const onDrop = async (e) => {
    dragOver.value = false;
    const file = e.dataTransfer?.files?.[0];
    await processFile(file);
};

const onRowChange = (row) => {
    revalidatePreviewRow(row, importCtx.value);
    if (row.valid && previewTab.value === 'invalid') {
        previewTab.value = 'valid';
    }
};

const onDepartmentChange = (row, code) => {
    const dept = props.departments.find((d) => d.code === code);
    row.edit.department_raw = dept ? dept.name : (code || '');
    onRowChange(row);
};

const onLevelChange = (row) => {
    onRowChange(row);
};

const addLevel = (row) => {
    if (row.edit.levels.length >= IMPORT_MAX_LEVELS) return;
    row.edit.levels.push({ code: null, label: '', description: '', weight: '' });
    onRowChange(row);
};

const removeLevel = (row, index) => {
    if (row.edit.levels.length <= 1) return;
    row.edit.levels.splice(index, 1);
    onRowChange(row);
};

const toggleExpand = (line) => {
    const next = new Set(expandedLines.value);
    if (next.has(line)) next.delete(line);
    else next.add(line);
    expandedLines.value = next;
};

const isExpanded = (line) => expandedLines.value.has(line);

const removeRow = (row) => {
    confirmDelete(
        `Xoá dòng "${row.edit.criteria_name || row.line}" khỏi danh sách xem trước?`,
        () => { previewRows.value = previewRows.value.filter((r) => r.line !== row.line); },
        { title: 'Xoá dòng' },
    );
};

const resetPreview = () => {
    step.value = 'guide';
    previewRows.value = [];
    fileError.value = '';
};

const chooseOtherFile = () => {
    if (!previewRows.value.length) {
        resetPreview();
        return;
    }
    confirmDelete(
        'Chọn file khác sẽ xoá dữ liệu xem trước hiện tại. Tiếp tục?',
        resetPreview,
        { title: 'Chọn file khác', confirmText: 'Tiếp tục' },
    );
};

const exportRows = (mode) => {
    const list = mode === 'errors' ? invalidRows.value : validRows.value;
    if (!list.length) return;
    exportPreviewRows(list, { mode });
};

const submitImport = () => {
    if (!canSubmit.value) return;
    importing.value = true;
    fileError.value = '';
    router.post(route('workspace.evaluation.import'), {
        rows: rowsToPayload(validRows.value),
    }, {
        preserveScroll: true,
        onSuccess: () => {
            importDraft.clear();
            emit('imported', { count: validRows.value.length });
            close();
        },
        onError: (errors) => {
            fileError.value = Object.values(errors).flat().join(' ') || 'Nhập thất bại.';
        },
        onFinish: () => { importing.value = false; },
    });
};
</script>

<template>
  <Modal
    :show="show"
    :dirty="importDirty"
    title="Nhập tiêu chí đánh giá từ file"
    max-width="max-w-7xl"
    :on-save-draft="saveDraftOnClose"
    @close="close"
  >
    <div class="mb-5 flex items-center gap-2 text-xs">
      <span
        class="rounded-full px-2.5 py-1 font-semibold"
        :class="step === 'guide' ? 'bg-brand text-white' : 'bg-slate-100 text-slate-500 dark:bg-slate-800'"
      >1. Hướng dẫn</span>
      <AppIcon
        name="chevron-right"
        :size="12"
        class="text-slate-300"
      />
      <span
        class="rounded-full px-2.5 py-1 font-semibold"
        :class="step === 'preview' ? 'bg-brand text-white' : 'bg-slate-100 text-slate-500 dark:bg-slate-800'"
      >2. Xem trước & nhập</span>
    </div>

    <!-- Guide step: horizontal 2-panel layout -->
    <div
      v-if="step === 'guide'"
      class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,22rem)_1fr]"
    >
      <!-- Left rail: process + convention -->
      <div class="space-y-4">
        <div class="rounded-xl border border-brand/20 bg-brand/5 p-4 dark:bg-brand/10">
          <h3 class="flex items-center gap-2 font-semibold text-slate-800 dark:text-slate-100">
            <AppIcon
              name="info"
              :size="16"
              class="text-brand"
            />
            Quy trình nhập liệu
          </h3>
          <ol class="mt-2 list-decimal space-y-1.5 pl-5 text-sm text-slate-600 dark:text-slate-300">
            <li>Tải <strong>file mẫu Excel</strong> (sheet "Nhap lieu" + "Tham chieu").</li>
            <li>Điền dữ liệu từ <strong>dòng 8</strong> (sau 2 dòng mẫu), không đổi tên cột.</li>
            <li>Để trống cột <strong>Phòng ban</strong> = tiêu chí chung (chỉ siêu quản trị).</li>
            <li>Chọn file → sửa trực tiếp trên bảng xem trước nếu cần.</li>
            <li>Chỉ dòng <strong>hợp lệ</strong> được nhập; có thể xuất dòng lỗi để sửa offline.</li>
          </ol>
        </div>

        <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
            Quy ước thang điểm
          </p>
          <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
            Mỗi dòng có tối đa 5 bộ cột <strong>Mức 1…5</strong> (Nhãn / Mô tả / Điểm).
            Mức 1 và 2 <strong>bắt buộc</strong>; Mức 3–5 để trống nếu không dùng.
            Cần trên 5 mức? Sửa bổ sung qua modal "Thêm tiêu chí" sau khi nhập.
          </p>
        </div>
      </div>

      <!-- Right panel: reference cards + file input -->
      <div class="space-y-4">
        <div class="grid gap-3 sm:grid-cols-3">
          <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
              Chấm 0.5
            </p>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
              Có / Không
            </p>
          </div>
          <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
              Phòng ban ({{ departments.length }})
            </p>
            <p class="mt-1 line-clamp-2 text-sm text-slate-600 dark:text-slate-300">
              {{ departments.slice(0, 4).map((d) => d.name).join(', ') || 'Chưa có dữ liệu' }}{{ departments.length > 4 ? '…' : '' }}
            </p>
          </div>
          <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
              Loại tiêu chí ({{ categories.length }})
            </p>
            <p class="mt-1 line-clamp-2 text-sm text-slate-600 dark:text-slate-300">
              {{ categories.slice(0, 4).join(', ') || 'Chưa có dữ liệu' }}{{ categories.length > 4 ? '…' : '' }}
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
        </div>

        <div
          class="flex flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed p-8 text-center transition"
          :class="dragOver ? 'border-brand bg-brand/5' : 'border-slate-200 dark:border-slate-700'"
          @dragover.prevent="dragOver = true"
          @dragleave.prevent="dragOver = false"
          @drop.prevent="onDrop"
        >
          <AppIcon
            name="upload"
            :size="28"
            class="text-slate-300"
          />
          <p class="text-sm text-slate-500 dark:text-slate-400">
            Kéo thả file vào đây, hoặc
          </p>
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
          accept=".xlsx,.xls"
          class="hidden"
          @change="onFileChange"
        >

        <p
          v-if="parsing"
          class="flex items-center gap-2 text-sm text-slate-500"
        >
          <AppIcon
            name="refresh"
            :size="14"
            class="animate-spin"
          /> Đang đọc file…
        </p>
        <p
          v-if="fileError"
          class="text-sm text-rose-600"
        >
          {{ fileError }}
        </p>
        <ul
          v-if="parseErrors.length"
          class="list-disc pl-5 text-sm text-amber-700 dark:text-amber-400"
        >
          <li
            v-for="(err, i) in parseErrors"
            :key="i"
          >
            {{ err }}
          </li>
        </ul>
      </div>
    </div>

    <!-- Preview step -->
    <div
      v-else
      class="space-y-4"
    >
      <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex flex-wrap gap-2 text-sm">
          <span class="text-slate-500">
            Tổng <strong class="text-slate-800 dark:text-slate-100">{{ previewRows.length }}</strong> dòng
          </span>
        </div>
        <button
          type="button"
          class="btn-ghost text-xs"
          @click="chooseOtherFile"
        >
          ← Chọn file khác
        </button>
      </div>

      <!-- Tabs -->
      <div class="flex gap-1 rounded-xl border border-slate-200 bg-slate-50 p-1 dark:border-slate-700 dark:bg-slate-800/50">
        <button
          type="button"
          class="flex flex-1 items-center justify-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition"
          :class="previewTab === 'valid'
            ? 'bg-white text-emerald-700 shadow-sm dark:bg-slate-900 dark:text-emerald-400'
            : 'text-slate-500 hover:text-slate-700'"
          @click="previewTab = 'valid'"
        >
          <AppIcon
            name="check"
            :size="14"
          />
          Hợp lệ
          <span
            class="rounded-full px-1.5 py-0.5 text-[10px] font-bold"
            :class="previewTab === 'valid' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600'"
          >{{ validRows.length }}</span>
        </button>
        <button
          type="button"
          class="flex flex-1 items-center justify-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition"
          :class="previewTab === 'invalid'
            ? 'bg-white text-rose-700 shadow-sm dark:bg-slate-900 dark:text-rose-400'
            : 'text-slate-500 hover:text-slate-700'"
          @click="previewTab = 'invalid'"
        >
          <AppIcon
            name="blockers"
            :size="14"
          />
          Lỗi
          <span
            class="rounded-full px-1.5 py-0.5 text-[10px] font-bold"
            :class="previewTab === 'invalid' ? 'bg-rose-100 text-rose-700' : 'bg-slate-200 text-slate-600'"
          >{{ invalidRows.length }}</span>
        </button>
      </div>

      <p class="text-xs text-slate-500 dark:text-slate-400">
        Sửa trực tiếp trên bảng — dòng chuyển sang tab Hợp lệ khi đã đúng. Bấm vào số mức để mở/đóng bảng thang điểm.
      </p>

      <div
        v-if="!displayedRows.length"
        class="rounded-xl border border-dashed border-slate-200 py-10 text-center text-sm text-slate-400 dark:border-slate-700"
      >
        {{ previewTab === 'valid' ? 'Chưa có dòng hợp lệ.' : 'Không còn dòng lỗi.' }}
      </div>

      <div
        v-else
        class="max-h-[min(30rem,55vh)] overflow-auto rounded-xl border border-slate-200 dark:border-slate-700"
      >
        <table class="w-full min-w-[1100px] text-xs">
          <thead class="sticky top-0 z-10 bg-slate-50 text-left text-[10px] uppercase tracking-wide text-slate-500 dark:bg-slate-800">
            <tr>
              <th class="w-8 px-1 py-2">
                #
              </th>
              <th class="w-8 px-1 py-2" />
              <th class="min-w-[10rem] px-1 py-2">
                Tên tiêu chí *
              </th>
              <th class="w-40 px-1 py-2">
                Phòng ban
              </th>
              <th class="w-28 px-1 py-2">
                Mã tiêu chí
              </th>
              <th class="w-36 px-1 py-2">
                Loại *
              </th>
              <th class="w-20 px-1 py-2 text-center">
                Chấm 0.5
              </th>
              <th class="w-24 px-1 py-2 text-center">
                Thang điểm
              </th>
              <th
                v-if="previewTab === 'invalid'"
                class="min-w-[10rem] px-1 py-2"
              >
                Lỗi
              </th>
              <th class="w-8 px-1 py-2" />
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <template
              v-for="row in displayedRows"
              :key="row.line"
            >
              <tr
                class="align-top"
                :class="row.valid ? '' : 'bg-rose-50/40 dark:bg-rose-950/15'"
              >
                <td class="px-1 py-1.5 text-slate-400">
                  {{ row.line }}
                </td>
                <td class="px-1 py-1.5">
                  <button
                    type="button"
                    class="grid h-6 w-6 place-items-center rounded text-slate-400 hover:bg-slate-100"
                    :title="isExpanded(row.line) ? 'Thu gọn' : 'Mở rộng'"
                    @click="toggleExpand(row.line)"
                  >
                    <AppIcon
                      :name="isExpanded(row.line) ? 'chevron-down' : 'chevron-right'"
                      :size="13"
                    />
                  </button>
                </td>
                <td class="px-1 py-1.5">
                  <input
                    v-model="row.edit.criteria_name"
                    type="text"
                    class="input w-full py-1 text-xs"
                    placeholder="Tên tiêu chí"
                    @input="onRowChange(row)"
                  >
                </td>
                <td class="px-1 py-1.5">
                  <SearchSelect
                    :model-value="row.department_code"
                    :options="departmentOptions"
                    placeholder="Để trống = chung"
                    @update:model-value="(v) => onDepartmentChange(row, v)"
                  />
                </td>
                <td class="px-1 py-1.5">
                  <input
                    v-model="row.edit.criteria_code"
                    type="text"
                    class="input w-full py-1 text-xs uppercase"
                    placeholder="Tự sinh"
                    @input="onRowChange(row)"
                  >
                </td>
                <td class="px-1 py-1.5">
                  <SearchSelect
                    v-model="row.edit.category"
                    :options="categoryOptions"
                    placeholder="Loại tiêu chí…"
                    :clearable="false"
                    @update:model-value="onRowChange(row)"
                  />
                </td>
                <td class="px-1 py-1.5 text-center">
                  <input
                    type="checkbox"
                    :checked="row.edit.allow_half_score"
                    @change="row.edit.allow_half_score = $event.target.checked; onRowChange(row)"
                  >
                </td>
                <td class="px-1 py-1.5 text-center">
                  <button
                    type="button"
                    class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300"
                    @click="toggleExpand(row.line)"
                  >
                    {{ row.levels.length }} mức
                  </button>
                </td>
                <td
                  v-if="previewTab === 'invalid'"
                  class="px-1 py-1.5 text-[10px] leading-snug text-rose-600"
                >
                  {{ row.errors.join('; ') }}
                </td>
                <td class="px-1 py-1.5">
                  <button
                    type="button"
                    class="grid h-7 w-7 place-items-center rounded text-slate-400 hover:bg-rose-50 hover:text-rose-600"
                    title="Xóa dòng"
                    @click="removeRow(row)"
                  >
                    <AppIcon
                      name="delete"
                      :size="13"
                    />
                  </button>
                </td>
              </tr>
              <tr v-if="isExpanded(row.line)">
                <td />
                <td />
                <td
                  colspan="7"
                  class="bg-slate-50/70 px-2 py-2 dark:bg-slate-800/40"
                >
                  <p class="mb-1.5 text-[10px] font-semibold uppercase tracking-wide text-slate-400">
                    Thang điểm (tối thiểu {{ IMPORT_MIN_LEVELS }}, tối đa {{ IMPORT_MAX_LEVELS }} mức)
                  </p>
                  <div class="space-y-1">
                    <div
                      v-for="(level, li) in row.edit.levels"
                      :key="li"
                      class="grid grid-cols-[1fr_1fr_6rem_1.75rem] items-center gap-1.5"
                    >
                      <input
                        v-model="level.label"
                        type="text"
                        class="input py-1 text-xs"
                        :placeholder="`Nhãn mức ${li + 1}`"
                        @input="onLevelChange(row)"
                      >
                      <input
                        v-model="level.description"
                        type="text"
                        class="input py-1 text-xs"
                        placeholder="Mô tả (tuỳ chọn)"
                        @input="onLevelChange(row)"
                      >
                      <input
                        v-model="level.weight"
                        type="number"
                        step="0.5"
                        class="input py-1 text-center text-xs tabular-nums"
                        placeholder="Điểm"
                        @input="onLevelChange(row)"
                      >
                      <button
                        type="button"
                        class="grid h-6 w-6 place-items-center rounded text-slate-400 hover:bg-rose-50 hover:text-rose-600 disabled:opacity-30"
                        :disabled="row.edit.levels.length <= 1"
                        @click="removeLevel(row, li)"
                      >
                        <AppIcon
                          name="trash"
                          :size="12"
                        />
                      </button>
                    </div>
                  </div>
                  <button
                    type="button"
                    class="btn-ghost mt-1.5 h-7 px-2 text-xs text-brand disabled:opacity-30"
                    :disabled="row.edit.levels.length >= IMPORT_MAX_LEVELS"
                    @click="addLevel(row)"
                  >
                    <AppIcon
                      name="add"
                      :size="12"
                    /> Thêm mức
                  </button>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <p
        v-if="fileError"
        class="text-sm text-rose-600"
      >
        {{ fileError }}
      </p>

      <!-- Actions -->
      <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
        <div class="flex flex-wrap gap-2">
          <button
            type="button"
            class="btn-ghost text-sm"
            @click="modalClose()"
          >
            Huỷ — không nhập
          </button>
          <button
            v-if="invalidRows.length"
            type="button"
            class="btn-ghost border border-rose-200 text-sm text-rose-700 dark:border-rose-800 dark:text-rose-400"
            @click="exportRows('errors')"
          >
            <AppIcon
              name="export"
              :size="14"
            /> Xuất {{ invalidRows.length }} dòng lỗi
          </button>
          <button
            v-if="validRows.length"
            type="button"
            class="btn-ghost border border-slate-200 text-sm dark:border-slate-600"
            @click="exportRows('valid')"
          >
            <AppIcon
              name="download"
              :size="14"
            /> Xuất {{ validRows.length }} dòng hợp lệ
          </button>
        </div>
        <button
          type="button"
          class="btn-primary text-sm disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="!canSubmit"
          @click="submitImport"
        >
          <AppIcon
            v-if="importing"
            name="refresh"
            :size="14"
            class="animate-spin"
          />
          <AppIcon
            v-else
            name="check"
            :size="14"
          />
          {{ importing ? 'Đang nhập…' : `Nhập ${validRows.length} dòng hợp lệ` }}
        </button>
      </div>
    </div>

    <div
      v-if="step === 'guide'"
      class="mt-4 flex justify-end border-t border-slate-100 pt-4 dark:border-slate-800"
    >
      <button
        type="button"
        class="btn-ghost text-sm"
        @click="modalClose()"
      >
        Đóng
      </button>
    </div>
  </Modal>
</template>
