<script setup>
import { computed, inject, watch, ref, nextTick } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Modal from '@/Components/Ui/Modal.vue';
import FieldTooltip from '@/shared/ui/FieldTooltip.vue';
import PersonSelect from '@/modules/project/components/PersonSelect.vue';
import SearchSelect from '@/shared/ui/SearchSelect.vue';
import { valueLabelOptions } from '@/shared/utils/selectOptions';
import { date } from '@/composables/useFormat';

const props = defineProps({
    show: { type: Boolean, default: false },
    blocker: { type: Object, default: null },
    /** Khi mở từ «Hướng xử lý» — tab Hướng xử lý + focus ô nhập */
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
const activeTab = ref('content');

const EDIT_TABS = [
    { key: 'resolution', label: 'Hướng xử lý', icon: 'meeting-notes' },
    { key: 'content', label: 'Nội dung', icon: 'blockers' },
    { key: 'assignment', label: 'Phân công', icon: 'people' },
];

const CREATE_TABS = [
    { key: 'content', label: 'Nội dung', icon: 'blockers' },
    { key: 'assignment', label: 'Phân công', icon: 'people' },
];

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
        activeTab.value = props.focusResolution ? 'resolution' : 'content';
    } else {
        form.reset();
        form.project_id = props.defaultProjectId;
        form.severity = 'medium';
        form.status = 'open';
        form.resolution = '';
        activeTab.value = 'content';
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

const tabs = computed(() => (isEdit.value ? EDIT_TABS : CREATE_TABS));

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
    if (!isEdit.value) return 'Ghi nhận vướng mắc';
    if (props.focusResolution || activeTab.value === 'resolution') return 'Lưu hướng xử lý';
    return 'Lưu thay đổi';
});

function setTab(key) {
    activeTab.value = key;
    if (key === 'resolution') {
        nextTick(() => resolutionInputRef.value?.focus?.());
    }
}

function textOrDash(value) {
    const t = (value ?? '').trim();
    return t || null;
}

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
    max-width="max-w-3xl"
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

      <div
        class="mb-4 flex flex-wrap gap-1 border-b border-slate-200 pb-2"
        role="tablist"
        aria-label="Phần biểu mẫu vướng mắc"
      >
        <button
          v-for="tab in tabs"
          :key="tab.key"
          type="button"
          role="tab"
          class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium transition"
          :class="activeTab === tab.key
            ? 'bg-brand text-white shadow-sm'
            : 'text-slate-600 hover:bg-slate-100'"
          :aria-selected="activeTab === tab.key"
          @click="setTab(tab.key)"
        >
          <AppIcon
            :name="tab.icon"
            :size="14"
          />
          {{ tab.label }}
        </button>
      </div>

      <div class="min-h-[14rem] space-y-4">
        <!-- Tab: Hướng xử lý (chỉ sửa) -->
        <div
          v-show="isEdit && activeTab === 'resolution'"
          class="space-y-4"
        >
          <div class="rounded-lg border border-slate-200 bg-slate-50/90 p-3">
            <div class="flex flex-wrap items-center gap-2">
              <span class="font-mono text-xs font-semibold text-brand">{{ blocker.code }}</span>
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

          <p class="text-xs text-slate-500">
            Trạng thái và người phụ trách chỉnh ở tab <button
              type="button"
              class="font-medium text-brand hover:underline"
              @click="setTab('assignment')"
            >
              Phân công
            </button>.
          </p>
        </div>

        <!-- Tab: Nội dung -->
        <div
          v-show="activeTab === 'content'"
          class="space-y-4"
        >
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
          <div>
            <label class="label flex items-center gap-1.5">
              Mô tả chi tiết
              <FieldTooltip text="Bối cảnh, tác động và phạm vi ảnh hưởng." />
            </label>
            <textarea
              v-model="form.description"
              rows="4"
              class="input resize-y"
              placeholder="Mô tả bối cảnh, tác động…"
            />
          </div>
          <div>
            <label class="label flex items-center gap-1.5">
              Nguyên nhân
              <FieldTooltip text="Nguyên nhân gốc nếu đã xác định." />
            </label>
            <textarea
              v-model="form.root_cause"
              rows="3"
              class="input resize-y"
              placeholder="Nguyên nhân gốc (nếu có)…"
            />
          </div>
        </div>

        <!-- Tab: Phân công -->
        <div
          v-show="activeTab === 'assignment'"
          class="space-y-4"
        >
          <div class="grid gap-4 sm:grid-cols-2">
            <div>
              <label class="label flex items-center gap-1.5">
                Mức độ
                <FieldTooltip text="Mức nghiêm trọng / ưu tiên xử lý." />
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
                <FieldTooltip text="Trạng thái xử lý hiện tại." />
              </label>
              <SearchSelect
                v-model="form.status"
                :options="statusSelectOptions"
                placeholder="Chọn trạng thái…"
                :clearable="false"
              />
            </div>
            <div>
              <label class="label flex items-center gap-1.5">
                Hạn xử lý
                <FieldTooltip text="Thời hạn mong muốn xử lý xong." />
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
                <FieldTooltip text="Người theo dõi và xử lý vướng mắc." />
              </label>
              <PersonSelect
                v-model="form.owner_id"
                :options="employees"
                placeholder="Tìm & chọn…"
              />
            </div>
          </div>

          <div
            v-if="isEdit && textOrDash(form.resolution)"
            class="rounded-lg border border-dashed border-slate-200 bg-slate-50/60 p-3"
          >
            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">
              Hướng xử lý (tóm tắt)
            </p>
            <p class="mt-1 line-clamp-3 whitespace-pre-wrap text-sm text-slate-600">
              {{ form.resolution }}
            </p>
            <button
              type="button"
              class="mt-2 text-xs font-medium text-brand hover:underline"
              @click="setTab('resolution')"
            >
              Mở tab Hướng xử lý
            </button>
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
