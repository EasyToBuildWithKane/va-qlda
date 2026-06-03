<script setup>
import { ref, computed, watch, onUnmounted, inject } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import PersonMultiSelect from '@/modules/project/components/PersonMultiSelect.vue';
import SearchSelect from '@/shared/ui/SearchSelect.vue';
import { valueLabelOptions } from '@/shared/utils/selectOptions';
import { useToast } from '@/shared/composables/useToast';
import {
    BULK_MAX_ROWS,
    bulkValidationSummary,
    getBulkSampleText,
    nextBulkRowId,
    parseBulkText,
    rowsToBulkText,
    validateBulkRows,
} from '@/composables/useTaskBulkCreate';

const props = defineProps({
    projectId: { type: Number, required: true },
    sprints: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
    statusOptions: { type: Array, default: () => [] },
    priorityOptions: { type: Array, default: () => [] },
    phaseOptions: { type: Array, default: () => [] },
    existingTitles: { type: Array, default: () => [] },
    defaultStatus: { type: String, default: 'todo' },
    initialDefaults: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['saved', 'dirty-change']);
const modalClose = inject('modalClose', () => {});
const toast = useToast();

const bulkText = ref('');
const bulkRows = ref([]);
const view = ref('compose'); // compose | preview
const showTips = ref(true);

const defaults = ref({
    sprint_id: null,
    status: props.defaultStatus,
    priority: 'medium',
    phase: 'development',
    assignee_ids: [],
    reviewer_id: null,
    reporter_id: null,
});

const applyInitialDefaults = () => {
    const d = props.initialDefaults || {};
    defaults.value = {
        sprint_id: d.sprint_id ?? null,
        status: d.status ?? props.defaultStatus,
        priority: d.priority ?? 'medium',
        phase: d.phase ?? 'development',
        assignee_ids: [...(d.assignee_ids || [])],
        reviewer_id: d.reviewer_id ?? null,
        reporter_id: d.reporter_id ?? null,
    };
};

const reset = () => {
    bulkText.value = '';
    bulkRows.value = [];
    view.value = 'compose';
    showTips.value = true;
    applyInitialDefaults();
};

defineExpose({ reset });

watch(
    () => props.initialDefaults,
    () => applyInitialDefaults(),
    { deep: true, immediate: true },
);

const isDirty = computed(() => !!bulkText.value.trim() || bulkRows.value.length > 0);
watch(isDirty, (v) => emit('dirty-change', v), { immediate: true });

const validatedRows = computed(() => validateBulkRows(bulkRows.value, props.existingTitles));
const summary = computed(() => bulkValidationSummary(validatedRows.value));

const errorsById = computed(() => {
    const map = new Map();
    for (const r of validatedRows.value) {
        map.set(r.id, r.errors || []);
    }
    return map;
});

const rowErrors = (id) => errorsById.value.get(id) || [];
const rowHasErrors = (id) => rowErrors(id).length > 0;

const lineCountInText = computed(() => {
    const parsed = parseBulkText(bulkText.value);
    return parsed.length;
});

const syncRowsFromText = () => {
    bulkRows.value = parseBulkText(bulkText.value);
    if (!bulkRows.value.length) {
        toast.warning('Chưa có dòng hợp lệ. Mỗi dòng là một tiêu đề công việc.');
        return false;
    }
    if (lineCountInText.value > BULK_MAX_ROWS) {
        toast.warning(`Chỉ lấy ${BULK_MAX_ROWS} dòng đầu (giới hạn mỗi lần tạo).`);
    }
    return true;
};

const goPreview = () => {
    if (!syncRowsFromText()) return;
    view.value = 'preview';
};

const backToCompose = () => {
    bulkText.value = rowsToBulkText(bulkRows.value);
    view.value = 'compose';
};

const insertSample = () => {
    bulkText.value = bulkText.value.trim()
        ? `${bulkText.value.trim()}\n${getBulkSampleText()}`
        : getBulkSampleText();
};

const clearAll = () => {
    bulkText.value = '';
    bulkRows.value = [];
    view.value = 'compose';
};

const addEmptyRow = () => {
    if (bulkRows.value.length >= BULK_MAX_ROWS) {
        toast.warning(`Tối đa ${BULK_MAX_ROWS} công việc mỗi lần.`);
        return;
    }
    bulkRows.value = [...bulkRows.value, { id: nextBulkRowId(), title: '', selected: true }];
};

const removeRow = (id) => {
    bulkRows.value = bulkRows.value.filter((r) => r.id !== id);
};

const toggleSelectAll = (checked) => {
    bulkRows.value = bulkRows.value.map((r) => ({
        ...r,
        selected: checked ? !rowHasErrors(r.id) : false,
    }));
};

const allSelected = computed(() => {
    const valid = bulkRows.value.filter((r) => !rowHasErrors(r.id));
    return valid.length > 0 && valid.every((r) => r.selected);
});

const bulkForm = useForm({ defaults: {}, rows: [] });
const submitting = computed(() => bulkForm.processing);

const submit = () => {
    const rows = bulkRows.value.filter((r) => r.selected && !rowHasErrors(r.id));
    if (!rows.length) {
        toast.error('Chọn ít nhất một dòng hợp lệ để tạo.');
        return;
    }

    bulkForm.defaults = { ...defaults.value };
    bulkForm.rows = rows.map((r) => ({ title: r.title }));
    bulkForm.post(`/projects/${props.projectId}/tasks/bulk`, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success(`Đã tạo ${rows.length} công việc`);
            emit('saved');
        },
        onError: () => toast.error('Không tạo được — kiểm tra lại dữ liệu.'),
    });
};

