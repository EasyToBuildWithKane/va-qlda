<script setup>
import { computed, inject, watch, ref, nextTick } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Modal from '@/Components/Ui/Modal.vue';
import PersonSelect from '@/modules/project/components/PersonSelect.vue';
import SearchSelect from '@/shared/ui/SearchSelect.vue';
import BlockerAttachmentsBlock from '@/modules/project/components/BlockerAttachmentsBlock.vue';
import BlockerFormBulkPanel from '@/modules/project/components/BlockerFormBulkPanel.vue';
import BlockerTitleComposer from '@/modules/project/components/BlockerTitleComposer.vue';
import BlockerCreateModeTabs from '@/modules/project/components/BlockerCreateModeTabs.vue';
import BlockerFormSection from '@/modules/project/components/BlockerFormSection.vue';
import { valueLabelOptions } from '@/shared/utils/selectOptions';
import { date } from '@/composables/useFormat';
import { useToast } from '@/shared/composables/useToast';
import { useModalFormDraft } from '@/composables/useModalFormDraft';
import { buildDraftSaveMeta, restoreModalDraft } from '@/composables/useModalDraftHelpers';
import { uploadFilesToBlocker } from '@/composables/useBlockerAttachmentUpload';

const props = defineProps({
    show: { type: Boolean, default: false },
    blocker: { type: Object, default: null },
    /** Mở từ «Hướng xử lý» — chỉ nhập kế hoạch, không tab Nội dung/Phân công */
    focusResolution: { type: Boolean, default: false },
    projects: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
    severityOptions: { type: Array, default: () => [] },
    statusOptions: { type: Array, default: () => [] },
    defaultProjectId: { type: Number, default: null },
    lockProject: { type: Boolean, default: false },
    projectName: { type: String, default: '' },
    projectCode: { type: String, default: '' },
    canUploadAttachments: { type: Boolean, default: false },
    /** Chỉ áp dụng khi tạo mới (không có blocker) */
    initialDescription: { type: String, default: '' },
});

const emit = defineEmits(['close', 'saved']);
const modalClose = inject('modalClose', () => emit('close'));
const toast = useToast();
const pendingCreateFiles = ref([]);
const createMode = ref('single');
const bulkDirty = ref(false);
const bulkPanelRef = ref(null);

const form = useForm({
    project_id: null,
    title: '',
    description: '',
    root_cause: '',
    severity: 'medium',
    status: 'open',
    owner_id: null,
    due_date: null,
    resolution: '',
    evidence_links: [],
});

const draftScope = computed(() => {
    if (props.blocker) {
        const suffix = props.focusResolution ? '.resolution' : '';
        return `edit.${props.blocker.id}${suffix}`;
    }
    return `create.${props.defaultProjectId ?? 'global'}`;
});

const formDraft = useModalFormDraft('blocker', {
    getScope: () => draftScope.value,
    pick: (data) => ({
        ...data,
        evidence_links: (data.evidence_links ?? []).map((l) => ({
            label: l?.label ?? '',
            url: l?.url ?? '',
        })),
        createMode: createMode.value,
        bulk: createMode.value === 'bulk'
            ? bulkPanelRef.value?.getDraftSnapshot?.() ?? null
            : null,
    }),
});

const applyFormDraft = (data) => {
    form.project_id = data.project_id ?? props.defaultProjectId;
    form.title = data.title ?? '';
    form.description = data.description ?? '';
    form.root_cause = data.root_cause ?? '';
    form.severity = data.severity ?? 'medium';
    form.status = data.status ?? 'open';
    form.owner_id = data.owner_id ?? null;
    form.due_date = data.due_date ?? null;
    form.resolution = data.resolution ?? '';
    form.evidence_links = (data.evidence_links ?? []).map((l) => ({
        label: l?.label ?? '',
        url: l?.url ?? '',
    }));
};

const saveDraftOnClose = () => {
    formDraft.saveOnClose(form.data(), buildDraftSaveMeta(props.blocker, { createMode: createMode.value }));
};

const resolutionInputRef = ref(null);

const clearPendingCreateFiles = () => {
    pendingCreateFiles.value.forEach((p) => {
        if (p.preview) URL.revokeObjectURL(p.preview);
    });
    pendingCreateFiles.value = [];
};

