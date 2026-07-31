<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Modal from '@/Components/Ui/Modal.vue';
import { useToast } from '@/shared/composables/useToast';
import {
    downloadTemplateImportFile,
    parseTemplateImportFile,
    validateTemplateRows,
    templateRowToPayload,
} from '@/modules/evaluation-template/composables/useEvaluationTemplateImport.js';

const props = defineProps({
    show: { type: Boolean, default: false },
    positions: { type: Array, default: () => [] },
    criteriaOptions: { type: Array, default: () => [] },
});

const emit = defineEmits(['close']);
const toast = useToast();

const step = ref('guide');
const parsing = ref(false);
const importing = ref(false);
const parseErrors = ref([]);
const previewRows = ref([]);
const previewTab = ref('valid');

watch(() => props.show, (open) => {
    if (open) {
        step.value = 'guide';
        parsing.value = false;
        importing.value = false;
        parseErrors.value = [];
        previewRows.value = [];
        previewTab.value = 'valid';
    }
});

const validRows = computed(() => previewRows.value.filter((r) => r._valid));
const invalidRows = computed(() => previewRows.value.filter((r) => !r._valid));

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
        step.value = 'preview';
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
</script>

<template>
  <Modal
    :show="show"
    title="Nhập mẫu đánh giá từ Excel"
    max-width="max-w-5xl"
    @close="close"
  >
    <div
      v-if="step === 'guide'"
      class="space-y-4"
    >
      <ol class="list-decimal space-y-2 pl-5 text-sm text-slate-600">
        <li>Tải file mẫu Excel (có sheet hướng dẫn tham chiếu vị trí & tiêu chí).</li>
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
          @click="step = 'guide'"
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
  </Modal>
</template>