const handleKeydown = (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        e.preventDefault();
        if (view.value === 'compose') goPreview();
        else if (summary.value.canSubmit) submit();
    }
};

watch(view, (v) => {
    if (v === 'preview') document.addEventListener('keydown', handleKeydown);
    else document.removeEventListener('keydown', handleKeydown);
});

onUnmounted(() => document.removeEventListener('keydown', handleKeydown));

const selectedSprintName = computed(() => {
    if (!defaults.value.sprint_id) return null;
    return props.sprints.find((s) => s.id === defaults.value.sprint_id)?.name;
});

const statusSelectOptions = computed(() => valueLabelOptions(props.statusOptions));
const prioritySelectOptions = computed(() => valueLabelOptions(props.priorityOptions));
const phaseSelectOptions = computed(() => valueLabelOptions(props.phaseOptions));
</script>

<template>
  <div class="space-y-4">
    <!-- Tips -->
    <div
      v-if="showTips"
      class="flex gap-3 rounded-lg border border-brand/15 bg-gradient-to-r from-brand-50/80 to-slate-50 px-3 py-2.5 text-xs text-slate-700 dark:border-brand/30 dark:from-brand-950/30 dark:to-slate-900/50 dark:text-slate-300"
    >
      <AppIcon
        name="info"
        :size="18"
        class="mt-0.5 shrink-0 text-brand"
      />
      <div class="min-w-0 flex-1 space-y-1">
        <p class="font-semibold text-slate-800 dark:text-slate-100">
          Tạo hàng loạt nhanh
        </p>
        <ul class="list-inside list-disc space-y-0.5 text-slate-600 dark:text-slate-400">
          <li>Mỗi dòng = một công việc · dán từ Excel (một cột) được</li>
          <li>Cài đặt chung (Sprint, ưu tiên, người làm) áp dụng cho tất cả</li>
          <li><kbd class="rounded border border-slate-200 bg-white px-1 dark:border-slate-600 dark:bg-slate-800">Ctrl</kbd>+<kbd class="rounded border border-slate-200 bg-white px-1 dark:border-slate-600 dark:bg-slate-800">Enter</kbd> xem trước / tạo</li>
        </ul>
      </div>
      <button
        type="button"
        class="shrink-0 text-slate-400 hover:text-slate-600"
        @click="showTips = false"
      >
        <AppIcon
          name="close"
          :size="14"
        />
      </button>
    </div>

    <!-- Shared defaults -->
    <section class="rounded-lg border border-slate-200 bg-slate-50/80 p-3 dark:border-slate-700 dark:bg-slate-900/40">
      <h3 class="mb-2 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500">
        <AppIcon
          name="settings"
          :size="14"
        />
        Cài đặt chung
      </h3>
      <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div>
          <label class="label text-[11px]">Sprint</label>
          <SearchSelect
            v-model="defaults.sprint_id"
            :options="sprints"
            placeholder="Tìm sprint…"
            search-placeholder="Tìm sprint…"
          />
        </div>
        <div>
          <label class="label text-[11px]">Trạng thái</label>
          <SearchSelect
            v-model="defaults.status"
            :options="statusSelectOptions"
            placeholder="Trạng thái…"
            :clearable="false"
          />
        </div>
        <div>
          <label class="label text-[11px]">Ưu tiên</label>
          <SearchSelect
            v-model="defaults.priority"
            :options="prioritySelectOptions"
            placeholder="Ưu tiên…"
            :clearable="false"
          />
        </div>
        <div>
          <label class="label text-[11px]">Giai đoạn</label>
          <SearchSelect
            v-model="defaults.phase"
            :options="phaseSelectOptions"
            placeholder="Giai đoạn…"
            :clearable="false"
          />
        </div>
      </div>
      <div class="mt-3">
        <label class="label text-[11px]">Người thực hiện (áp dụng tất cả)</label>
        <PersonMultiSelect
          v-model="defaults.assignee_ids"
          :options="employees"
          placeholder="Tìm & thêm người thực hiện…"
        />
      </div>
      <p
        v-if="selectedSprintName"
        class="mt-2 text-[11px] text-brand"
      >
        Sẽ gán vào sprint: <strong>{{ selectedSprintName }}</strong>
      </p>
    </section>

    <!-- Step indicator -->
    <div class="flex items-center gap-2 text-xs">
      <button
        type="button"
        class="flex items-center gap-1.5 rounded-full px-3 py-1 font-semibold transition"
        :class="view === 'compose' ? 'bg-brand text-white' : 'bg-slate-100 text-slate-600 dark:bg-slate-800'"
        @click="view = 'compose'"
      >
        <span class="grid h-5 w-5 place-items-center rounded-full bg-white/20 text-[10px]">1</span>
        Nhập danh sách
      </button>
      <AppIcon
        name="chevron"
        :size="14"
        class="text-slate-300"
      />
      <button
        type="button"
        class="flex items-center gap-1.5 rounded-full px-3 py-1 font-semibold transition"
        :class="view === 'preview' ? 'bg-brand text-white' : 'bg-slate-100 text-slate-600 dark:bg-slate-800'"
        :disabled="!bulkRows.length && view !== 'preview'"
        @click="bulkRows.length ? (view = 'preview') : goPreview()"
      >
        <span class="grid h-5 w-5 place-items-center rounded-full bg-white/20 text-[10px]">2</span>
        Xem trước & tạo
      </button>
    </div>

    <!-- Compose -->
    <div
      v-show="view === 'compose'"
      class="space-y-2"
    >
      <div class="flex flex-wrap items-center justify-between gap-2">
        <label class="label mb-0">Danh sách tiêu đề <span class="text-rose-500">*</span></label>
        <div class="flex flex-wrap gap-1.5">
          <button
            type="button"
            class="btn-ghost py-1 text-xs"
            @click="insertSample"
          >
            <AppIcon
              name="template"
              :size="14"
            /> Chèn mẫu
          </button>
          <button
            type="button"
            class="btn-ghost py-1 text-xs text-slate-500"
            :disabled="!bulkText.trim()"
            @click="clearAll"
          >
            Xoá hết
          </button>
        </div>
      </div>
      <div class="relative">
        <textarea
          v-model="bulkText"
          rows="10"
          class="input resize-y font-mono text-sm leading-relaxed"
          :placeholder="`Mỗi dòng một công việc (tối đa ${BULK_MAX_ROWS})\n\nVí dụ:\nThiết kế wireframe trang chủ\nAPI đăng nhập OAuth\nKiểm thử regression sprint 3`"
        />
        <div
          class="pointer-events-none absolute bottom-2 right-2 rounded-md bg-slate-900/75 px-2 py-0.5 text-[10px] font-medium text-white"
        >
          {{ lineCountInText }} / {{ BULK_MAX_ROWS }} dòng
        </div>
      </div>
      <div class="flex justify-end">
        <button
          type="button"
          class="btn-primary text-sm"
          :disabled="!bulkText.trim()"
          @click="goPreview"
        >
          Tiếp tục xem trước
          <AppIcon
            name="chevron"
            :size="14"
          />
        </button>
      </div>
    </div>

    <!-- Preview -->
    <div
      v-show="view === 'preview'"
      class="space-y-3"
    >
      <div class="flex flex-wrap items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs dark:border-slate-700 dark:bg-slate-900">
        <span class="font-semibold text-emerald-700 dark:text-emerald-400">{{ summary.valid }} hợp lệ</span>
        <span
          v-if="summary.invalid"
          class="text-amber-700 dark:text-amber-400"
        >{{ summary.invalid }} lỗi</span>
        <span
          v-if="summary.duplicates"
          class="text-slate-500"
        >{{ summary.duplicates }} trùng</span>
        <span
          v-if="summary.exists"
          class="text-slate-500"
        >{{ summary.exists }} đã tồn tại</span>
        <span class="ml-auto text-slate-400">{{ summary.selected }} / {{ summary.total }} chọn</span>
      </div>

      <div class="max-h-[min(320px,40vh)] overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700">
        <div class="flex items-center gap-2 border-b border-slate-100 bg-slate-50 px-3 py-2 text-[11px] font-semibold uppercase text-slate-500 dark:border-slate-800 dark:bg-slate-900">
          <input
            type="checkbox"
            class="rounded accent-brand"
            :checked="allSelected"
            @change="toggleSelectAll($event.target.checked)"
          >
          <span class="w-6">#</span>
          <span class="flex-1">Tiêu đề công việc</span>
          <span class="w-24 text-right">Trạng thái</span>
        </div>
        <ul class="max-h-[min(280px,36vh)] divide-y divide-slate-100 overflow-y-auto dark:divide-slate-800">
          <li
            v-for="(row, idx) in bulkRows"
            :key="row.id"
            class="flex items-start gap-2 px-3 py-2 transition"
            :class="rowHasErrors(row.id) ? 'bg-amber-50/50 dark:bg-amber-950/20' : ''"
          >
            <input
              v-model="row.selected"
              type="checkbox"
              class="mt-2 rounded accent-brand"
              :disabled="rowHasErrors(row.id)"
            >
            <span class="mt-1.5 w-6 shrink-0 text-xs text-slate-400">{{ idx + 1 }}</span>
            <input
              v-model="row.title"
              type="text"
              class="input min-w-0 flex-1 py-1 text-sm"
              :class="rowHasErrors(row.id) ? 'border-amber-300' : ''"
            >
            <div class="w-24 shrink-0 text-right">
              <span
                v-if="!rowHasErrors(row.id)"
                class="text-[10px] font-medium text-emerald-600"
              >OK</span>
              <template v-else>
                <span
                  v-for="err in rowErrors(row.id)"
                  :key="err.code"
                  class="block text-[10px] text-amber-700 dark:text-amber-400"
                  :title="err.message"
                >
                  {{ err.message }}
                </span>
              </template>
            </div>
            <button
              type="button"
              class="mt-1 shrink-0 text-slate-400 hover:text-rose-500"
              title="Xoá dòng"
              @click="removeRow(row.id)"
            >
              <AppIcon
                name="delete"
                :size="14"
              />
            </button>
          </li>
        </ul>
      </div>

      <button
        type="button"
        class="btn-ghost w-full border border-dashed border-slate-200 text-xs dark:border-slate-700"
        @click="addEmptyRow"
      >
        <AppIcon
          name="plus"
          :size="14"
        /> Thêm dòng trống
      </button>

      <div class="flex flex-wrap justify-between gap-2 pt-1">
        <button
          type="button"
          class="btn-ghost text-sm"
          @click="backToCompose"
        >
          <AppIcon
            name="back"
            :size="14"
          /> Sửa danh sách
        </button>
        <div class="flex gap-2">
          <button
            type="button"
            class="btn-ghost"
            @click="modalClose()"
          >
            Huỷ
          </button>
          <button
            type="button"
            class="btn-primary"
            :disabled="!summary.canSubmit || submitting"
            @click="submit"
          >
            <AppIcon
              v-if="!submitting"
              name="add"
              :size="15"
            />
            <span v-if="submitting">Đang tạo…</span>
            <span v-else>Tạo {{ summary.valid }} công việc</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Compose footer -->
    <div
      v-if="view === 'compose'"
      class="flex justify-end gap-2 border-t border-slate-100 pt-3 dark:border-slate-800"
    >
      <button
        type="button"
        class="btn-ghost"
        @click="modalClose()"
      >
        Huỷ
      </button>
    </div>
  </div>
</template>
