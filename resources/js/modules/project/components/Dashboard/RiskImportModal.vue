<script setup>
import { ref, computed, inject } from 'vue';
import { router } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Modal from '@/Components/Ui/Modal.vue';
import SearchSelect from '@/shared/ui/SearchSelect.vue';
import { valueLabelOptions } from '@/shared/utils/selectOptions';
import { useConfirmDelete } from '@/composables/useConfirmClose';
import {
    downloadRiskImportTemplate,
    parseRiskImportFile,
    validateImportRows,
    createPreviewRows,
    revalidatePreviewRow,
    rowsToPayload,
    exportPreviewRows,
    IMPORT_HEADERS,
} from '@/composables/useRiskImport';

const props = defineProps({
    show: { type: Boolean, default: false },
    projectId: { type: Number, required: true },
    projectCode: { type: String, default: 'DA' },
    projectName: { type: String, default: '' },
    employees: { type: Array, default: () => [] },
    severityOptions: { type: Array, default: () => [] },
    statusOptions: { type: Array, default: () => [] },
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

const importCtx = computed(() => ({
    employees: props.employees,
    severityOptions: props.severityOptions,
    statusOptions: props.statusOptions,
}));

const validRows = computed(() => previewRows.value.filter((r) => r.valid));
const invalidRows = computed(() => previewRows.value.filter((r) => !r.valid));
const displayedRows = computed(() => (previewTab.value === 'valid' ? validRows.value : invalidRows.value));
const canSubmit = computed(() => validRows.value.length > 0 && !importing.value);

const severitySelectOptions = computed(() => [
    { id: '', name: '—' },
    ...valueLabelOptions(props.severityOptions),
]);
const statusSelectOptions = computed(() => valueLabelOptions(props.statusOptions));
const ownerSelectOptions = computed(() => [
    { id: '', name: '—' },
    ...props.employees,
]);

const reset = () => {
    step.value = 'guide';
    previewTab.value = 'valid';
    parsing.value = false;
    importing.value = false;
    fileError.value = '';
    parseErrors.value = [];
    previewRows.value = [];
    if (fileInputRef.value) fileInputRef.value.value = '';
};

const close = () => {
    reset();
    emit('close');
};

const modalClose = inject('modalClose', () => close());
const importDirty = computed(() => previewRows.value.length > 0 || step.value === 'preview');

const onDownloadTemplate = () => {
    downloadRiskImportTemplate({
        projectCode: props.projectCode,
        projectName: props.projectName,
        severityOptions: props.severityOptions,
        statusOptions: props.statusOptions,
        employees: props.employees,
    });
};

const onPickFile = () => fileInputRef.value?.click();

const applyParsedRows = (raw, errors = []) => {
    parseErrors.value = errors;
    if (!raw.length) {
        if (!errors.length) fileError.value = 'File không có dữ liệu.';
        return;
    }
    previewRows.value = createPreviewRows(
        validateImportRows(raw, importCtx.value),
        importCtx.value,
    );
    previewTab.value = validRows.value.length ? 'valid' : 'invalid';
    step.value = 'preview';
};

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
            fileError.value = 'Chỉ hỗ trợ file .xlsx, .xls hoặc .csv';
            return;
        }

        const { rows: raw, errors } = await parseRiskImportFile(file);
        applyParsedRows(raw, errors);
    } catch (err) {
        console.error(err);
        fileError.value = 'Không đọc được file. Kiểm tra lại định dạng.';
    } finally {
        parsing.value = false;
    }
};

const onRowChange = (row) => {
    revalidatePreviewRow(row, importCtx.value);
    if (row.valid && previewTab.value === 'invalid') {
        previewTab.value = 'valid';
    }
};

