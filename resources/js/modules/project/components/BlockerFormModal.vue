<script setup>
import { computed, inject, watch, ref, nextTick } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Modal from '@/Components/Ui/Modal.vue';
import FieldTooltip from '@/shared/ui/FieldTooltip.vue';
import PersonSelect from '@/modules/project/components/PersonSelect.vue';
import SearchSelect from '@/shared/ui/SearchSelect.vue';
import BlockerAttachmentsBlock from '@/modules/project/components/BlockerAttachmentsBlock.vue';
import { valueLabelOptions } from '@/shared/utils/selectOptions';
import { date } from '@/composables/useFormat';

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
});

const emit = defineEmits(['close', 'saved']);
const modalClose = inject('modalClose', () => emit('close'));

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

const resolutionInputRef = ref(null);

watch(() => props.show, async (open) => {
    if (!open) return;
    form.clearErrors();
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
    } else {
        form.reset();
        form.project_id = props.defaultProjectId;
        form.severity = 'medium';
        form.status = 'open';
        form.resolution = '';
        form.evidence_links = [];
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

const showAttachments = computed(() => isEdit.value && props.blocker?.id && !isResolutionFlow.value);

const showResolutionPanel = computed(() => isResolutionFlow.value);

const showMainForm = computed(() => !isResolutionFlow.value);

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
    if (!props.blocker) return 'Ghi nhận vướng mắc';
    if (props.focusResolution) return 'Hướng xử lý vướng mắc';
    return 'Cập nhật vướng mắc';
});

const modalSubtitle = computed(() => {
    if (!props.blocker) return null;
    return props.blocker.code ? `${props.blocker.code} · ${props.blocker.title}` : props.blocker.title;
});

const severitySelectOptions = computed(() => valueLabelOptions(props.severityOptions));
const statusSelectOptions = computed(() => valueLabelOptions(props.statusOptions));

