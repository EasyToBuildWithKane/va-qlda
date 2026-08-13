<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import RadioCard from '@/shared/ui/RadioCard.vue';
import PersonSelect from '@/modules/project/components/PersonSelect.vue';
import ProjectDepartmentAccessPanel from '@/modules/project/components/ProjectDepartmentAccessPanel.vue';
import SearchSelect from '@/shared/ui/SearchSelect.vue';
import SearchMultiSelect from '@/shared/ui/SearchMultiSelect.vue';
import KbRichTextField from '@/modules/knowledge-base/components/KbRichTextField.vue';
import { valueLabelOptions } from '@/shared/utils/selectOptions';
import { PROJECT_COLOR_OPTIONS, PROJECT_COLOR_SWATCH } from '@/modules/project/utils/projectColors';
import { displayOrEmpty } from '@/shared/utils/emptyDisplay';
import { useDialog } from '@/composables/useDialog';
import { useToast } from '@/shared/composables/useToast';
import {
    draftTitle,
    formatDraftSavedAt,
    hasDraftContent,
    useProjectCreateDraft,
} from '@/composables/useProjectCreateDraft';

const props = defineProps({
    project: { type: Object, default: null },
    employees: { type: Array, default: () => [] },
    statusOptions: { type: Array, default: () => [] },
    typeOptions: { type: Array, default: () => [] },
    scopeOptions: { type: Array, default: () => [] },
    regionOptions: { type: Array, default: () => [] },
    departmentOptions: { type: Array, default: () => [] },
    suggestedCode: { type: String, default: '' },
    defaultDepartmentId: { type: Number, default: null },
});

const isCreate = computed(() => !props.project);

const colors = PROJECT_COLOR_OPTIONS;
const swatch = PROJECT_COLOR_SWATCH;

const form = useForm({
    code: props.project?.code ?? props.suggestedCode,
    name: props.project?.name ?? '',
    description: props.project?.description ?? '',
    color: props.project?.color ?? 'brand',
    status: props.project?.status?.value ?? 'planning',
    type: props.project?.type?.value ?? 'rnd',
    scope: props.project?.scope?.value ?? 'headquarters',
    scope_regions: (props.project?.scope_regions ?? []).filter((v) =>
        (props.regionOptions ?? []).some((o) => o.value === v),
    ),
    scope_departments: props.project?.scope_departments ?? [],
    department_id: props.project?.department_id ?? props.defaultDepartmentId ?? null,
    start_date: props.project?.start_date ?? null,
    due_date: props.project?.due_date ?? null,
    manager_id: props.project?.manager_id ?? null,
    is_active: props.project?.is_active ?? true,
});

const typeSelectOptions = computed(() => valueLabelOptions(props.typeOptions));
const statusSelectOptions = computed(() => valueLabelOptions(props.statusOptions));

watch(() => form.scope, (s) => {
    if (s !== 'regional') form.scope_regions = [];
});
watch(() => form.department_id, (id) => {
    if (id == null || id === '') return;
    const n = Number(id);
    form.scope_departments = (form.scope_departments ?? []).filter((x) => Number(x) !== n);
});

const submitted = ref(false);
const touched = reactive(new Set());
const touch = (f) => touched.add(f);

const liveErrors = computed(() => {
    const e = {};
    if (!form.name?.trim()) e.name = 'Tên dự án không được để trống.';
    if (!form.scope) e.scope = 'Phải chọn ít nhất một phạm vi áp dụng.';
    if (form.scope === 'regional' && form.scope_regions.length === 0) e.scope_regions = 'Chọn ít nhất một khu vực.';
    if (form.start_date && form.due_date && form.due_date < form.start_date) e.due_date = 'Ngày kết thúc phải lớn hơn ngày bắt đầu.';
    return e;
});

const errFor = (f) => form.errors[f] || ((submitted.value || touched.has(f)) ? liveErrors.value[f] : null);