const removeRow = (row) => {
    confirmDelete(
        `Xoá dòng "${row.title || row.line}" khỏi danh sách xem trước?`,
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
    exportPreviewRows(list, {
        projectCode: props.projectCode,
        projectName: props.projectName,
        mode,
    });
};

const submitImport = () => {
    if (!canSubmit.value) return;
    importing.value = true;
    fileError.value = '';
    router.post('/blockers/import', {
        project_id: props.projectId,
        rows: rowsToPayload(validRows.value),
    }, {
        preserveScroll: true,
        onSuccess: () => {
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
    title="Nhập rủi ro & vướng mắc từ file"
    max-width="max-w-5xl"
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

    <!-- Guide step -->
    <div
      v-if="step === 'guide'"
      class="space-y-4"
    >
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
          <li>Tải <strong>file mẫu Excel</strong> (có sheet hướng dẫn + sheet nhập liệu).</li>
          <li>Điền dữ liệu từ <strong>dòng 8</strong> trong sheet &quot;Nhập liệu&quot; (sau 2 dòng mẫu).</li>
          <li>Chọn file → sửa trực tiếp trên bảng xem trước nếu cần.</li>
          <li>Chỉ các dòng <strong>hợp lệ</strong> được nhập; có thể xuất dòng lỗi để sửa offline.</li>
        </ol>
      </div>

      <div class="grid gap-3 sm:grid-cols-2">
        <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
            Cột bắt buộc
          </p>
          <ul class="mt-2 space-y-1 text-sm text-slate-600 dark:text-slate-300">
            <li
              v-for="h in IMPORT_HEADERS.filter((c) => c.label.includes('*'))"
              :key="h.key"
            >
              {{ h.label }}
            </li>
          </ul>
        </div>
        <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
            Mức độ hợp lệ
          </p>
          <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
            low, medium, high, critical hoặc tiếng Việt
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
        Sửa trực tiếp trên bảng — dòng chuyển sang tab Hợp lệ khi đã đúng.
      </p>

      <div
        v-if="!displayedRows.length"
        class="rounded-xl border border-dashed border-slate-200 py-10 text-center text-sm text-slate-400 dark:border-slate-700"
      >
        {{ previewTab === 'valid' ? 'Chưa có dòng hợp lệ.' : 'Không còn dòng lỗi.' }}
      </div>

      <div
        v-else
        class="max-h-[min(24rem,50vh)] overflow-auto rounded-xl border border-slate-200 dark:border-slate-700"
      >
        <table class="w-full min-w-[880px] text-xs">
          <thead class="sticky top-0 z-10 bg-slate-50 text-left text-[10px] uppercase tracking-wide text-slate-500 dark:bg-slate-800">
            <tr>
              <th class="w-8 px-1 py-2">
                #
              </th>
              <th class="min-w-[10rem] px-1 py-2">
                Tiêu đề *
              </th>
              <th class="w-28 px-1 py-2">
                Mức độ *
              </th>
              <th class="w-28 px-1 py-2">
                Trạng thái
              </th>
              <th class="w-32 px-1 py-2">
                Phụ trách
              </th>
              <th class="w-32 px-1 py-2">
                Hạn
              </th>
              <th class="min-w-[8rem] px-1 py-2">
                Nguyên nhân
              </th>
              <th class="min-w-[8rem] px-1 py-2">
                Hướng xử lý
              </th>
              <th
                v-if="previewTab === 'invalid'"
                class="min-w-[8rem] px-1 py-2"
              >
                Lỗi
              </th>
              <th class="w-8 px-1 py-2" />
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <tr
              v-for="row in displayedRows"
              :key="row.line"
              class="align-top"
              :class="row.valid ? '' : 'bg-rose-50/40 dark:bg-rose-950/15'"
            >
              <td class="px-1 py-1.5 text-slate-400">
                {{ row.line }}
              </td>
              <td class="px-1 py-1.5">
                <input
                  v-model="row.edit.title"
                  type="text"
                  class="input w-full py-1 text-xs"
                  placeholder="Tiêu đề"
                  @input="onRowChange(row)"
                >
              </td>
              <td class="px-1 py-1.5">
                <SearchSelect
                  v-model="row.edit.severity"
                  :options="severitySelectOptions"
                  placeholder="Mức độ…"
                  :clearable="false"
                  @update:model-value="onRowChange(row)"
                />
              </td>
              <td class="px-1 py-1.5">
                <SearchSelect
                  v-model="row.edit.status"
                  :options="statusSelectOptions"
                  placeholder="Trạng thái…"
                  :clearable="false"
                  @update:model-value="onRowChange(row)"
                />
              </td>
              <td class="px-1 py-1.5">
                <SearchSelect
                  v-model="row.edit.owner_id"
                  :options="ownerSelectOptions"
                  placeholder="Người phụ trách…"
                  show-avatar
                  @update:model-value="onRowChange(row)"
                />
              </td>
              <td class="px-1 py-1.5">
                <input
                  v-model="row.edit.due_date"
                  type="date"
                  class="input w-full py-1 text-xs"
                  @change="onRowChange(row)"
                >
              </td>
              <td class="px-1 py-1.5">
                <input
                  v-model="row.edit.root_cause"
                  type="text"
                  class="input w-full py-1 text-xs"
                  placeholder="Tùy chọn"
                  @input="onRowChange(row)"
                >
              </td>
              <td class="px-1 py-1.5">
                <input
                  v-model="row.edit.resolution"
                  type="text"
                  class="input w-full py-1 text-xs"
                  placeholder="Tùy chọn"
                  @input="onRowChange(row)"
                >
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