watch(() => props.show, async (open) => {
    if (!open) {
        clearPendingCreateFiles();
        bulkPanelRef.value?.reset?.();
        return;
    }
    clearPendingCreateFiles();
    form.clearErrors();
    createMode.value = 'single';
    bulkDirty.value = false;
    const epoch = formDraft.bumpOpenEpoch();
    const preset = (props.initialDescription ?? '').trim();
    if (props.blocker) {
        form.project_id = props.blocker.project_id;
        form.title = props.blocker.title;
        form.description = props.blocker.description ?? '';
        form.root_cause = props.blocker.root_cause ?? '';
        form.severity = props.blocker.severity.value;
        form.status = props.blocker.status.value;
        form.owner_id = props.blocker.owner?.id ?? null;
        form.due_date = props.blocker.due_date ?? null;
        form.resolution = props.blocker.resolution ?? '';
        form.evidence_links = (props.blocker.evidence_links ?? []).map((l) => ({
            label: l?.label ?? '',
            url: l?.url ?? '',
        }));
        await restoreModalDraft(formDraft, {
            isActive: () => props.show,
            openEpoch: epoch,
            entity: props.blocker,
            applyDraft: async (data, meta) => {
                applyFormDraft(data);
                const mode = meta?.createMode ?? data.createMode;
                if (mode && !props.blocker) createMode.value = mode;
                await nextTick();
                const bulkSnap = meta?.bulk ?? data.bulk;
                if (bulkSnap && createMode.value === 'bulk') {
                    bulkPanelRef.value?.applyDraftSnapshot?.(bulkSnap);
                }
            },
            form,
        });
    } else {
        form.reset();
        form.project_id = props.defaultProjectId;
        form.severity = 'medium';
        form.status = 'open';
        form.resolution = '';
        form.evidence_links = [];
        const restored = await restoreModalDraft(formDraft, {
            isActive: () => props.show,
            openEpoch: epoch,
            entity: null,
            applyDraft: async (data, meta) => {
                applyFormDraft(data);
                const mode = meta?.createMode ?? data.createMode;
                if (mode) createMode.value = mode;
                await nextTick();
                const bulkSnap = meta?.bulk ?? data.bulk;
                if (bulkSnap && createMode.value === 'bulk') {
                    bulkPanelRef.value?.applyDraftSnapshot?.(bulkSnap);
                }
            },
            form,
        });
        if (!restored && preset) {
            form.description = preset;
        }
    }
    if (props.focusResolution && props.blocker) {
        await nextTick();
        resolutionInputRef.value?.focus?.();
    }
});

const activeProjectId = computed(() =>
    form.project_id ?? props.blocker?.project_id ?? props.defaultProjectId ?? null,
);

const projectDisplay = computed(() => {
    const embedded = props.blocker?.project;
    if (embedded?.name) {
        return embedded?.code ? `${embedded.name} (${embedded.code})` : embedded.name;
    }
    const id = activeProjectId.value;
    if (id) {
        const p = props.projects.find((x) => x?.id === id);
        if (p?.name) {
            return p?.code ? `${p.name} (${p.code})` : p.name;
        }
    }
    if (props.projectName) {
        return props.projectCode ? `${props.projectName} (${props.projectCode})` : props.projectName;
    }
    return null;
});

const isEdit = computed(() => !!props.blocker);

const attachmentList = computed(() => {
    const raw = props.blocker?.attachments;
    if (Array.isArray(raw)) return raw;
    if (raw?.data && Array.isArray(raw.data)) return raw.data;
    return [];
});

const isResolutionFlow = computed(() => isEdit.value && props.focusResolution);

const showResolutionPanel = computed(() => isResolutionFlow.value);

const showMainForm = computed(() => !isResolutionFlow.value);

const showAttachmentsBlock = computed(() =>
    showMainForm.value && props.canUploadAttachments,
);

const attachmentBlockerId = computed(() => (props.blocker?.id ? props.blocker.id : null));

const showProjectSelector = computed(() => !isEdit.value && !props.lockProject);

const showProjectBanner = computed(() => isEdit.value || props.lockProject);

const projectBannerLabel = computed(() => {
    if (projectDisplay.value) {
        return projectDisplay.value;
    }
    if (isEdit.value) {
        return 'Thắc mắc chung';
    }
    return '—';
});

const modalTitle = computed(() => {
    if (!props.blocker) {
        return createMode.value === 'bulk' ? 'Ghi nhận nhiều vướng mắc' : 'Ghi nhận vướng mắc';
    }
    if (props.focusResolution) return 'Hướng xử lý vướng mắc';
    return 'Cập nhật vướng mắc';
});

const modalMaxWidth = computed(() => {
    if (!props.blocker && createMode.value === 'bulk') return 'max-w-6xl';
    if (showMainForm.value && !isResolutionFlow.value) return 'max-w-6xl';
    if (props.blocker || createMode.value === 'single') return 'max-w-6xl';
    return 'max-w-4xl';
});