const fieldTabByKey = {
    name: 0,
    code: 0,
    type: 0,
    description: 0,
    scope: 1,
    scope_regions: 1,
    scope_departments: 1,
    department_id: 1,
    start_date: 2,
    due_date: 2,
    manager_id: 3,
    status: 3,
};

const formErrorList = computed(() => Object.entries(form.errors).filter(([, v]) => v));

const hasVisibleErrors = computed(
    () => formErrorList.value.length > 0
        || (submitted.value && Object.keys(liveErrors.value).length > 0),
);

function focusTabForField(fieldKey) {
    if (fieldKey && fieldTabByKey[fieldKey] !== undefined) {
        activeTab.value = fieldTabByKey[fieldKey];
    }
}

function focusFirstError(errorMap) {
    const firstKey = Object.keys(errorMap)[0];
    if (firstKey) {
        focusTabForField(firstKey.split('.')[0]);
    }
}

const validDepartmentIds = computed(() => new Set(props.departmentOptions.map((d) => Number(d.id))));

function sanitizeDepartmentPayload(data) {
    const out = { ...data };
    const deptId = out.department_id != null && out.department_id !== '' ? Number(out.department_id) : null;
    if (deptId != null && !validDepartmentIds.value.has(deptId)) {
        const fallback = props.defaultDepartmentId != null ? Number(props.defaultDepartmentId) : null;
        out.department_id = fallback != null && validDepartmentIds.value.has(fallback) ? fallback : null;
    }
    if (Array.isArray(out.scope_departments)) {
        out.scope_departments = out.scope_departments
            .map((id) => Number(id))
            .filter((id) => validDepartmentIds.value.has(id));
    }
    return out;
}

function showValidationToast(errorMap) {
    const first = Object.values(errorMap)[0];
    toast.error(typeof first === 'string' ? first : 'Vui lòng kiểm tra lại biểu mẫu.');
}

const dayCount = computed(() => {
    if (!form.start_date || !form.due_date) return null;
    const s = new Date(form.start_date);
    const d = new Date(form.due_date);
    if (Number.isNaN(s) || Number.isNaN(d) || d < s) return null;
    return Math.round((d - s) / 86400000) + 1;
});

const tracked = computed(() => [
    { key: 'name', label: 'Tên dự án', required: true, filled: !!form.name?.trim() },
    { key: 'code', label: 'Mã dự án', filled: !!form.code?.trim() },
    { key: 'type', label: 'Loại dự án', required: true, filled: !!form.type },
    { key: 'scope', label: 'Phạm vi triển khai', required: true, filled: !!form.scope },
    { key: 'start_date', label: 'Ngày bắt đầu', filled: !!form.start_date },
    { key: 'due_date', label: 'Ngày kết thúc', filled: !!form.due_date },
    { key: 'manager_id', label: 'Chủ dự án', filled: !!form.manager_id },
    { key: 'description', label: 'Mô tả', filled: !!form.description?.trim() },
]);
const filledCount = computed(() => tracked.value.filter((t) => t.filled).length);
const completion = computed(() => Math.round((filledCount.value / tracked.value.length) * 100));

const dayCountLabel = computed(() => (
    dayCount.value === null
        ? displayOrEmpty(null, 'Chưa chọn ngày')
        : `${dayCount.value} ngày`
));

const tabs = [
    { key: 'basic', title: 'Thông tin', icon: 'edit' },
    { key: 'scope', title: 'Phạm vi', icon: 'globe' },
    { key: 'time', title: 'Thời gian', icon: 'calendar-clock' },
    { key: 'people', title: 'Nhân sự', icon: 'members' },
];

const activeTab = ref(0);
const activeTabMeta = computed(() => tabs[activeTab.value]);

const dialog = useDialog();
const toast = useToast();
const draftStore = useProjectCreateDraft();
const draftsOpen = ref(true);

onMounted(() => {
    if (isCreate.value) draftStore.refresh();
});

