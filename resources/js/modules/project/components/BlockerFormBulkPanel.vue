<script setup>
import { ref, computed, watch, inject } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import PersonSelect from '@/modules/project/components/PersonSelect.vue';
import SearchSelect from '@/shared/ui/SearchSelect.vue';
import FieldTooltip from '@/shared/ui/FieldTooltip.vue';
import BlockerAttachmentsBlock from '@/modules/project/components/BlockerAttachmentsBlock.vue';
import BlockerFormSection from '@/modules/project/components/BlockerFormSection.vue';
import { valueLabelOptions } from '@/shared/utils/selectOptions';
import { useToast } from '@/shared/composables/useToast';
import { uploadAttachmentsForCreatedBlockers } from '@/composables/useBlockerAttachmentUpload';
import {
    BULK_MAX_ROWS,
    bulkValidationSummary,
    getBlockerBulkSampleText,
    nextBulkRowId,
    parseBulkText,
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
    canUploadAttachments: { type: Boolean, default: false },
});

const emit = defineEmits(['saved', 'dirty-change']);
const modalClose = inject('modalClose', () => {});
const toast = useToast();

const emptyBulkRow = (overrides = {}) => ({
    id: nextBulkRowId(),
    title: '',
    selected: true,
    pendingFiles: [],
    ...overrides,
});

const bulkRows = ref([emptyBulkRow()]);
const showTips = ref(true);
const pasteText = ref('');

const defaults = ref({
    project_id: null,
    severity: 'medium',
    status: 'open',
    owner_id: null,
    due_date: null,
});

const revokeRowFiles = (row) => {
    (row?.pendingFiles ?? []).forEach((p) => {
        if (p.preview) URL.revokeObjectURL(p.preview);
    });
};

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
    bulkRows.value.forEach(revokeRowFiles);
    bulkRows.value = [emptyBulkRow()];
    pasteText.value = '';
    showTips.value = true;
    applyInitialDefaults();
};

