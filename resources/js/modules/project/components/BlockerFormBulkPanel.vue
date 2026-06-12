<script setup>
import { ref, computed, watch, inject } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import PersonSelect from '@/modules/project/components/PersonSelect.vue';
import SearchSelect from '@/shared/ui/SearchSelect.vue';
import FieldTooltip from '@/shared/ui/FieldTooltip.vue';
import BlockerAttachmentsBlock from '@/modules/project/components/BlockerAttachmentsBlock.vue';
import { valueLabelOptions } from '@/shared/utils/selectOptions';
import { useToast } from '@/shared/composables/useToast';
import { uploadAttachmentsForCreatedBlockers } from '@/composables/useBlockerAttachmentUpload';
import {
    BULK_MAX_ROWS,
    bulkValidationSummary,
    nextBulkRowId,
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
    applyInitialDefaults();
};

defineExpose({
    reset,
    getDraftSnapshot: () => ({
        bulkRows: bulkRows.value.map(({ id, title, selected }) => ({ id, title, selected })),
        defaults: { ...defaults.value },
    }),
    applyDraftSnapshot: (snap) => {
        if (!snap) return;
        bulkRows.value.forEach(revokeRowFiles);
        const rows = snap.bulkRows ?? [];
        bulkRows.value = rows.length
            ? rows.map((r) => emptyBulkRow({ id: r.id, title: r.title ?? '', selected: r.selected !== false }))
            : [emptyBulkRow()];
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
    bulkRows.value.some((r) => (r.title ?? '').trim() || (r.pendingFiles?.length ?? 0) > 0),
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

const clearAll = () => {
    bulkRows.value.forEach(revokeRowFiles);
    bulkRows.value = [emptyBulkRow()];
};

const bulkForm = useForm({ defaults: {}, rows: [] });
const submitting = computed(() => bulkForm.processing || uploadingAttachments.value);
const uploadingAttachments = ref(false);

const finishBulkSaved = (count) => {
    toast.success(`Đã ghi nhận ${count} vướng mắc`);
    emit('saved');
};

const submit = () => {
    const rows = bulkRows.value.filter((r) => !rowHasErrors(r.id) && (r.title ?? '').trim());
    if (!rows.length) {
        toast.error('Nhập ít nhất một đề hợp lệ để ghi nhận.');
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

const listHint = computed(() => {
    const parts = [`${bulkRows.value.length}/${BULK_MAX_ROWS} hàng`];
    if (summary.value.valid) parts.push(`${summary.value.valid} sẵn sàng ghi`);
    if (summary.value.invalid) parts.push(`${summary.value.invalid} lỗi`);
    return parts.join(' · ');
});

const pendingAttachmentCount = computed(() =>
    bulkRows.value.reduce((n, r) => n + (r.pendingFiles?.length ?? 0), 0),
);
</script>

<template>
  <div class="space-y-3">
    <details
      class="group rounded-lg border border-slate-200 bg-white"
      open
    >
      <summary class="cursor-pointer list-none px-3 py-2.5 text-sm font-semibold text-slate-800 marker:content-none [&::-webkit-details-marker]:hidden">
        <span class="flex items-center gap-2">
          Cài đặt chung
          <AppIcon
            name="chevron"
            :size="14"
            class="text-slate-400 transition group-open:rotate-180"
          />
        </span>
      </summary>
      <div class="border-t border-slate-100 px-3 pb-3 pt-2">
        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
          <div
            v-if="!lockProject"
            class="sm:col-span-2 lg:col-span-3"
          >
            <label class="label flex items-center gap-1">
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
            <label class="label">Dự án</label>
            <p class="input flex h-9 items-center truncate text-sm text-slate-700">
              {{ projectLabel }}
            </p>
          </div>
          <div>
            <label class="label">Mức độ</label>
            <SearchSelect
              v-model="defaults.severity"
              :options="severitySelectOptions"
              placeholder="Mức độ…"
              :clearable="false"
            />
          </div>
          <div>
            <label class="label">Trạng thái</label>
            <SearchSelect
              v-model="defaults.status"
              :options="statusSelectOptions"
              placeholder="Trạng thái…"
              :clearable="false"
            />
          </div>
          <div>
            <label class="label">Hạn xử lý</label>
            <input
              v-model="defaults.due_date"
              type="date"
              class="input h-9 text-sm"
            >
          </div>
          <div class="sm:col-span-2 lg:col-span-3">
            <label class="label">Người phụ trách</label>
            <PersonSelect
              v-model="defaults.owner_id"
              :options="employees"
              placeholder="Tìm & chọn…"
            />
          </div>
        </div>
      </div>
    </details>

    <div class="overflow-hidden rounded-lg border border-slate-200">
      <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 bg-slate-50/80 px-3 py-2">
        <h3 class="text-xs font-semibold text-slate-800">
          Danh sách vướng mắc
        </h3>
        <span class="text-[11px] text-slate-500">{{ listHint }}</span>
      </div>
      <div
        class="hidden grid-cols-[1.75rem_1fr_minmax(7.5rem,10rem)_2rem] gap-2 border-b border-slate-100 px-3 py-1.5 text-[10px] font-medium uppercase tracking-wide text-slate-400 lg:grid"
      >
        <span>#</span>
        <span>Đề</span>
        <span>Ảnh</span>
        <span />
      </div>
      <ul class="max-h-[min(340px,45vh)] divide-y divide-slate-100 overflow-y-auto">
        <li
          v-for="(row, idx) in bulkRows"
          :key="row.id"
          class="grid gap-2 px-3 py-2 lg:grid-cols-[1.75rem_1fr_minmax(7.5rem,10rem)_2rem] lg:items-center"
          :class="rowHasErrors(row.id) ? 'bg-amber-50/40' : ''"
        >
          <span class="text-xs tabular-nums text-slate-400">{{ idx + 1 }}</span>
          <div class="min-w-0">
            <input
              v-model="row.title"
              type="text"
              class="input w-full py-1.5 text-sm"
              :class="rowHasErrors(row.id) ? 'border-amber-300' : ''"
              placeholder="Đề vướng mắc…"
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
      <div class="border-t border-slate-100 px-3 py-2">
        <button
          type="button"
          class="text-xs font-medium text-brand hover:underline"
          @click="addEmptyRow"
        >
          + Thêm hàng
        </button>
      </div>
    </div>

    <div class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-100 pt-3">
      <p
        v-if="pendingAttachmentCount"
        class="mr-auto text-xs text-slate-500"
      >
        {{ pendingAttachmentCount }} ảnh chờ tải
      </p>
      <button
        type="button"
        class="btn-ghost text-xs"
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
</template>