const applyDraft = (draft) => {
    const d = draft.data;
    form.name = d.name ?? '';
    form.description = d.description ?? '';
    form.color = d.color ?? 'brand';
    form.status = d.status ?? 'planning';
    form.type = d.type ?? 'rnd';
    form.scope = d.scope ?? 'headquarters';
    form.scope_regions = (d.scope_regions ?? []).filter((v) =>
        (props.regionOptions ?? []).some((o) => o.value === v),
    );
    form.scope_departments = d.scope_departments ?? [];
    form.department_id = d.department_id ?? props.defaultDepartmentId ?? null;
    form.start_date = d.start_date ?? null;
    form.due_date = d.due_date ?? null;
    form.manager_id = d.manager_id ?? null;
    form.is_active = d.is_active ?? true;
    form.code = props.suggestedCode;
    activeTab.value = draft.activeTab ?? 0;
    draftStore.activeDraftId.value = draft.id;
    submitted.value = false;
    touched.clear();
};

const loadDraft = async (draft) => {
    if (hasDraftContent(form.data())) {
        const ok = await dialog.confirm({
            title: 'Lấy bản nháp?',
            message: 'Nội dung đang nhập sẽ bị thay bằng bản nháp đã chọn.',
            confirmText: 'Lấy bản nháp',
            cancelText: 'Huỷ',
        });
        if (!ok) return;
    }
    applyDraft(draft);
    toast.success(`Đã lấy bản nháp: ${draftTitle(draft)}`);
};

const saveLocalDraft = () => {
    if (!hasDraftContent(form.data())) {
        toast.warning('Chưa có nội dung để lưu nháp.');
        return;
    }
    draftStore.save(form.data(), activeTab.value);
    toast.success('Đã lưu bản nháp trên trình duyệt.');
};

const deleteDraft = async (draft) => {
    const ok = await dialog.confirm({
        title: 'Xóa bản nháp?',
        message: `Xóa "${draftTitle(draft)}" khỏi danh sách đã lưu?`,
        confirmText: 'Xóa',
        cancelText: 'Huỷ',
        tone: 'danger',
    });
    if (!ok) return;
    draftStore.remove(draft.id);
    toast.info('Đã xóa bản nháp.');
};

const submit = (after = 'close') => {
    submitted.value = true;

    const clientErrs = { ...liveErrors.value };
    if (Object.keys(clientErrs).length > 0) {
        focusFirstError(clientErrs);
        showValidationToast(clientErrs);
        return;
    }

    const postOptions = {
        preserveScroll: true,
        onSuccess: () => {
            if (isCreate.value && draftStore.activeDraftId.value) {
                draftStore.remove(draftStore.activeDraftId.value);
            }
        },
        onError: (errors) => {
            focusFirstError(errors);
            showValidationToast(errors);
        },
        onFinish: () => {
            form.transform((data) => data);
        },
    };

    if (props.project) {
        form.transform((data) => sanitizeDepartmentPayload(data));
        form.put(`/projects/${props.project.id}`, postOptions);
        return;
    }

    form.transform((data) => sanitizeDepartmentPayload({ ...data, after }));
    form.post('/projects', postOptions);
};
</script>

