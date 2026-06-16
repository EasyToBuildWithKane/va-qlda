<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import Modal from '@/Components/Ui/Modal.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { useToast } from '@/shared/composables/useToast';
import {
    downloadContractImportTemplate,
    parseContractImportFile,
    validateImportRows,
    createPreviewRows,
    revalidatePreviewRow,
    rowsToPayload,
} from '../composables/useContractImport.js';
import { downloadContractExport } from '../composables/useContractExport.js';

const props = defineProps({
    show: { type: Boolean, default: false },
    canManage: { type: Boolean, default: false },
    vendors: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
    statusOptions: { type: Array, default: () => [] },
    paymentOptions: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'imported']);
const toast = useToast();

const tab = ref(props.canManage ? 'import' : 'export');
const tabs = computed(() => [
    { key: 'import', label: 'Nhập', icon: 'upload', disabled: !props.canManage },
    { key: 'export', label: 'Xuất', icon: 'download', disabled: false },
    { key: 'reconcile', label: 'Đối soát', icon: 'refresh', disabled: false },
]);

// ── Import ──
const step = ref('upload'); // upload | preview
const previewTab = ref('valid');
const previewRows = ref([]);
const parsing = ref(false);
const importing = ref(false);

const validRows = computed(() => previewRows.value.filter((r) => r.valid));
const invalidRows = computed(() => previewRows.value.filter((r) => !r.valid));
const displayedRows = computed(() => (previewTab.value === 'valid' ? validRows.value : invalidRows.value));
const canSubmit = computed(() => validRows.value.length > 0 && !importing.value);

const importOpts = computed(() => ({
    vendors: props.vendors,
    categories: props.categories,
    employees: props.employees,
    statusOptions: props.statusOptions,
    paymentOptions: props.paymentOptions,
}));

function onDownloadTemplate() {
    downloadContractImportTemplate({
        vendors: props.vendors,
        statusOptions: props.statusOptions,
        paymentOptions: props.paymentOptions,
    });
}

async function onFileChange(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    parsing.value = true;
    try {
        const { rows, errors } = await parseContractImportFile(file);
        if (errors.length) {
            toast.error(errors[0]);
            if (!rows.length) { parsing.value = false; e.target.value = ''; return; }
        }
        previewRows.value = createPreviewRows(validateImportRows(rows, importOpts.value), importOpts.value);
        previewTab.value = invalidRows.value.length && !validRows.value.length ? 'invalid' : 'valid';
        step.value = 'preview';
    } catch {
        toast.error('Không đọc được file. Hãy dùng đúng mẫu Excel.');
    } finally {
        parsing.value = false;
        e.target.value = '';
    }
}

function onEditRow(row) {
    revalidatePreviewRow(row, importOpts.value);
}

function onSubmit() {
    if (!canSubmit.value) return;
    importing.value = true;
    router.post('/contracts/import', { rows: rowsToPayload(validRows.value) }, {
        preserveScroll: true,
        onSuccess: () => {
            emit('imported');
            resetImport();
            emit('close');
        },
        onError: () => toast.error('Nhập thất bại. Kiểm tra lại dữ liệu.'),
        onFinish: () => { importing.value = false; },
    });
}

function resetImport() {
    step.value = 'upload';
    previewRows.value = [];
    previewTab.value = 'valid';
}

// ── Export ──
const exporting = ref(false);
async function onExport() {
    exporting.value = true;
    try {
        const { data } = await axios.get('/contracts/export');
        downloadContractExport(data.contracts || []);
        toast.success('Đã xuất báo cáo hợp đồng.');
    } catch {
        toast.error('Xuất thất bại.');
    } finally {
        exporting.value = false;
    }
}

function close() {
    resetImport();
    emit('close');
}
</script>