const bulkInitialDefaults = computed(() => ({
    project_id: form.project_id ?? props.defaultProjectId ?? null,
    severity: form.severity,
    status: form.status,
    owner_id: form.owner_id,
    due_date: form.due_date,
}));

const onBulkSaved = () => {
    formDraft.clear();
    emit('saved');
    emit('close');
};

const modalSubtitle = computed(() => {
    if (!props.blocker) return null;
    return props.blocker.code ? `${props.blocker.code} · ${props.blocker.title}` : props.blocker.title;
});

const severitySelectOptions = computed(() => valueLabelOptions(props.severityOptions));
const statusSelectOptions = computed(() => valueLabelOptions(props.statusOptions));
const statusLocked = computed(() => {
    const v = props.blocker?.status?.value;
    return v === 'resolved' || v === 'closed';
});

const submitLabel = computed(() => {
    if (isResolutionFlow.value) return 'Lưu hướng xử lý';
    if (!isEdit.value) return 'Ghi nhận vướng mắc';
    return 'Lưu thay đổi';
});

const pendingAttachmentSummary = computed(() => {
    const n = pendingCreateFiles.value.length;
    if (!n) return null;
    const images = pendingCreateFiles.value.filter((p) => p.isImage).length;
    if (images === n) return `${n} ảnh sẽ tải khi bấm Lưu`;
    return `${n} file sẽ tải khi bấm Lưu`;
});

function textOrDash(value) {
    const t = (value ?? '').trim();
    return t || null;
}

const addEvidenceLink = () => {
    if (form.evidence_links.length >= 20) return;
    form.evidence_links.push({ label: '', url: '' });
};

const removeEvidenceLink = (index) => {
    form.evidence_links.splice(index, 1);
};

const cleanedEvidenceLinks = () =>
    form.evidence_links
        .map((l) => ({ label: (l.label ?? '').trim(), url: (l.url ?? '').trim() }))
        .filter((l) => l.url);

const finishSave = () => {
    formDraft.clear();
    emit('saved');
    emit('close');
};

const uploadPendingAttachments = (blockerId, successMessage) => {
    const files = pendingCreateFiles.value.map((p) => p.file);
    if (!files.length) {
        finishSave();
        return;
    }
    uploadFilesToBlocker(blockerId, files, {
        onPartialError: () => toast.warning('Đã lưu vướng mắc nhưng một số ảnh/file chưa tải được.'),
        onFinish: () => {
            clearPendingCreateFiles();
            if (successMessage) toast.success(successMessage);
            finishSave();
        },
    });
};

const modalDirty = computed(() =>
    form.isDirty
    || pendingCreateFiles.value.length > 0
    || (!props.blocker && createMode.value === 'bulk' && bulkDirty.value),
);

