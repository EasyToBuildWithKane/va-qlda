<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import Modal from '@/Components/Ui/Modal.vue';
import {
    downloadCredentialTemplate,
    parseCredentialFile,
    exportCredentialWorkbook,
    reconcileCredentials,
} from '@/modules/credential/composables/useCredentialImport.js';
import { useToast } from '@/shared/composables/useToast';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    rows: { type: Array, default: () => [] },
    options: { type: Object, default: () => ({}) },
    canManage: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const toast = useToast();
const tab = ref('import');
const parsing = ref(false);
const previewRows = ref([]);
const parseErrors = ref([]);
const reconcile = computed(() => reconcileCredentials(props.rows));

function close() {
    emit('update:modelValue', false);
    tab.value = 'import';
    previewRows.value = [];
    parseErrors.value = [];
}

watch(() => props.modelValue, (v) => {
    if (!v) close();
});

async function onFile(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    parsing.value = true;
    try {
        const { rows, errors } = await parseCredentialFile(file);
        previewRows.value = rows;
        parseErrors.value = errors;
        if (errors.length) toast.error('File có lỗi — xem danh sách bên dưới.');
    } catch {
        toast.error('Không đọc được file Excel.');
    } finally {
        parsing.value = false;
    }
}

function submitImport() {
    if (!props.canManage || !previewRows.value.length) return;
    router.post(route('credentials.import'), { rows: previewRows.value }, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Đã nhập tài khoản.');
            close();
        },
        onError: () => toast.error('Nhập thất bại — kiểm tra dữ liệu.'),
    });
}

function exportFiltered() {
    exportCredentialWorkbook(props.rows);
    toast.success('Đã xuất Excel.');
}
</script>

<template>
  <Modal
    :show="modelValue"
    max-width="2xl"
    @close="close"
  >
    <div class="p-6">
      <h2 class="font-display text-lg font-semibold text-slate-900">
        Dữ liệu tài khoản
      </h2>
      <div class="mt-4 flex gap-2 border-b border-slate-200 pb-2">
        <button
          v-for="t in ['import', 'export', 'reconcile']"
          :key="t"
          type="button"
          class="rounded-lg px-3 py-1.5 text-xs font-medium capitalize"
          :class="tab === t ? 'bg-brand/10 text-brand' : 'text-slate-600'"
          @click="tab = t"
        >
          {{ t === 'import' ? 'Nhập' : t === 'export' ? 'Xuất' : 'Đối soát' }}
        </button>
      </div>

      <div
        v-if="tab === 'import'"
        class="mt-4 space-y-3"
      >
        <ol class="list-decimal space-y-1 pl-4 text-sm text-slate-600">
          <li>Tải file mẫu (.xlsx) — sheet «Nhập liệu», tối đa <strong>200 dòng</strong>.</li>
          <li>Điền cột bắt buộc (*): Tên, Loại, Hệ thống (đúng enum trong mẫu).</li>
          <li>Chọn file → kiểm tra preview → Nhập khi không còn lỗi.</li>
        </ol>
        <div class="flex flex-wrap gap-2">
          <button
            type="button"
            class="btn-ghost h-9 px-3 text-xs"
            @click="downloadCredentialTemplate"
          >
            Tải file mẫu
          </button>
          <label class="btn-primary inline-flex h-9 cursor-pointer items-center px-3 text-xs">
            Chọn file
            <input
              type="file"
              accept=".xlsx,.xls,.csv"
              class="sr-only"
              @change="onFile"
            >
          </label>
        </div>
        <ul
          v-if="parseErrors.length"
          class="text-xs text-rose-600"
        >
          <li
            v-for="(err, i) in parseErrors"
            :key="i"
          >
            {{ err }}
          </li>
        </ul>
        <p
          v-if="previewRows.length"
          class="text-sm"
        >
          Hợp lệ: {{ previewRows.length }} dòng
        </p>
        <button
          v-if="canManage && previewRows.length"
          type="button"
          class="btn-primary h-9 px-4 text-xs"
          :disabled="parsing"
          @click="submitImport"
        >
          Nhập {{ previewRows.length }} bản ghi
        </button>
      </div>

      <div
        v-else-if="tab === 'export'"
        class="mt-4"
      >
        <p class="text-sm text-slate-600">
          Xuất danh sách đang hiển thị ({{ rows.length }} bản ghi).
        </p>
        <button
          type="button"
          class="btn-primary mt-3 h-9 px-4 text-xs"
          @click="exportFiltered"
        >
          Xuất Excel
        </button>
      </div>

      <div
        v-else
        class="mt-4"
      >
        <p class="text-sm">
          Cảnh báo: {{ reconcile.summary.warnings }} · Gợi ý: {{ reconcile.summary.info }}
        </p>
        <ul class="mt-2 max-h-48 space-y-1 overflow-y-auto text-xs text-slate-600">
          <li
            v-for="(issue, i) in reconcile.issues.slice(0, 30)"
            :key="i"
          >
            {{ issue.message }}
          </li>
        </ul>
      </div>
    </div>
  </Modal>
</template>
