<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Modal from '@/Components/Ui/Modal.vue';
import AutocompleteInput from '@/shared/ui/form/AutocompleteInput.vue';
import { useToast } from '@/shared/composables/useToast';

const props = defineProps({
    show: { type: Boolean, default: false },
    testCase: { type: Object, default: null },
    projectId: { type: Number, default: null },
    projectCode: { type: String, default: '' },
    projectName: { type: String, default: '' },
    projects: { type: Array, default: () => [] },
    testSuites: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
    priorityOptions: { type: Array, default: () => [] },
    statusOptions: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'saved']);
const toast = useToast();

const isEditing = computed(() => Boolean(props.testCase?.id));
const lockProject = computed(() => Boolean(props.projectId));

const form = useForm({
    project_id: null,
    suite_id: null,
    suite_name: '',
    title: '',
    preconditions: '',
    steps: [],
    expected_result: '',
    priority: 'medium',
    status: 'draft',
    owner_id: null,
});

const projectOptions = computed(() => (props.projects ?? []).map((p) => ({
    id: p.id ?? p.value,
    name: p.name ?? p.label,
    code: p.code ?? '',
})));

const employeeOptions = computed(() => (props.employees ?? []).map((e) => ({
    id: e.id ?? e.value,
    name: e.name ?? e.label,
})));

const suitesForProject = computed(() => {
    const pid = Number(form.project_id);
    if (!pid) return [];
    return (props.testSuites ?? []).filter((s) => Number(s.project_id) === pid);
});

const lockedProjectLabel = computed(() => {
    if (props.projectName && props.projectCode) return `${props.projectCode} · ${props.projectName}`;
    return props.projectName || props.projectCode || '';
});

function resetForm(tc) {
    form.project_id = tc?.project_id ?? props.projectId ?? null;
    form.suite_id = tc?.suite_id ?? tc?.suite?.id ?? null;
    form.suite_name = '';
    form.title = tc?.title ?? '';
    form.preconditions = tc?.preconditions ?? '';
    form.steps = tc?.steps ? JSON.parse(JSON.stringify(tc.steps)) : [];
    form.expected_result = tc?.expected_result ?? '';
    form.priority = tc?.priority?.value ?? 'medium';
    form.status = tc?.status?.value ?? 'draft';
    form.owner_id = tc?.owner_id ?? tc?.owner?.id ?? null;
    form.clearErrors();
}

watch(() => props.show, (v) => {
    if (v) resetForm(props.testCase);
}, { immediate: true });

function onProjectChange(id) {
    const prev = form.project_id;
    form.project_id = id;
    if (String(prev ?? '') !== String(id ?? '')) {
        form.suite_id = null;
        form.suite_name = '';
    }
}

function onSuiteChange(id) {
    form.suite_id = id;
    if (id) form.suite_name = '';
}

function onSuiteCreate(name) {
    form.suite_id = null;
    form.suite_name = name || '';
}

function addStep() {
    form.steps.push({ step: '', expected: '' });
}

function removeStep(idx) {
    form.steps.splice(idx, 1);
}

function moveStep(idx, dir) {
    const to = idx + dir;
    if (to < 0 || to >= form.steps.length) return;
    const temp = form.steps[idx];
    form.steps[idx] = form.steps[to];
    form.steps[to] = temp;
}

function submit() {
    const routeName = isEditing.value
        ? route('test-cases.update', { testCase: props.testCase.id })
        : route('test-cases.store');
    const method = isEditing.value ? 'put' : 'post';

    form[method](routeName, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success(isEditing.value ? 'Đã cập nhật test case.' : 'Đã thêm test case.');
            emit('saved');
            emit('close');
        },
        onError: () => toast.error('Có lỗi xảy ra. Vui lòng kiểm tra lại.'),
    });
}
</script>