const submitLabel = computed(() => {
    if (isResolutionFlow.value) return 'Lưu hướng xử lý';
    if (!isEdit.value) return 'Ghi nhận vướng mắc';
    return 'Lưu thay đổi';
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

const submit = () => {
    const opts = { preserveScroll: true, onSuccess: () => { emit('saved'); emit('close'); } };
    const payload = { ...form.data(), evidence_links: cleanedEvidenceLinks() };
    if (props.blocker) {
        form.transform(() => payload).put(`/blockers/${props.blocker.id}`, opts);
    } else {
        form.transform(() => payload).post('/blockers', opts);
    }
};
</script>

<template>
  <Modal
    :show="show"
    :dirty="form.isDirty"
    :title="modalTitle"
    max-width="max-w-5xl"
    @close="emit('close')"
  >
    <p
      v-if="modalSubtitle"
      class="-mt-1 mb-4 truncate text-sm text-slate-500"
      :title="modalSubtitle"
    >
      {{ modalSubtitle }}
    </p>

    <form
      class="flex flex-col"
      @submit.prevent="submit"
    >
      <div
        v-if="showProjectBanner"
        class="mb-4 flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50/80 px-3 py-2.5"
      >
        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-white text-brand shadow-sm ring-1 ring-slate-200/80">
          <AppIcon
            name="projects"
            :size="18"
          />
        </span>
        <div class="min-w-0 flex-1">
          <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">
            {{ isEdit && !blocker?.project_id ? 'Phạm vi' : 'Dự án' }}
          </p>
          <p class="truncate text-sm font-semibold text-slate-800">
            {{ projectBannerLabel }}
          </p>
        </div>
      </div>

      <div
        v-else-if="showProjectSelector"
        class="mb-4 max-w-md"
      >
        <label class="label flex items-center gap-1.5">
          Dự án
          <span class="font-normal text-slate-400">(tuỳ chọn)</span>
          <FieldTooltip text="Để trống → nhóm «Thắc mắc chung» trên danh sách." />
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

      <div class="min-h-[12rem]">
        <!-- Tab: Hướng xử lý (chỉ sửa) — v-if: tránh đọc blocker.* khi tạo mới (v-show vẫn evaluate) -->
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
            <details
              v-if="textOrDash(blocker.description) || textOrDash(blocker.root_cause)"
              class="group mt-2"
            >
              <summary class="cursor-pointer list-none text-xs font-medium text-brand hover:underline marker:content-none [&::-webkit-details-marker]:hidden">
                <span class="inline-flex items-center gap-1">
                  <AppIcon
                    name="chevron-down"
                    :size="14"
                    class="transition group-open:rotate-180"
                  />
                  Xem mô tả &amp; nguyên nhân (tham khảo)
                </span>
              </summary>
              <div class="mt-2 space-y-2 border-t border-slate-200/80 pt-2 text-sm text-slate-600">
                <p
                  v-if="textOrDash(blocker.description)"
                  class="whitespace-pre-wrap"
                >
                  <span class="text-[10px] font-bold uppercase text-slate-400">Mô tả · </span>
                  {{ blocker.description }}
                </p>
                <p
                  v-if="textOrDash(blocker.root_cause)"
                  class="whitespace-pre-wrap"
                >
                  <span class="text-[10px] font-bold uppercase text-slate-400">Nguyên nhân · </span>
                  {{ blocker.root_cause }}
                </p>
              </div>
            </details>
          </div>

          <div>
            <label class="label flex items-center gap-1.5">
              Kế hoạch xử lý
              <FieldTooltip text="Bước cụ thể, người phối hợp, thời hạn và tiêu chí hoàn thành." />
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
          class="grid gap-5 lg:grid-cols-2 lg:gap-6"
        >
          <div class="min-w-0 space-y-3">
            <div>
              <label class="label flex items-center gap-1.5">
                Tiêu đề <span class="text-danger">*</span>
                <FieldTooltip text="Một câu tóm tắt, dễ nhận biết trong danh sách." />
              </label>
              <input
                v-model="form.title"
                type="text"
                class="input"
                :placeholder="isEdit ? undefined : 'VD: API đăng nhập trả về lỗi 500 khi tải cao…'"
              >
              <p
                v-if="form.errors.title"
                class="mt-1 text-xs text-danger"
              >
                {{ form.errors.title }}
              </p>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
              <div>
                <label class="label flex items-center gap-1.5">
                  Mức độ
                  <FieldTooltip text="Mức nghiêm trọng / ưu tiên xử lý." />
                </label>
                <SearchSelect
                  v-model="form.severity"
                  :options="severitySelectOptions"
                  placeholder="Chọn…"
                  :clearable="false"
                />
              </div>
              <div>
                <label class="label flex items-center gap-1.5">
                  Trạng thái
                  <FieldTooltip text="Trạng thái xử lý hiện tại." />
                </label>
                <SearchSelect
                  v-model="form.status"
                  :options="statusSelectOptions"
                  placeholder="Chọn…"
                  :clearable="false"
                />
              </div>
              <div>
                <label class="label flex items-center gap-1.5">
                  Hạn xử lý
                </label>
                <input
                  v-model="form.due_date"
                  type="date"
                  class="input"
                >
              </div>
              <div>
                <label class="label flex items-center gap-1.5">
                  Người phụ trách
                </label>
                <PersonSelect
                  v-model="form.owner_id"
                  :options="employees"
                  placeholder="Tìm & chọn…"
                />
              </div>
            </div>
            <div>
              <label class="label flex items-center gap-1.5">
                Mô tả chi tiết
              </label>
              <textarea
                v-model="form.description"
                rows="3"
                class="input resize-y text-sm"
                placeholder="Bối cảnh, tác động…"
              />
            </div>
            <div>
              <label class="label flex items-center gap-1.5">
                Nguyên nhân
              </label>
              <textarea
                v-model="form.root_cause"
                rows="2"
                class="input resize-y text-sm"
                placeholder="Nguyên nhân gốc (nếu có)…"
              />
            </div>
            <div v-if="!isResolutionFlow">
              <label class="label flex items-center gap-1.5">
                Hướng xử lý
                <FieldTooltip text="Kế hoạch xử lý; có thể chỉnh riêng qua «Hướng xử lý» trên bảng." />
              </label>
              <textarea
                v-model="form.resolution"
                rows="2"
                class="input resize-y text-sm"
                placeholder="Biện pháp, bước tiếp theo…"
              />
            </div>
          </div>

          <div class="min-w-0 space-y-3 lg:border-l lg:border-slate-200 lg:pl-6 dark:lg:border-slate-700">
            <div>
              <div class="mb-1 flex items-center justify-between gap-2">
                <label class="label flex items-center gap-1.5">
                  Link dẫn chứng
                  <FieldTooltip text="Jira, Figma, log, ticket…" />
                </label>
                <button
                  type="button"
                  class="text-xs font-medium text-brand hover:underline"
                  :disabled="form.evidence_links.length >= 20"
                  @click="addEvidenceLink"
                >
                  + Thêm link
                </button>
              </div>
              <div class="max-h-36 space-y-2 overflow-y-auto pr-0.5">
                <div
                  v-for="(link, index) in form.evidence_links"
                  :key="index"
                  class="flex flex-col gap-1.5 sm:flex-row sm:items-center"
                >
                  <input
                    v-model="link.label"
                    type="text"
                    class="input text-sm sm:w-28"
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
                    class="text-xs text-rose-500 hover:underline sm:shrink-0"
                    @click="removeEvidenceLink(index)"
                  >
                    Xoá
                  </button>
                </div>
              </div>
              <p
                v-if="!form.evidence_links.length"
                class="mt-1 text-xs text-slate-400"
              >
                Chưa có link dẫn chứng.
              </p>
              <p
                v-if="form.errors['evidence_links.0.url']"
                class="mt-1 text-xs text-danger"
              >
                {{ form.errors['evidence_links.0.url'] }}
              </p>
            </div>

            <div>
              <label class="label flex items-center gap-1.5">
                Ảnh & file minh chứng
                <FieldTooltip text="Kéo thả hoặc chọn ảnh; nhấn thumbnail để phóng to. Upload lưu ngay, không cần bấm Lưu." />
              </label>
              <BlockerAttachmentsBlock
                v-if="showAttachments"
                :blocker-id="blocker.id"
                :attachments="attachmentList"
                :can-upload="canUploadAttachments"
                compact
              />
              <p
                v-else
                class="rounded-lg border border-dashed border-slate-200 px-3 py-4 text-center text-xs text-slate-500 dark:border-slate-600"
              >
                Ghi nhận vướng mắc trước, sau đó mở lại bản ghi để tải ảnh và file đính kèm.
              </p>
            </div>
          </div>
        </div>
      </div>

      <div class="mt-5 flex flex-wrap items-center justify-end gap-2 border-t border-slate-100 pt-4">
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
