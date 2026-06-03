<script setup>
import { computed, inject, watch, ref, nextTick } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Modal from '@/Components/Ui/Modal.vue';
import FieldTooltip from '@/shared/ui/FieldTooltip.vue';
import PersonSelect from '@/modules/project/components/PersonSelect.vue';
import SearchSelect from '@/shared/ui/SearchSelect.vue';
import BlockerDetailSummary from '@/modules/project/components/BlockerDetailSummary.vue';
import { valueLabelOptions } from '@/shared/utils/selectOptions';

const props = defineProps({
    show: { type: Boolean, default: false },
    blocker: { type: Object, default: null },
    /** Khi mở từ «Xử lý» — cuộn tới ô hướng xử lý */
    focusResolution: { type: Boolean, default: false },
    projects: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
    severityOptions: { type: Array, default: () => [] },
    statusOptions: { type: Array, default: () => [] },
    defaultProjectId: { type: Number, default: null },
    lockProject: { type: Boolean, default: false },
    projectName: { type: String, default: '' },
    projectCode: { type: String, default: '' },
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
    } else {
        form.reset();
        form.project_id = props.defaultProjectId;
        form.severity = 'medium';
        form.status = 'open';
    }
    if (props.focusResolution && props.blocker) {
        await nextTick();
        resolutionInputRef.value?.scrollIntoView?.({ behavior: 'smooth', block: 'center' });
        resolutionInputRef.value?.focus?.();
    }
});

const activeProjectId = computed(() =>
    form.project_id ?? props.blocker?.project_id ?? props.defaultProjectId ?? null,
);

const projectDisplay = computed(() => {
    const embedded = props.blocker?.project;
    if (embedded?.name) {
        return embedded.code ? `${embedded.name} (${embedded.code})` : embedded.name;
    }
    const id = activeProjectId.value;
    if (id) {
        const p = props.projects.find((x) => x.id === id);
        if (p?.name) {
            return p.code ? `${p.name} (${p.code})` : p.name;
        }
    }
    if (props.projectName) {
        return props.projectCode ? `${props.projectName} (${props.projectCode})` : props.projectName;
    }
    return null;
});

const isEdit = computed(() => !!props.blocker);

/** Chỉ cho chọn dự án khi tạo mới (chưa khóa theo trang dự án). */
const showProjectSelector = computed(() => !isEdit.value && !props.lockProject && !projectDisplay.value);

const showProjectBanner = computed(() => isEdit.value || props.lockProject || !!projectDisplay.value);

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

const severitySelectOptions = computed(() => valueLabelOptions(props.severityOptions));
const statusSelectOptions = computed(() => valueLabelOptions(props.statusOptions));

const submit = () => {
    const opts = { preserveScroll: true, onSuccess: () => { emit('saved'); emit('close'); } };
    if (props.blocker) form.put(`/blockers/${props.blocker.id}`, opts);
    else form.post('/blockers', opts);
};
</script>