<template>
  <Modal
    :show="show"
    :title="isEditing ? 'Sửa test case' : 'Thêm test case'"
    max-width="max-w-xl"
    fit-viewport
    :dirty="form.isDirty"
    close-confirm-title="Huỷ thao tác?"
    close-confirm-message="Nội dung chưa lưu sẽ bị mất."
    @close="emit('close')"
  >
    <form
      class="flex min-h-0 flex-1 flex-col overflow-hidden"
      @submit.prevent="submit"
    >
      <div class="min-h-0 flex-1 space-y-3 overflow-y-auto overscroll-contain pr-0.5 [-webkit-overflow-scrolling:touch]">
        <!-- Project -->
        <div v-if="lockProject">
          <p class="label mb-1">
            Dự án
          </p>
          <div class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
            <AppIcon
              name="folder"
              :size="14"
              class="shrink-0 text-brand"
            />
            <span class="min-w-0 truncate font-medium text-slate-800">{{ lockedProjectLabel }}</span>
          </div>
        </div>
        <div v-else>
          <label
            class="label mb-1"
            for="tc-project"
          >Dự án <span class="text-rose-500">*</span></label>
          <AutocompleteInput
            id="tc-project"
            :model-value="form.project_id"
            :options="projectOptions"
            placeholder="Gõ tên hoặc mã dự án…"
            empty-text="Không tìm thấy dự án."
            :search-keys="['name', 'code']"
            subtitle-key="code"
            :panel-z-index="160"
            @update:model-value="onProjectChange"
          />
          <p
            v-if="form.errors.project_id"
            class="mt-1 text-xs text-rose-600"
          >
            {{ form.errors.project_id }}
          </p>
        </div>

        <!-- Title -->
        <div>
          <label
            class="label mb-1"
            for="tc-title"
          >Tiêu đề <span class="text-rose-500">*</span></label>
          <input
            id="tc-title"
            v-model="form.title"
            type="text"
            class="input w-full"
            placeholder="Ví dụ: Đăng nhập thành công với tài khoản hợp lệ"
            maxlength="255"
          >
          <p
            v-if="form.errors.title"
            class="mt-1 text-xs text-rose-600"
          >
            {{ form.errors.title }}
          </p>
        </div>

        <!-- Suite — optional grouping (plain language, not "test suite") -->
        <div>
          <label
            class="label mb-1"
            for="tc-suite"
          >
            Nhóm kiểm thử
            <span class="font-normal text-slate-400">(không bắt buộc)</span>
          </label>
          <AutocompleteInput
            id="tc-suite"
            :model-value="form.suite_id"
            :options="suitesForProject"
            :disabled="!form.project_id"
            :created-label="form.suite_name"
            placeholder="Ví dụ: Đăng nhập, Thanh toán…"
            empty-text="Chưa có nhóm. Gõ tên rồi chọn «Tạo nhóm»."
            creatable
            create-label="Tạo nhóm «{query}»"
            :panel-z-index="160"
            @update:model-value="onSuiteChange"
            @create="onSuiteCreate"
          />
          <p class="mt-1 text-[11px] leading-relaxed text-slate-400">
            {{ form.project_id
              ? 'Dùng để gom các test case cùng một tính năng hoặc màn hình. Có thể bỏ trống.'
              : 'Chọn dự án trước — rồi gắn hoặc tạo nhóm nếu cần.' }}
          </p>
        </div>

        <!-- Priority + Status + Owner -->
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
          <div>
            <label
              class="label mb-1"
              for="tc-priority"
            >Ưu tiên <span class="text-rose-500">*</span></label>
            <select
              id="tc-priority"
              v-model="form.priority"
              class="input w-full"
            >
              <option
                v-for="opt in priorityOptions"
                :key="opt.value"
                :value="opt.value"
              >
                {{ opt.label }}
              </option>
            </select>
            <p
              v-if="form.errors.priority"
              class="mt-1 text-xs text-rose-600"
            >
              {{ form.errors.priority }}
            </p>
          </div>

          <div>
            <label
              class="label mb-1"
              for="tc-status"
            >Trạng thái</label>
            <select
              id="tc-status"
              v-model="form.status"
              class="input w-full"
            >
              <option
                v-for="opt in statusOptions"
                :key="opt.value"
                :value="opt.value"
              >
                {{ opt.label }}
              </option>
            </select>
          </div>

          <div>
            <label
              class="label mb-1"
              for="tc-owner"
            >Người phụ trách</label>
            <AutocompleteInput
              id="tc-owner"
              :model-value="form.owner_id"
              :options="employeeOptions"
              placeholder="Gõ tên để tìm…"
              empty-text="Không tìm thấy nhân sự."
              clearable
              :panel-z-index="160"
              @update:model-value="(v) => { form.owner_id = v; }"
            />
          </div>
        </div>

        <!-- Preconditions -->
        <div>
          <label
            class="label mb-1"
            for="tc-preconditions"
          >Điều kiện tiên quyết</label>
          <textarea
            id="tc-preconditions"
            v-model="form.preconditions"
            class="input w-full resize-y"
            rows="2"
            placeholder="Cần có trước khi chạy, ví dụ: đã đăng nhập, dữ liệu mẫu sẵn…"
            maxlength="10000"
          />
        </div>

        <!-- Steps -->
        <div>
          <div class="mb-1.5 flex items-center justify-between gap-2">
            <label class="label mb-0">Các bước kiểm thử</label>
            <button
              type="button"
              class="inline-flex items-center gap-1 rounded-md bg-brand/10 px-2 py-1 text-xs font-medium text-brand hover:bg-brand/20"
              @click="addStep"
            >
              <AppIcon
                name="plus"
                :size="12"
              />
              Thêm bước
            </button>
          </div>

          <div
            v-if="!form.steps.length"
            class="rounded-lg border border-dashed border-slate-200 px-3 py-3 text-center text-xs text-slate-400"
          >
            Tuỳ chọn — bấm «Thêm bước» nếu cần mô tả từng thao tác.
          </div>

          <div class="space-y-2">
            <div
              v-for="(step, idx) in form.steps"
              :key="idx"
              class="flex gap-2 rounded-lg border border-slate-200 bg-slate-50/50 p-2"
            >
              <div class="flex shrink-0 flex-col items-center gap-0.5 pt-0.5">
                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-brand/10 text-[10px] font-bold text-brand">{{ idx + 1 }}</span>
                <button
                  type="button"
                  class="rounded p-0.5 text-slate-300 hover:text-slate-500"
                  :disabled="idx === 0"
                  @click="moveStep(idx, -1)"
                >
                  <AppIcon
                    name="chevron-up"
                    :size="12"
                  />
                </button>
                <button
                  type="button"
                  class="rounded p-0.5 text-slate-300 hover:text-slate-500"
                  :disabled="idx === form.steps.length - 1"
                  @click="moveStep(idx, 1)"
                >
                  <AppIcon
                    name="chevron-down"
                    :size="12"
                  />
                </button>
              </div>

              <div class="flex min-w-0 flex-1 flex-col gap-1.5">
                <div>
                  <label class="mb-0.5 block text-[11px] font-semibold text-slate-500">Thao tác</label>
                  <textarea
                    v-model="step.step"
                    class="input w-full resize-none"
                    rows="2"
                    placeholder="Mô tả thao tác thực hiện…"
                    maxlength="2000"
                  />
                  <p
                    v-if="form.errors[`steps.${idx}.step`]"
                    class="mt-1 text-xs text-rose-600"
                  >
                    {{ form.errors[`steps.${idx}.step`] }}
                  </p>
                </div>
                <div>
                  <label class="mb-0.5 block text-[11px] font-semibold text-slate-500">Kết quả mong đợi bước này</label>
                  <input
                    v-model="step.expected"
                    type="text"
                    class="input w-full"
                    placeholder="Kết quả sau bước…"
                    maxlength="2000"
                  >
                </div>
              </div>

              <button
                type="button"
                class="self-start rounded p-1 text-slate-300 hover:bg-rose-50 hover:text-rose-500"
                @click="removeStep(idx)"
              >
                <AppIcon
                  name="trash"
                  :size="14"
                />
              </button>
            </div>
          </div>
        </div>

        <!-- Expected result overall -->
        <div>
          <label
            class="label mb-1"
            for="tc-expected"
          >Kết quả mong đợi (tổng thể)</label>
          <textarea
            id="tc-expected"
            v-model="form.expected_result"
            class="input w-full resize-y"
            rows="2"
            placeholder="Kết quả đạt yêu cầu khi hoàn thành toàn bộ test case…"
            maxlength="10000"
          />
        </div>
      </div>

      <div class="mt-2.5 flex shrink-0 justify-end gap-2 border-t border-slate-100 pt-2.5">
        <button
          type="button"
          class="btn-ghost h-9 px-4 text-sm"
          :disabled="form.processing"
          @click="emit('close')"
        >
          Hủy
        </button>
        <button
          type="submit"
          class="btn-primary h-9 gap-1.5 px-4 text-sm"
          :disabled="form.processing"
        >
          <AppIcon
            name="save"
            :size="14"
          />
          {{ form.processing ? 'Đang lưu…' : (isEditing ? 'Lưu thay đổi' : 'Thêm test case') }}
        </button>
      </div>
    </form>
  </Modal>
</template>