<template>
  <div class="space-y-5">
    <div
      v-if="isCreate"
      class="card p-4"
    >
      <button
        type="button"
        class="flex w-full items-center justify-between gap-2 text-left"
        @click="draftsOpen = !draftsOpen"
      >
        <h3 class="flex items-center gap-2 font-display text-sm font-semibold text-slate-800">
          <AppIcon
            name="save"
            :size="16"
            class="text-brand"
          />
          Bản nháp đã lưu
          <span
            v-if="draftStore.drafts.length"
            class="text-sm font-medium text-slate-500"
          >({{ draftStore.drafts.length }})</span>
        </h3>
        <AppIcon
          name="chevron-down"
          :size="16"
          class="shrink-0 text-slate-400 transition"
          :class="draftsOpen ? 'rotate-180' : ''"
        />
      </button>
      <div
        v-show="draftsOpen"
        class="mt-3"
      >
        <p
          v-if="!draftStore.drafts.length"
          class="text-sm text-slate-500"
        >
          Chưa có bản nháp. Nhập thông tin rồi bấm Lưu nháp ở thanh dưới.
        </p>
        <ul
          v-else
          class="grid gap-2 sm:grid-cols-2"
        >
          <li
            v-for="d in draftStore.drafts"
            :key="d.id"
            class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-slate-50/80 px-3 py-2.5"
            :class="draftStore.activeDraftId === d.id ? 'border-brand/40 ring-1 ring-brand/20' : ''"
          >
            <div class="min-w-0">
              <p class="truncate text-sm font-medium text-slate-800">
                {{ draftTitle(d) }}
              </p>
              <p class="mt-0.5 text-xs text-slate-400">
                {{ formatDraftSavedAt(d.savedAt) }}
              </p>
            </div>
            <div class="flex shrink-0 items-center gap-1.5">
              <button
                type="button"
                class="btn-primary py-1 px-2.5 text-xs"
                @click="loadDraft(d)"
              >
                Lấy
              </button>
              <button
                type="button"
                class="btn-ghost py-1 px-2 text-xs text-rose-600"
                @click="deleteDraft(d)"
              >
                Xóa
              </button>
            </div>
          </li>
        </ul>
      </div>
    </div>

    <form @submit.prevent="submit('close')">
      <div
        v-if="hasVisibleErrors"
        class="mb-4 rounded-card border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900"
        role="alert"
      >
        <p class="font-semibold">
          Không thể lưu dự án
        </p>
        <ul class="mt-2 list-inside list-disc space-y-0.5 text-rose-800">
          <li
            v-for="(msg, key) in form.errors"
            :key="key"
          >
            {{ msg }}
          </li>
          <template v-if="!formErrorList.length">
            <li
              v-for="(msg, key) in liveErrors"
              :key="'live-' + key"
            >
              {{ msg }}
            </li>
          </template>
        </ul>
      </div>

      <div class="card overflow-visible">
        <div class="flex border-b border-slate-200">
          <button
            v-for="(t, i) in tabs"
            :key="t.key"
            type="button"
            class="flex min-w-0 flex-1 items-center justify-center gap-2 border-b-2 px-3 py-3.5 text-sm font-medium transition"
            :class="activeTab === i
              ? 'border-brand bg-brand-50/40 text-brand'
              : 'border-transparent text-slate-500 hover:bg-slate-50 hover:text-slate-700'"
            @click="activeTab = i"
          >
            <AppIcon
              :name="t.icon"
              :size="16"
              class="shrink-0"
            />
            <span class="truncate">{{ t.title }}</span>
          </button>
        </div>

        <div class="overflow-visible p-6 sm:p-7">
          <!-- Tab: Thông tin cơ bản -->
          <div
            v-show="activeTabMeta.key === 'basic'"
            class="space-y-5"
          >
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
              <div class="sm:col-span-2">
                <label class="label">
                  Tên dự án <span class="text-danger">*</span>
                </label>
                <input
                  v-model="form.name"
                  type="text"
                  class="input"
                  :class="errFor('name') ? 'border-danger focus:border-danger focus:ring-danger/30' : ''"
                  placeholder="Tên dự án"
                  @blur="touch('name')"
                >
                <p
                  v-if="errFor('name')"
                  class="mt-1.5 flex items-center gap-1 text-xs text-danger"
                >
                  <AppIcon
                    name="close"
                    :size="12"
                  /> {{ errFor('name') }}
                </p>
              </div>
              <div>
                <label class="label">Mã dự án</label>
                <input
                  v-model="form.code"
                  type="text"
                  class="input cursor-not-allowed bg-slate-50 font-mono text-slate-600"
                  placeholder="PRJ-001"
                  readonly
                  tabindex="-1"
                >
                <p
                  v-if="form.errors.code"
                  class="mt-1.5 text-xs text-danger"
                >
                  {{ form.errors.code }}
                </p>
              </div>
              <div class="sm:col-span-2">
                <label class="label">
                  Loại dự án <span class="text-danger">*</span>
                </label>
                <SearchSelect
                  v-model="form.type"
                  :options="typeSelectOptions"
                  placeholder="Chọn loại dự án…"
                  :clearable="false"
                />
                <p
                  v-if="form.errors.type"
                  class="mt-1.5 text-xs text-danger"
                >
                  {{ form.errors.type }}
                </p>
              </div>
              <div>
                <label class="label">Màu nhãn</label>
                <div class="flex flex-wrap items-center gap-2 pt-1">
                  <button
                    v-for="c in colors"
                    :key="c.key"
                    type="button"
                    class="h-8 w-8 rounded-full ring-offset-2 transition hover:scale-105"
                    :class="[swatch[c.key], form.color === c.key ? 'ring-2 ring-brand' : 'ring-1 ring-black/5']"
                    :aria-label="c.label"
                    :title="c.label"
                    @click="form.color = c.key"
                  />
                </div>
              </div>
              <div class="sm:col-span-3">
                <KbRichTextField
                  v-model="form.description"
                  label="Mô tả"
                  placeholder="Mô tả mục tiêu, phạm vi, kết quả kỳ vọng…"
                  editor-min-height-class="min-h-[16rem] sm:min-h-[18rem]"
                  :error="form.errors.description"
                />
              </div>
            </div>
          </div>

          <!-- Tab: Phạm vi -->
          <div
            v-show="activeTabMeta.key === 'scope'"
            class="space-y-6"
          >
            <div>
              <label class="label">
                Phạm vi triển khai <span class="text-danger">*</span>
              </label>
              <div
                class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-2"
                role="radiogroup"
              >
                <RadioCard
                  v-for="o in scopeOptions"
                  :key="o.value"
                  v-model="form.scope"
                  :value="o.value"
                  :label="o.label"
                  :icon="o.icon"
                />
              </div>
              <p
                v-if="errFor('scope')"
                class="mt-2 flex items-center gap-1 text-xs text-danger"
              >
                <AppIcon
                  name="close"
                  :size="12"
                /> {{ errFor('scope') }}
              </p>
            </div>

            <div v-if="form.scope === 'regional'">
              <label class="label">Khu vực áp dụng</label>
              <SearchMultiSelect
                v-model="form.scope_regions"
                :options="regionOptions"
                value-key="value"
                label-key="label"
                placeholder="Tìm & chọn khu vực…"
                search-placeholder="Tìm khu vực…"
                :max-chips="12"
                @update:model-value="touch('scope_regions')"
              />
              <p
                v-if="errFor('scope_regions')"
                class="mt-2 flex items-center gap-1 text-xs text-danger"
              >
                <AppIcon
                  name="close"
                  :size="12"
                /> {{ errFor('scope_regions') }}
              </p>
            </div>

            <ProjectDepartmentAccessPanel
              :department-options="departmentOptions"
              :owner-id="form.department_id"
              :related-ids="form.scope_departments"
              :owner-error="errFor('department_id')"
              :related-error="errFor('scope_departments')"
              @update:owner-id="form.department_id = $event"
              @update:related-ids="form.scope_departments = $event"
              @touch-owner="touch('department_id')"
              @touch-related="touch('scope_departments')"
            />
          </div>

          <!-- Tab: Thời gian -->
          <div
            v-show="activeTabMeta.key === 'time'"
            class="space-y-5"
          >
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
              <div>
                <label class="label">Ngày bắt đầu</label>
                <input
                  v-model="form.start_date"
                  type="date"
                  class="input"
                  @blur="touch('due_date')"
                >
              </div>
              <div>
                <label class="label">Ngày kết thúc</label>
                <input
                  v-model="form.due_date"
                  type="date"
                  class="input"
                  :class="errFor('due_date') ? 'border-danger focus:border-danger focus:ring-danger/30' : ''"
                  @blur="touch('due_date')"
                >
              </div>
              <div>
                <label class="label">Số ngày thực hiện</label>
                <div class="flex h-[42px] items-center gap-2 rounded-input border border-slate-200 bg-slate-50 px-3">
                  <AppIcon
                    name="clock"
                    :size="16"
                    class="text-slate-400"
                  />
                  <span class="font-medium text-slate-700">{{ dayCountLabel }}</span>
                </div>
              </div>
            </div>
            <p
              v-if="errFor('due_date')"
              class="flex items-center gap-1 text-xs text-danger"
            >
              <AppIcon
                name="close"
                :size="12"
              /> {{ errFor('due_date') }}
            </p>
          </div>

          <!-- Tab: Nhân sự -->
          <div
            v-show="activeTabMeta.key === 'people'"
            class="space-y-5"
          >
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
              <div>
                <label class="label">Chủ dự án / Quản lý</label>
                <PersonSelect
                  v-model="form.manager_id"
                  :options="employees"
                />
                <p
                  v-if="form.errors.manager_id"
                  class="mt-1.5 text-xs text-danger"
                >
                  {{ form.errors.manager_id }}
                </p>
              </div>
              <div>
                <label class="label">Trạng thái dự án</label>
                <SearchSelect
                  v-model="form.status"
                  :options="statusSelectOptions"
                  placeholder="Chọn trạng thái…"
                  :clearable="false"
                />
              </div>
            </div>
            <label class="flex items-center gap-2.5 text-sm text-slate-700">
              <input
                v-model="form.is_active"
                type="checkbox"
                class="rounded border-slate-300 text-brand focus:ring-brand/30"
              >
              Dự án đang hoạt động
            </label>
          </div>

          <div class="mt-6 flex items-center justify-between border-t border-slate-100 pt-4">
            <button
              type="button"
              class="btn-ghost"
              :disabled="activeTab === 0"
              @click="activeTab--"
            >
              ← Trước
            </button>
            <span class="text-xs text-slate-400">Bước {{ activeTab + 1 }}/{{ tabs.length }}</span>
            <button
              type="button"
              class="btn-ghost"
              :disabled="activeTab === tabs.length - 1"
              @click="activeTab++"
            >
              Tiếp →
            </button>
          </div>
        </div>
      </div>

      <div class="sticky bottom-0 z-20 mt-5 -mx-1">
        <div class="card flex flex-wrap items-center gap-2 px-4 py-3 shadow-elevation-2">
          <p
            v-if="form.processing"
            class="text-xs text-slate-400"
          >
            Đang lưu…
          </p>
          <p
            v-else-if="completion < 100"
            class="hidden text-xs text-slate-400 sm:block"
          >
            Hoàn thành {{ completion }}% biểu mẫu
          </p>
          <div class="ml-auto flex flex-wrap items-center gap-2">
            <a
              href="/projects"
              class="btn-ghost"
            >Hủy</a>
            <button
              v-if="isCreate"
              type="button"
              class="btn-ghost"
              :disabled="form.processing"
              @click="saveLocalDraft"
            >
              Lưu nháp
            </button>
            <button
              type="button"
              class="btn-ghost border border-slate-200"
              :disabled="form.processing"
              @click="submit('continue')"
            >
              Lưu &amp; tiếp tục
            </button>
            <button
              type="button"
              class="btn-primary"
              :disabled="form.processing"
              @click="submit('close')"
            >
              <AppIcon
                name="save"
                :size="16"
              /> {{ project ? 'Lưu & đóng' : 'Tạo & đóng' }}
            </button>
          </div>
        </div>
      </div>
    </form>
  </div>
</template>