<template>
  <Modal
    :show="show"
    title="Dữ liệu hợp đồng"
    max-width="max-w-5xl"
    @close="close"
  >
    <!-- Tabs -->
    <div class="mb-4 flex gap-1 rounded-lg bg-slate-100 p-1">
      <button
        v-for="t in tabs"
        :key="t.key"
        type="button"
        class="flex flex-1 items-center justify-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
        :class="[
          tab === t.key ? 'bg-white text-brand shadow-sm' : 'text-slate-500 hover:text-slate-700',
          t.disabled ? 'cursor-not-allowed opacity-40' : '',
        ]"
        :disabled="t.disabled"
        @click="tab = t.key"
      >
        <AppIcon
          :name="t.icon"
          :size="15"
        />
        {{ t.label }}
      </button>
    </div>

    <!-- IMPORT -->
    <div v-if="tab === 'import'">
      <div
        v-if="step === 'upload'"
        class="space-y-4"
      >
        <p class="text-sm text-slate-500">
          Tải mẫu Excel, điền dữ liệu rồi tải lên. Tối đa 200 dòng/lần.
        </p>
        <div class="flex flex-wrap gap-3">
          <button
            type="button"
            class="btn-ghost"
            @click="onDownloadTemplate"
          >
            <AppIcon
              name="download"
              :size="15"
            /> Tải mẫu Excel
          </button>
          <label class="btn-primary cursor-pointer">
            <AppIcon
              name="upload"
              :size="15"
            />
            {{ parsing ? 'Đang đọc…' : 'Chọn file' }}
            <input
              type="file"
              accept=".xlsx,.xls"
              class="hidden"
              :disabled="parsing"
              @change="onFileChange"
            >
          </label>
        </div>
      </div>

      <div
        v-else
        class="space-y-3"
      >
        <div class="flex items-center gap-2 text-sm">
          <button
            type="button"
            class="rounded-md px-3 py-1 font-medium"
            :class="previewTab === 'valid' ? 'bg-emerald-100 text-emerald-700' : 'text-slate-500'"
            @click="previewTab = 'valid'"
          >
            Hợp lệ ({{ validRows.length }})
          </button>
          <button
            type="button"
            class="rounded-md px-3 py-1 font-medium"
            :class="previewTab === 'invalid' ? 'bg-rose-100 text-rose-700' : 'text-slate-500'"
            @click="previewTab = 'invalid'"
          >
            Lỗi ({{ invalidRows.length }})
          </button>
          <button
            type="button"
            class="ml-auto text-sm text-slate-400 hover:text-slate-600"
            @click="resetImport"
          >
            ← Chọn file khác
          </button>
        </div>

        <div class="max-h-80 overflow-auto rounded-lg border border-slate-200">
          <table class="w-full text-sm">
            <thead class="sticky top-0 bg-slate-50 text-left text-xs text-slate-500">
              <tr>
                <th class="px-2 py-1.5 font-medium">
                  Tên hợp đồng
                </th>
                <th class="px-2 py-1.5 font-medium">
                  NCC
                </th>
                <th class="px-2 py-1.5 font-medium">
                  Chi phí năm
                </th>
                <th class="px-2 py-1.5 font-medium">
                  Hết hạn
                </th>
                <th
                  v-if="previewTab === 'invalid'"
                  class="px-2 py-1.5 font-medium"
                >
                  Lỗi
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in displayedRows"
                :key="row.line"
                class="border-t border-slate-100"
              >
                <td class="px-2 py-1.5">
                  <input
                    v-model="row.edit.name"
                    class="input w-full"
                    @input="onEditRow(row)"
                  >
                </td>
                <td class="px-2 py-1.5 text-slate-600">
                  {{ row.edit.vendor_name }}
                </td>
                <td class="px-2 py-1.5">
                  <input
                    v-model="row.edit.annual_cost"
                    type="number"
                    class="input w-28"
                    @input="onEditRow(row)"
                  >
                </td>
                <td class="px-2 py-1.5">
                  <input
                    v-model="row.edit.expiry_date"
                    type="date"
                    class="input"
                    @input="onEditRow(row)"
                  >
                </td>
                <td
                  v-if="previewTab === 'invalid'"
                  class="px-2 py-1.5 text-xs text-rose-600"
                >
                  {{ row.errors.join('; ') }}
                </td>
              </tr>
              <tr v-if="!displayedRows.length">
                <td
                  colspan="5"
                  class="px-2 py-6 text-center text-slate-400"
                >
                  Không có dòng nào.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="flex justify-end gap-2">
          <button
            type="button"
            class="btn-ghost"
            @click="close"
          >
            Đóng
          </button>
          <button
            type="button"
            class="btn-primary"
            :disabled="!canSubmit"
            @click="onSubmit"
          >
            {{ importing ? 'Đang nhập…' : `Xác nhận nhập ${validRows.length} dòng` }}
          </button>
        </div>
      </div>
    </div>

    <!-- EXPORT -->
    <div
      v-else-if="tab === 'export'"
      class="space-y-4"
    >
      <p class="text-sm text-slate-500">
        Xuất toàn bộ hợp đồng ra Excel có định dạng: Tổng quan, Chi tiết, và Sắp hết hạn.
      </p>
      <button
        type="button"
        class="btn-primary"
        :disabled="exporting"
        @click="onExport"
      >
        <AppIcon
          name="download"
          :size="15"
        />
        {{ exporting ? 'Đang xuất…' : 'Xuất Excel' }}
      </button>
    </div>

    <!-- RECONCILE -->
    <div
      v-else
      class="space-y-3"
    >
      <p class="text-sm text-slate-500">
        Đối soát dữ liệu hợp đồng — phát hiện thiếu thông tin tài chính / ngày tháng.
        Tính năng đầy đủ sẽ bổ sung ở giai đoạn sau.
      </p>
      <div class="rounded-lg border border-dashed border-slate-200 p-6 text-center text-sm text-slate-400">
        Chưa có cảnh báo đối soát.
      </div>
    </div>
  </Modal>
</template>
