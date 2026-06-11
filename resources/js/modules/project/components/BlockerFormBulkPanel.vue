<script setup>
import { ref, computed, watch, onUnmounted, inject } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import PersonSelect from '@/modules/project/components/PersonSelect.vue';
import SearchSelect from '@/shared/ui/SearchSelect.vue';
import FieldTooltip from '@/shared/ui/FieldTooltip.vue';
import { valueLabelOptions } from '@/shared/utils/selectOptions';
import { useToast } from '@/shared/composables/useToast';
import {
    BULK_MAX_ROWS,
    bulkValidationSummary,
    getBlockerBulkSampleText,
    nextBulkRowId,
    parseBulkText,
    rowsToBulkText,
    validateBlockerBulkRows,
} from '@/composables/useBlockerBulkCreate';

const props = defineProps({
    projects: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
    severityOptions: { type: Array, default: () => [] },
    statusOptions: { type: Array, default: () => [] },
    lockProject: { type: Boolean, default: false },
    defaultProjectId: { type: Number, default: null },
    initialDefaults: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['saved', 'dirty-change']);
const modalClose = inject('modalClose', () => {});
const toast = useToast();

const bulkText = ref('');
const bulkRows = ref([]);
const view = ref('compose');
const showTips = ref(true);

const defaults = ref({
    project_id: null,
    severity: 'medium',
    status: 'open',
    owner_id: null,
    due_date: null,
});

const applyInitialDefaults = () => {
    const d = props.initialDefaults || {};
    defaults.value = {
        project_id: d.project_id ?? props.defaultProjectId ?? null,
        severity: d.severity ?? 'medium',
        status: d.status ?? 'open',
        owner_id: d.owner_id ?? null,
        due_date: d.due_date ?? null,
    };
};

const reset = () => {
    bulkText.value = '';
    bulkRows.value = [];
    view.value = 'compose';
    showTips.value = true;
    applyInitialDefaults();
};

defineExpose({
    reset,
    getDraftSnapshot: () => ({
        bulkText: bulkText.value,
        bulkRows: bulkRows.value.map((r) => ({ ...r })),
        view: view.value,
        defaults: { ...defaults.value },
    }),
    applyDraftSnapshot: (snap) => {
        if (!snap) return;
        bulkText.value = snap.bulkText ?? '';
        bulkRows.value = (snap.bulkRows ?? []).map((r) => ({ ...r }));
        view.value = snap.view ?? 'compose';
        if (snap.defaults) {
            defaults.value = { ...snap.defaults };
        }
    },
});

watch(
    () => [props.initialDefaults, props.defaultProjectId],
    () => applyInitialDefaults(),
    { deep: true, immediate: true },
);

const isDirty = computed(() => !!bulkText.value.trim() || bulkRows.value.length > 0);
watch(isDirty, (v) => emit('dirty-change', v), { immediate: true });

const validatedRows = computed(() => validateBlockerBulkRows(bulkRows.value));
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

const lineCountInText = computed(() => parseBulkText(bulkText.value).length);

const syncRowsFromText = () => {
    bulkRows.value = parseBulkText(bulkText.value);
    if (!bulkRows.value.length) {
        toast.warning('Chưa có dòng hợp lệ. Mỗi dòng là một tiêu đề vướng mắc.');
        return false;
    }
    if (lineCountInText.value > BULK_MAX_ROWS) {
        toast.warning(`Chỉ lấy ${BULK_MAX_ROWS} dòng đầu (giới hạn mỗi lần ghi nhận).`);
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
        ? `${bulkText.value.trim()}\n${getBlockerBulkSampleText()}`
        : getBlockerBulkSampleText();
};

const clearAll = () => {
    bulkText.value = '';
    bulkRows.value = [];
    view.value = 'compose';
};

const addEmptyRow = () => {
    if (bulkRows.value.length >= BULK_MAX_ROWS) {
        toast.warning(`Tối đa ${BULK_MAX_ROWS} vướng mắc mỗi lần.`);
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
        toast.error('Chọn ít nhất một dòng hợp lệ để ghi nhận.');
        return;
    }

    bulkForm.defaults = { ...defaults.value };
    bulkForm.rows = rows.map((r) => ({ title: r.title.trim() }));
    bulkForm.post('/blockers/bulk-create', {
        preserveScroll: true,
        onSuccess: () => {
            toast.success(`Đã ghi nhận ${rows.length} vướng mắc`);
            emit('saved');
        },
        onError: () => toast.error('Không ghi nhận được — kiểm tra lại dữ liệu.'),
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

const severitySelectOptions = computed(() => valueLabelOptions(props.severityOptions));
const statusSelectOptions = computed(() => valueLabelOptions(props.statusOptions));

const projectLabel = computed(() => {
    if (!defaults.value.project_id) return 'Thắc mắc chung';
    const p = props.projects.find((x) => x.id === defaults.value.project_id);
    if (!p) return null;
    return p.code ? `${p.name} (${p.code})` : p.name;
});
</script>

<template>
  <div class="space-y-3">
    <div
      v-if="showTips"
      class="flex gap-2 rounded-lg border border-brand/15 bg-brand-50/40 px-3 py-2 text-xs text-slate-700"
    >
      <AppIcon
        name="info"
        :size="16"
        class="mt-0.5 shrink-0 text-brand"
      />
      <div class="min-w-0 flex-1">
        <p class="font-semibold text-slate-800">
          Ghi nhận nhiều vướng mắc một lần
        </p>
        <p class="mt-0.5 text-slate-600">
          Mỗi dòng = một tiêu đề · dán từ Excel được · cài đặt chung áp dụng cho tất cả.
        </p>
      </div>
      <button
        type="button"
        class="shrink-0 text-slate-400 hover:text-slate-600"
        aria-label="Ẩn gợi ý"
        @click="showTips = false"
      >
        <AppIcon
          name="close"
          :size="14"
        />
      </button>
    </div>

    <section class="rounded-lg border border-slate-200 bg-slate-50/80 p-3">
      <h3 class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
        Cài đặt chung
      </h3>
      <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
        <div
          v-if="!lockProject"
          class="sm:col-span-2 lg:col-span-2"
        >
          <label class="label flex items-center gap-1 text-[11px]">
            Dự án
            <FieldTooltip text="Để trống → nhóm «Thắc mắc chung»." />
          </label>
          <SearchSelect
            v-model="defaults.project_id"
            :options="projects"
            placeholder="Tìm dự án…"
            search-placeholder="Tìm dự án…"
            clearable
          />
        </div>
        <div v-else-if="projectLabel">
          <label class="label text-[11px]">Dự án</label>
          <p class="input flex h-9 items-center truncate bg-white text-sm text-slate-700">
            {{ projectLabel }}
          </p>
        </div>
        <div>
          <label class="label text-[11px]">Mức độ</label>
          <SearchSelect
            v-model="defaults.severity"
            :options="severitySelectOptions"
            placeholder="Mức độ…"
            :clearable="false"
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
          <label class="label text-[11px]">Hạn xử lý</label>
          <input
            v-model="defaults.due_date"
            type="date"
            class="input h-9 text-sm"
          >
        </div>
        <div class="sm:col-span-2 lg:col-span-5">
          <label class="label text-[11px]">Người phụ trách</label>
          <PersonSelect
            v-model="defaults.owner_id"
            :options="employees"
            placeholder="Tìm & chọn…"
          />
        </div>
      </div>
    </section>

    <div class="flex items-center gap-1.5 text-xs">
      <button
        type="button"
        class="flex flex-1 items-center justify-center gap-1 rounded-md px-2 py-1.5 font-semibold transition sm:flex-none"
        :class="view === 'compose'
          ? 'bg-brand text-white'
          : 'bg-slate-100 text-slate-600'"
        @click="view = 'compose'"
      >
        <span class="grid h-4 w-4 place-items-center rounded-full bg-white/20 text-[9px]">1</span>
        Danh sách
      </button>
      <AppIcon
        name="chevron"
        :size="12"
        class="hidden shrink-0 text-slate-300 sm:block"
      />
      <button
        type="button"
        class="flex flex-1 items-center justify-center gap-1 rounded-md px-2 py-1.5 font-semibold transition sm:flex-none"
        :class="view === 'preview'
          ? 'bg-brand text-white'
          : 'bg-slate-100 text-slate-600'"
        :disabled="!bulkRows.length && view !== 'preview'"
        @click="bulkRows.length ? (view = 'preview') : goPreview()"
      >
        <span class="grid h-4 w-4 place-items-center rounded-full bg-white/20 text-[9px]">2</span>
        Xem trước
      </button>
    </div>

    <div
      v-show="view === 'compose'"
      class="space-y-2"
    >
      <div class="flex flex-wrap items-center justify-between gap-2">
        <label class="label mb-0 text-sm">Tiêu đề vướng mắc <span class="text-rose-500">*</span></label>
        <div class="flex gap-1">
          <button
            type="button"
            class="btn-ghost py-1 text-xs"
            @click="insertSample"
          >
            Chèn mẫu
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
          rows="8"
          class="input resize-y font-mono text-sm leading-relaxed"
          :placeholder="`Mỗi dòng một vướng mắc (tối đa ${BULK_MAX_ROWS})\n\nVí dụ:\nChậm duyệt hồ sơ pháp lý\nLỗi đồng bộ dữ liệu học sinh`"
        />
        <div
          class="pointer-events-none absolute bottom-2 right-2 rounded bg-slate-800/80 px-1.5 py-0.5 text-[10px] font-medium text-white"
        >
          {{ lineCountInText }} / {{ BULK_MAX_ROWS }}
        </div>
      </div>
      <div class="flex justify-end">
        <button
          type="button"
          class="btn-primary text-sm"
          :disabled="!bulkText.trim()"
          @click="goPreview"
        >
          Tiếp tục
          <AppIcon
            name="chevron"
            :size="14"
          />
        </button>
      </div>
    </div>

    <div
      v-show="view === 'preview'"
      class="space-y-2"
    >
      <div class="flex flex-wrap items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs">
        <span class="font-semibold text-emerald-700">{{ summary.valid }} hợp lệ</span>
        <span
          v-if="summary.invalid"
          class="text-amber-700"
        >{{ summary.invalid }} lỗi</span>
        <span
          v-if="summary.duplicates"
          class="text-slate-500"
        >{{ summary.duplicates }} trùng</span>
        <span class="ml-auto text-slate-400">{{ summary.selected }} / {{ summary.total }} chọn</span>
      </div>

      <div class="overflow-hidden rounded-lg border border-slate-200">
        <div class="flex items-center gap-2 border-b border-slate-100 bg-slate-50 px-3 py-1.5 text-[10px] font-semibold uppercase text-slate-500">
          <input
            type="checkbox"
            class="rounded accent-brand"
            :checked="allSelected"
            @change="toggleSelectAll($event.target.checked)"
          >
          <span class="w-5">#</span>
          <span class="flex-1">Tiêu đề</span>
          <span class="w-20 text-right">Kiểm tra</span>
        </div>
        <ul class="max-h-[min(260px,38vh)] divide-y divide-slate-100 overflow-y-auto">
          <li
            v-for="(row, idx) in bulkRows"
            :key="row.id"
            class="flex items-start gap-2 px-3 py-1.5"
            :class="rowHasErrors(row.id) ? 'bg-amber-50/60' : ''"
          >
            <input
              v-model="row.selected"
              type="checkbox"
              class="mt-2 rounded accent-brand"
              :disabled="rowHasErrors(row.id)"
            >
            <span class="mt-1.5 w-5 shrink-0 text-xs text-slate-400">{{ idx + 1 }}</span>
            <input
              v-model="row.title"
              type="text"
              class="input min-w-0 flex-1 py-1 text-sm"
              :class="rowHasErrors(row.id) ? 'border-amber-300' : ''"
            >
            <div class="w-20 shrink-0 pt-1 text-right">
              <span
                v-if="!rowHasErrors(row.id)"
                class="text-[10px] font-medium text-emerald-600"
              >OK</span>
              <template v-else>
                <span
                  v-for="err in rowErrors(row.id)"
                  :key="err.code"
                  class="block text-[10px] text-amber-700"
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
        class="btn-ghost w-full border border-dashed border-slate-200 text-xs"
        @click="addEmptyRow"
      >
        + Thêm dòng
      </button>

      <div class="flex flex-wrap justify-between gap-2 pt-1">
        <button
          type="button"
          class="btn-ghost text-sm"
          @click="backToCompose"
        >
          Sửa danh sách
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
            <span v-if="submitting">Đang ghi nhận…</span>
            <span v-else>Ghi nhận {{ summary.valid }} vướng mắc</span>
          </button>
        </div>
      </div>
    </div>

    <div
      v-if="view === 'compose'"
      class="flex justify-end border-t border-slate-100 pt-3"
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