<template>
  <Modal
    :show="show"
    :dirty="form.isDirty"
    :title="modalTitle"
    max-width="max-w-4xl"
    @close="emit('close')"
  >
    <form
      class="space-y-5"
      @submit.prevent="submit"
    >
      <!-- Dự án (tự động khi ghi trong ngữ cảnh dự án) -->
      <div
        v-if="showProjectBanner"
        class="flex items-start gap-3 rounded-xl border border-brand/25 bg-gradient-to-r from-brand/8 to-transparent px-4 py-3.5 dark:border-brand/30 dark:from-brand/15"
      >
        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-white text-brand shadow-sm dark:bg-slate-800">
          <AppIcon
            name="projects"
            :size="20"
          />
        </span>
        <div class="min-w-0 flex-1">
          <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
            {{ isEdit && !blocker?.project_id ? 'Phạm vi' : 'Vướng mắc của dự án' }}
          </p>
          <p class="mt-0.5 truncate font-display text-base font-semibold text-slate-800 dark:text-slate-100">
            {{ projectBannerLabel }}
          </p>
          <p
            v-if="isEdit"
            class="mt-1 text-xs text-slate-500 dark:text-slate-400"
          >
            Không thể đổi dự án sau khi đã ghi nhận
          </p>
          <p
            v-else-if="lockProject"
            class="mt-1 text-xs text-slate-500 dark:text-slate-400"
          >
            Dự án được chọn tự động theo trang hiện tại
          </p>
        </div>
      </div>

      <div
        v-else-if="showProjectSelector"
        class="max-w-md"
      >
        <label class="label flex items-center gap-1.5">
          Dự án
          <span class="font-normal text-slate-400">(tuỳ chọn)</span>
          <FieldTooltip text="Chọn dự án nếu vướng mắc thuộc một dự án cụ thể. Để trống sẽ xếp vào nhóm «Thắc mắc chung» trên danh sách." />
        </label>
        <SearchSelect
          v-model="form.project_id"
          :options="projects"
          placeholder="Tìm & chọn dự án…"
          search-placeholder="Tìm dự án…"
          clearable
        />
        <p class="mt-1 text-xs text-slate-500">
          Không chọn dự án → <span class="font-medium text-slate-600">Thắc mắc chung</span>
        </p>
        <p
          v-if="form.errors.project_id"
          class="mt-1 text-xs text-danger"
        >
          {{ form.errors.project_id }}
        </p>
      </div>

      <BlockerDetailSummary
        v-if="isEdit"
        :blocker="blocker"
        class="mb-1"
      />

      <div
        v-if="isEdit"
        id="blocker-resolution-section"
        class="rounded-xl border-2 border-brand/25 bg-brand/[0.03] p-4 dark:border-brand/35"
      >
        <label class="label flex items-center gap-1.5 text-brand">
          Hướng xử lý
          <FieldTooltip text="Ghi rõ kế hoạch, bước tiếp theo, người phối hợp và kết quả mong đợi. Tham chiếu mô tả & nguyên nhân phía trên." />
        </label>
        <textarea
          ref="resolutionInputRef"
          v-model="form.resolution"
          rows="6"
          class="input mt-1 min-h-[8rem] resize-y border-brand/20 focus:border-brand/50"
          placeholder="VD:&#10;1. Liên hệ team hạ tầng kiểm tra log API…&#10;2. Tạm rollback bản phát hành X…&#10;3. Họp với PO lúc 14h để chốt phương án…"
        />
        <p class="mt-2 text-xs text-slate-500">
          Gợi ý: liệt kê bước cụ thể, người phụ trách từng bước, thời hạn và tiêu chí xong việc.
        </p>
      </div>

      <details
        v-if="isEdit"
        class="group rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900/30"
      >
        <summary class="cursor-pointer list-none px-4 py-3 text-sm font-medium text-slate-600 marker:content-none [&::-webkit-details-marker]:hidden">
          <span class="inline-flex items-center gap-2">
            <AppIcon
              name="chevron-down"
              :size="16"
              class="text-slate-400 transition group-open:rotate-180"
            />
            Sửa tiêu đề & nội dung gốc
          </span>
        </summary>
        <div class="space-y-4 border-t border-slate-100 px-4 py-4 dark:border-slate-800">
          <div>
            <label class="label flex items-center gap-1.5">
              Tiêu đề <span class="text-danger">*</span>
            </label>
            <input
              v-model="form.title"
              type="text"
              class="input"
            >
            <p
              v-if="form.errors.title"
              class="mt-1 text-xs text-danger"
            >
              {{ form.errors.title }}
            </p>
          </div>
          <div>
            <label class="label">Mô tả chi tiết</label>
            <textarea
              v-model="form.description"
              rows="3"
              class="input resize-y"
            />
          </div>
          <div>
            <label class="label">Nguyên nhân</label>
            <textarea
              v-model="form.root_cause"
              rows="2"
              class="input resize-y"
            />
          </div>
        </div>
      </details>

      <div
        class="space-y-4"
        :class="isEdit ? '' : 'grid gap-6 lg:grid-cols-2'"
      >
        <div
          v-if="!isEdit"
          class="space-y-4"
        >
          <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
            Nội dung vướng mắc
          </p>
          <div>
            <label class="label flex items-center gap-1.5">
              Tiêu đề <span class="text-danger">*</span>
              <FieldTooltip text="Một câu tóm tắt vướng mắc, ngắn gọn và dễ nhận biết trong danh sách." />
            </label>
            <input
              v-model="form.title"
              type="text"
              class="input"
              placeholder="VD: API đăng nhập trả về lỗi 500 khi tải cao…"
            >
            <p
              v-if="form.errors.title"
              class="mt-1 text-xs text-danger"
            >
              {{ form.errors.title }}
            </p>
          </div>
          <div>
            <label class="label flex items-center gap-1.5">
              Mô tả chi tiết
              <FieldTooltip text="Bối cảnh xảy ra, tác động đến công việc và phạm vi ảnh hưởng." />
            </label>
            <textarea
              v-model="form.description"
              rows="4"
              class="input resize-y"
              placeholder="Mô tả bối cảnh, tác động, phạm vi ảnh hưởng…"
            />
          </div>
          <div>
            <label class="label flex items-center gap-1.5">
              Nguyên nhân
              <FieldTooltip text="Nguyên nhân gốc rễ nếu đã xác định (gợi ý: dùng kỹ thuật 5 Whys)." />
            </label>
            <textarea
              v-model="form.root_cause"
              rows="3"
              class="input resize-y"
              placeholder="Nguyên nhân gốc rễ (nếu đã xác định)…"
            />
          </div>
          <div>
            <label class="label flex items-center gap-1.5">
              Hướng xử lý
              <FieldTooltip text="Kế hoạch xử lý ban đầu (có thể bổ sung sau)." />
            </label>
            <textarea
              v-model="form.resolution"
              rows="3"
              class="input resize-y"
              placeholder="Kế hoạch xử lý, bước tiếp theo…"
            />
          </div>
        </div>

        <div :class="isEdit ? 'space-y-4' : 'space-y-4'">
          <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
            Phân loại & phân công
          </p>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="label flex items-center gap-1.5">
                Mức độ
                <FieldTooltip text="Mức độ nghiêm trọng / ưu tiên xử lý của vướng mắc." />
              </label>
              <SearchSelect
                v-model="form.severity"
                :options="severitySelectOptions"
                placeholder="Chọn mức độ…"
                :clearable="false"
              />
            </div>
            <div>
              <label class="label flex items-center gap-1.5">
                Trạng thái
                <FieldTooltip text="Trạng thái xử lý hiện tại của vướng mắc." />
              </label>
              <SearchSelect
                v-model="form.status"
                :options="statusSelectOptions"
                placeholder="Chọn trạng thái…"
                :clearable="false"
              />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="label flex items-center gap-1.5">
                Hạn xử lý
                <FieldTooltip text="Thời hạn mong muốn xử lý xong vướng mắc." />
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
                <FieldTooltip text="Người chịu trách nhiệm theo dõi và xử lý vướng mắc." />
              </label>
              <PersonSelect
                v-model="form.owner_id"
                :options="employees"
                placeholder="Tìm & chọn người phụ trách…"
              />
            </div>
          </div>
        </div>
      </div>

      <div class="flex justify-end gap-2 border-t border-slate-100 pt-4 dark:border-slate-800">
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
          {{ isEdit ? (focusResolution ? 'Lưu hướng xử lý' : 'Lưu thay đổi') : 'Ghi nhận vướng mắc' }}
        </button>
      </div>
    </form>
  </Modal>
</template>