defineExpose({
    reset,
    getDraftSnapshot: () => ({
        bulkRows: bulkRows.value.map(({ id, title, selected }) => ({ id, title, selected })),
        defaults: { ...defaults.value },
        pasteText: pasteText.value,
    }),
    applyDraftSnapshot: (snap) => {
        if (!snap) return;
        bulkRows.value.forEach(revokeRowFiles);
        const rows = snap.bulkRows ?? [];
        bulkRows.value = rows.length
            ? rows.map((r) => emptyBulkRow({ id: r.id, title: r.title ?? '', selected: r.selected !== false }))
            : [emptyBulkRow()];
        pasteText.value = snap.pasteText ?? '';
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

watch(
    () => bulkRows.value.length,
    (n) => {
        if (n === 0) bulkRows.value = [emptyBulkRow()];
    },
);

const isDirty = computed(() =>
    bulkRows.value.some((r) => (r.title ?? '').trim() || (r.pendingFiles?.length ?? 0) > 0)
    || !!pasteText.value.trim(),
);
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

const setRowPendingFiles = (rowId, files) => {
    bulkRows.value = bulkRows.value.map((r) => (r.id === rowId ? { ...r, pendingFiles: files } : r));
};

const addEmptyRow = () => {
    if (bulkRows.value.length >= BULK_MAX_ROWS) {
        toast.warning(`Tối đa ${BULK_MAX_ROWS} vướng mắc mỗi lần.`);
        return;
    }
    bulkRows.value = [...bulkRows.value, emptyBulkRow()];
};

const removeRow = (id) => {
    if (bulkRows.value.length <= 1) {
        const row = bulkRows.value[0];
        revokeRowFiles(row);
        bulkRows.value = [emptyBulkRow()];
        return;
    }
    const row = bulkRows.value.find((r) => r.id === id);
    revokeRowFiles(row);
    bulkRows.value = bulkRows.value.filter((r) => r.id !== id);
};

const appendRowsFromText = (text) => {
    const parsed = parseBulkText(text);
    if (!parsed.length) {
        toast.warning('Chưa có dòng hợp lệ. Mỗi dòng là một tiêu đề vướng mắc.');
        return;
    }
    const room = BULK_MAX_ROWS - bulkRows.value.length;
    const slice = parsed.slice(0, Math.max(0, room));
    if (slice.length < parsed.length) {
        toast.warning(`Chỉ thêm ${slice.length} dòng (giới hạn ${BULK_MAX_ROWS} hàng).`);
    }
    bulkRows.value = [
        ...bulkRows.value,
        ...slice.map((r) => emptyBulkRow({ title: r.title, selected: true })),
    ];
};

const applyPaste = () => {
    if (!pasteText.value.trim()) return;
    appendRowsFromText(pasteText.value);
    pasteText.value = '';
};

const addSampleRows = () => {
    appendRowsFromText(getBlockerBulkSampleText());
};

const clearAll = () => {
    bulkRows.value.forEach(revokeRowFiles);
    bulkRows.value = [emptyBulkRow()];
    pasteText.value = '';
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
const submitting = computed(() => bulkForm.processing || uploadingAttachments.value);
const uploadingAttachments = ref(false);

const finishBulkSaved = (count) => {
    toast.success(`Đã ghi nhận ${count} vướng mắc`);
    emit('saved');
};

const submit = () => {
    const rows = bulkRows.value.filter((r) => r.selected && !rowHasErrors(r.id) && (r.title ?? '').trim());
    if (!rows.length) {
        toast.error('Chọn ít nhất một hàng có đề hợp lệ để ghi nhận.');
        return;
    }

    bulkForm.defaults = { ...defaults.value };
    bulkForm.rows = rows.map((r) => ({ title: r.title.trim() }));
    bulkForm.post('/blockers/bulk-create', {
        preserveScroll: true,
        onSuccess: (page) => {
            const ids = page.props.flash?.created_blocker_ids ?? [];
            const needsUpload = props.canUploadAttachments && rows.some((r) => (r.pendingFiles?.length ?? 0) > 0);
            if (needsUpload && ids.length) {
                uploadingAttachments.value = true;
                uploadAttachmentsForCreatedBlockers(rows, ids, {
                    onPartialError: () => toast.warning('Đã ghi nhận vướng mắc nhưng một số ảnh chưa tải được.'),
                    onFinish: () => {
                        uploadingAttachments.value = false;
                        finishBulkSaved(rows.length);
                    },
                });
                return;
            }
            finishBulkSaved(rows.length);
        },
        onError: () => toast.error('Không ghi nhận được — kiểm tra lại dữ liệu.'),
    });
};

const severitySelectOptions = computed(() => valueLabelOptions(props.severityOptions));
const statusSelectOptions = computed(() => valueLabelOptions(props.statusOptions));

const projectLabel = computed(() => {
    if (!defaults.value.project_id) return 'Thắc mắc chung';
    const p = props.projects.find((x) => x.id === defaults.value.project_id);
    if (!p) return null;
    return p.code ? `${p.name} (${p.code})` : p.name;
});

const pendingAttachmentCount = computed(() =>
    bulkRows.value.reduce((n, r) => n + (r.pendingFiles?.length ?? 0), 0),
);
</script>

<template>
  <div class="space-y-4">
    <div
      v-if="showTips"
      class="flex gap-2 rounded-lg border border-slate-200 bg-slate-50/90 px-3 py-2 text-xs text-slate-700"
    >
      <AppIcon
        name="info"
        :size="16"
        class="mt-0.5 shrink-0 text-brand"
      />
      <div class="min-w-0 flex-1">
        <p class="font-semibold text-slate-800">
          Mỗi hàng = một vướng mắc
        </p>
        <p class="mt-0.5 text-slate-600">
          Nhập đề và chọn ảnh riêng từng hàng. Có thể dán nhiều dòng đề bên dưới để thêm hàng nhanh.
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

    <BlockerFormSection
      title="Cài đặt chung"
      hint="Áp dụng cho mọi hàng trong lần ghi nhận này."
      dense
    >
      <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
        <div
          v-if="!lockProject"
          class="sm:col-span-2"
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
        <div class="sm:col-span-2">
          <label class="label text-[11px]">Người phụ trách</label>
          <PersonSelect
            v-model="defaults.owner_id"
            :options="employees"
            placeholder="Tìm & chọn…"
          />
        </div>
      </div>
    </BlockerFormSection>

    <BlockerFormSection
      title="Danh sách vướng mắc"
      :hint="`${bulkRows.length} / ${BULK_MAX_ROWS} hàng · ${summary.valid} hợp lệ`"
      dense
    >
      <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-end">
        <div class="min-w-0 flex-1">
          <label class="label text-[11px]">Dán nhanh nhiều đề (mỗi dòng một hàng)</label>
          <textarea
            v-model="pasteText"
            rows="2"
            class="input resize-y text-sm"
            placeholder="Dán từ Excel hoặc gõ nhiều dòng…"
            @keydown.ctrl.enter.prevent="applyPaste"
            @keydown.meta.enter.prevent="applyPaste"
          />
        </div>
        <div class="flex shrink-0 flex-wrap gap-1.5">
          <button
            type="button"
            class="btn-ghost py-1.5 text-xs"
            :disabled="!pasteText.trim()"
            @click="applyPaste"
          >
            Thêm hàng
          </button>
          <button
            type="button"
            class="btn-ghost py-1.5 text-xs"
            @click="addSampleRows"
          >
            Chèn mẫu
          </button>
        </div>
      </div>

      <div class="flex flex-wrap items-center gap-2 rounded-lg border border-slate-200 bg-slate-50/80 px-3 py-2 text-xs">
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

      <div class="mt-2 overflow-hidden rounded-lg border border-slate-200">
        <div
          class="hidden gap-2 border-b border-slate-100 bg-slate-50 px-3 py-2 text-[10px] font-semibold uppercase text-slate-500 lg:grid lg:grid-cols-[2rem_1fr_minmax(8rem,11rem)_2rem]"
        >
          <span />
          <span>Đề vướng mắc</span>
          <span>Minh chứng</span>
          <span />
        </div>
        <ul class="max-h-[min(320px,42vh)] divide-y divide-slate-100 overflow-y-auto">
          <li
            v-for="(row, idx) in bulkRows"
            :key="row.id"
            class="grid gap-2 px-3 py-2.5 lg:grid-cols-[2rem_1fr_minmax(8rem,11rem)_2rem] lg:items-center"
            :class="rowHasErrors(row.id) ? 'bg-amber-50/50' : ''"
          >
            <div class="flex items-center gap-2 lg:flex-col lg:gap-0.5">
              <input
                v-model="row.selected"
                type="checkbox"
                class="rounded accent-brand"
                :disabled="rowHasErrors(row.id)"
              >
              <span class="text-xs tabular-nums text-slate-400">{{ idx + 1 }}</span>
            </div>
            <div class="min-w-0">
              <input
                v-model="row.title"
                type="text"
                class="input w-full py-1.5 text-sm"
                :class="rowHasErrors(row.id) ? 'border-amber-300' : ''"
                placeholder="VD: API đăng nhập trả lỗi 500…"
                maxlength="255"
              >
              <p
                v-for="err in rowErrors(row.id)"
                :key="err.code"
                class="mt-0.5 text-[10px] text-amber-700"
              >
                {{ err.message }}
              </p>
            </div>
            <div class="min-w-0">
              <BlockerAttachmentsBlock
                v-if="canUploadAttachments"
                :blocker-id="null"
                :attachments="[]"
                :can-upload="canUploadAttachments"
                :pending-files="row.pendingFiles"
                stage-until-save
                inline
                @update:pending-files="setRowPendingFiles(row.id, $event)"
              />
              <span
                v-else
                class="text-[11px] text-slate-400"
              >—</span>
            </div>
            <button
              type="button"
              class="justify-self-end text-slate-400 hover:text-rose-500 lg:justify-self-center"
              title="Xoá hàng"
              @click="removeRow(row.id)"
            >
              <AppIcon
                name="delete"
                :size="16"
              />
            </button>
          </li>
        </ul>
      </div>

      <div class="mt-2 flex flex-wrap items-center gap-2">
        <button
          type="button"
          class="btn-ghost border border-dashed border-slate-200 text-xs"
          @click="addEmptyRow"
        >
          + Thêm hàng
        </button>
        <label class="ml-auto flex items-center gap-1.5 text-xs text-slate-600">
          <input
            type="checkbox"
            class="rounded accent-brand"
            :checked="allSelected"
            @change="toggleSelectAll($event.target.checked)"
          >
          Chọn tất cả hợp lệ
        </label>
      </div>
    </BlockerFormSection>

    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
      <p
        v-if="pendingAttachmentCount"
        class="text-xs text-slate-500"
      >
        {{ pendingAttachmentCount }} ảnh chờ tải (theo từng hàng khi ghi nhận)
      </p>
      <span v-else />
      <div class="flex flex-wrap gap-2">
        <button
          type="button"
          class="btn-ghost text-sm"
          :disabled="!isDirty"
          @click="clearAll"
        >
          Xoá hết
        </button>
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
</template>