const submit = () => {
    const payload = { ...form.data(), evidence_links: cleanedEvidenceLinks() };
    if (!props.blocker) {
        delete payload.root_cause;
        delete payload.resolution;
    }
    if (props.blocker) {
        if (isResolutionFlow.value) {
            const trimmed = {
                root_cause: (form.root_cause ?? '').trim(),
                resolution: (form.resolution ?? '').trim(),
            };
            if (!trimmed.root_cause) {
                form.setError('root_cause', 'Vui lòng ghi nhận nguyên nhân trước khi lưu hướng xử lý.');
                return;
            }
            form.transform(() => trimmed).put(`/blockers/${props.blocker.id}`, {
                preserveScroll: true,
                onSuccess: finishSave,
            });
            return;
        }
        delete payload.root_cause;
        delete payload.resolution;
        if (statusLocked.value) {
            payload.status = props.blocker.status.value;
        }
        form.transform(() => payload).put(`/blockers/${props.blocker.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                if (pendingCreateFiles.value.length) {
                    uploadPendingAttachments(
                        props.blocker.id,
                        pendingCreateFiles.value.some((p) => p.isImage)
                            ? 'Đã lưu và tải ảnh minh chứng'
                            : 'Đã lưu và tải file đính kèm',
                    );
                } else {
                    finishSave();
                }
            },
        });
        return;
    }
    form.transform(() => payload).post('/blockers', {
        preserveScroll: true,
        onSuccess: (page) => {
            const id = page.props.flash?.created_blocker_id;
            if (id && pendingCreateFiles.value.length) {
                uploadPendingAttachments(id, 'Đã ghi nhận vướng mắc và ảnh minh chứng');
            } else {
                clearPendingCreateFiles();
                finishSave();
            }
        },
    });
};
</script>

<template>
  <Modal
    :show="show"
    :dirty="modalDirty"
    :title="modalTitle"
    :max-width="modalMaxWidth"
    :on-save-draft="saveDraftOnClose"
    @close="emit('close')"
  >
    <BlockerCreateModeTabs
      v-if="!blocker && !focusResolution"
      :mode="createMode"
      @update:mode="createMode = $event"
    />

    <BlockerFormBulkPanel
      v-if="!blocker && !focusResolution && createMode === 'bulk'"
      ref="bulkPanelRef"
      :projects="projects"
      :employees="employees"
      :severity-options="severityOptions"
      :status-options="statusOptions"
      :lock-project="lockProject"
      :default-project-id="defaultProjectId"
      :initial-defaults="bulkInitialDefaults"
      :can-upload-attachments="canUploadAttachments"
      @saved="onBulkSaved"
      @dirty-change="bulkDirty = $event"
    />

    <form
      v-else
      class="flex flex-col"
      @submit.prevent="submit"
    >
      <p
        v-if="modalSubtitle"
        class="-mt-1 mb-3 truncate text-xs text-slate-500"
        :title="modalSubtitle"
      >
        {{ modalSubtitle }}
      </p>
      <div
        v-if="showProjectBanner"
        class="mb-3 flex items-center gap-2 rounded-lg border border-slate-200/80 bg-slate-50/60 px-3 py-2 text-sm"
      >
        <AppIcon
          name="projects"
          :size="16"
          class="shrink-0 text-brand"
        />
        <span class="text-slate-500">{{ isEdit && !blocker?.project_id ? 'Phạm vi' : 'Dự án' }}:</span>
        <span class="min-w-0 truncate font-medium text-slate-800">{{ projectBannerLabel }}</span>
      </div>

      <div
        v-else-if="showProjectSelector"
        class="mb-3 max-w-md"
      >
        <label class="label flex items-center gap-1.5">
          Dự án
          <span class="font-normal text-slate-400">(tuỳ chọn)</span>
        </label>
        <SearchSelect
          v-model="form.project_id"
          :options="projects"
          placeholder="Tìm & chọn dự án…"
          search-placeholder="Tìm dự án…"
          clearable
        />
        <p
          v-if="form.errors.project_id"
          class="mt-1 text-xs text-danger"
        >
          {{ form.errors.project_id }}
        </p>
      </div>

      <div>
        <div
          v-if="blocker && showResolutionPanel"
          class="space-y-4"
        >
          <div class="rounded-lg border border-slate-200 bg-slate-50/90 p-3">
            <div class="flex flex-wrap items-center gap-2">
              <span
                v-if="blocker.code"
                class="font-mono text-xs font-semibold text-brand"
              >{{ blocker.code }}</span>
              <span
                v-if="blocker.severity"
                class="text-xs font-medium text-slate-600"
              >{{ blocker.severity.label }}</span>
              <span
                v-if="blocker.status"
                class="text-xs text-slate-400"
              >· {{ blocker.status.label }}</span>
              <span
                v-if="blocker.due_date"
                class="ml-auto text-xs tabular-nums text-slate-500"
              >
                Hạn {{ date(blocker.due_date) }}
              </span>
            </div>
            <p class="mt-1 text-sm font-medium text-slate-800">
              {{ blocker.title }}
            </p>
            <p
              v-if="textOrDash(blocker.description)"
              class="mt-2 border-t border-slate-200/80 pt-2 text-sm text-slate-600 whitespace-pre-wrap"
            >
              <span class="text-[10px] font-bold uppercase text-slate-400">Mô tả · </span>
              {{ blocker.description }}
            </p>
          </div>

          <div>
            <label class="label flex items-center gap-1.5">
              Nguyên nhân
              <span class="text-danger">*</span>
            </label>
            <textarea
              v-model="form.root_cause"
              rows="4"
              class="input mt-1 resize-y text-sm"
              placeholder="VD: Do cấu hình timeout API thanh toán quá thấp sau bản nâng cấp hạ tầng ngày 05/06…"
            />
            <p
              v-if="form.errors.root_cause"
              class="mt-1 text-xs text-danger"
            >
              {{ form.errors.root_cause }}
            </p>
          </div>

          <div>
            <label class="label">
              Kế hoạch xử lý
            </label>
            <textarea
              ref="resolutionInputRef"
              v-model="form.resolution"
              rows="8"
              class="input mt-1 min-h-[10rem] resize-y"
              placeholder="VD:&#10;1. Liên hệ team hạ tầng kiểm tra log API…&#10;2. Tạm rollback bản phát hành X…&#10;3. Họp PO 14h chốt phương án…"
            />
          </div>
        </div>

        <div
          v-if="showMainForm"
          class="space-y-3"
        >
          <BlockerTitleComposer
            v-model="form.title"
            compact
            :error="form.errors.title"
          />

          <div class="rounded-lg border border-slate-200 p-3 sm:p-4">
            <div class="grid gap-4 lg:grid-cols-2 lg:items-start">
              <BlockerFormSection
                plain
                title="Phân công & mô tả"
              >
                <div class="grid gap-2.5 sm:grid-cols-2">
                  <div>
                    <label class="label">
                      Mức độ
                    </label>
                    <SearchSelect
                      v-model="form.severity"
                      :options="severitySelectOptions"
                      placeholder="Chọn…"
                      :clearable="false"
                    />
                  </div>
                  <div>
                    <label class="label">
                      Trạng thái
                    </label>
                    <SearchSelect
                      v-model="form.status"
                      :options="statusSelectOptions"
                      placeholder="Chọn…"
                      :clearable="false"
                      :disabled="statusLocked"
                    />
                    <p
                      v-if="statusLocked"
                      class="mt-1 text-xs text-slate-500"
                    >
                      Đã giải quyết hoặc đã đóng — không thể đổi trạng thái.
                    </p>
                  </div>
                  <div>
                    <label class="label">
                      Hạn xử lý
                    </label>
                    <input
                      v-model="form.due_date"
                      type="date"
                      class="input"
                    >
                  </div>
                  <div>
                    <label class="label">
                      Người phụ trách
                    </label>
                    <PersonSelect
                      v-model="form.owner_id"
                      :options="employees"
                      placeholder="Tìm & chọn…"
                    />
                  </div>
                </div>
                <div class="mt-2.5">
                  <label class="label flex items-center gap-1.5">
                    Mô tả
                    <span class="font-normal text-slate-400">(tuỳ chọn)</span>
                  </label>
                  <textarea
                    v-model="form.description"
                    rows="3"
                    class="input resize-y text-sm"
                    placeholder="Bối cảnh, tác động…"
                  />
                </div>
              </BlockerFormSection>

              <BlockerFormSection
                plain
                title="Minh chứng"
                optional
              >
                <div class="space-y-2.5">
                  <div>
                    <div class="mb-1 flex items-center justify-between gap-2">
                      <span class="text-[11px] font-medium text-slate-600">Link</span>
                      <button
                        type="button"
                        class="text-xs font-medium text-brand hover:underline"
                        :disabled="form.evidence_links.length >= 20"
                        @click="addEvidenceLink"
                      >
                        + Thêm
                      </button>
                    </div>
                    <div class="max-h-28 space-y-1.5 overflow-y-auto">
                      <div
                        v-for="(link, index) in form.evidence_links"
                        :key="index"
                        class="flex gap-1.5"
                      >
                        <input
                          v-model="link.label"
                          type="text"
                          class="input w-20 shrink-0 text-sm"
                          placeholder="Nhãn"
                        >
                        <input
                          v-model="link.url"
                          type="url"
                          class="input min-w-0 flex-1 text-sm"
                          placeholder="https://…"
                        >
                        <button
                          type="button"
                          class="shrink-0 px-1 text-xs text-rose-500 hover:underline"
                          @click="removeEvidenceLink(index)"
                        >
                          Xoá
                        </button>
                      </div>
                    </div>
                    <p
                      v-if="form.errors['evidence_links.0.url']"
                      class="mt-1 text-xs text-danger"
                    >
                      {{ form.errors['evidence_links.0.url'] }}
                    </p>
                  </div>

                  <BlockerAttachmentsBlock
                    v-if="showAttachmentsBlock"
                    :blocker-id="attachmentBlockerId"
                    :attachments="attachmentList"
                    :can-upload="canUploadAttachments"
                    :pending-files="pendingCreateFiles"
                    stage-until-save
                    compact
                    @update:pending-files="pendingCreateFiles = $event"
                  />
                </div>
              </BlockerFormSection>
            </div>
          </div>
        </div>
      </div>

      <div class="mt-4 flex flex-wrap items-center justify-end gap-2 border-t border-slate-100 pt-3">
        <p
          v-if="pendingAttachmentSummary"
          class="mr-auto text-xs text-slate-500"
        >
          {{ pendingAttachmentSummary }}
        </p>
        <button
          type="button"
          class="btn-ghost"
          @click="modalClose()"
        >
          Huỷ
        </button>
        <button
          type="submit"
          class="btn-primary"
          :disabled="form.processing"
        >
          {{ submitLabel }}
        </button>
      </div>
    </form>
  </Modal>
</template>
