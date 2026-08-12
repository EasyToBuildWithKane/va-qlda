<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Modal from '@/Components/Ui/Modal.vue';
import { useToast } from '@/shared/composables/useToast';
import { displayOrEmpty } from '@/shared/utils/emptyDisplay';

const props = defineProps({
    show: { type: Boolean, default: false },
    testCase: { type: Object, default: null },
    projectId: { type: Number, default: null },
    projectCode: { type: String, default: '' },
    projectName: { type: String, default: '' },
    testSuites: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
    priorityOptions: { type: Array, default: () => [] },
    statusOptions: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'saved']);
const toast = useToast();

const isEditing = computed(() => Boolean(props.testCase?.id));

const form = useForm({
    project_id: null,
    suite_id: null,
    title: '',
    preconditions: '',
    steps: [],
    expected_result: '',
    priority: 'medium',
    status: 'draft',
    owner_id: null,
});

function resetForm(tc) {
    form.project_id = tc?.project_id ?? props.projectId ?? null;
    form.suite_id = tc?.suite_id ?? null;
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

const suiteOptions = computed(() => [
    { id: null, name: 'Không thuộc bộ nào' },
    ...props.testSuites,
]);
</script>

<template>
  <Modal
    :show="show"
    :title="isEditing ? 'Sửa test case' : 'Thêm test case mới'"
    max-width="3xl"
    @close="emit('close')"
  >
    <template #header-icon>
      <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-brand/10 text-brand">
        <AppIcon
          name="check-circle"
          :size="16"
        />
      </span>
    </template>

    <form
      class="space-y-5 p-5"
      @submit.prevent="submit"
    >
      <!-- Project / Suite -->
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div v-if="!projectId">
          <label class="label">Dự án <span class="text-rose-500">*</span></label>
          <input
            v-model.number="form.project_id"
            type="number"
            class="input w-full"
            placeholder="ID dự án"
          >
          <p
            v-if="form.errors.project_id"
            class="mt-1 text-xs text-rose-600"
          >
            {{ form.errors.project_id }}
          </p>
        </div>

        <div>
          <label class="label">Bộ test (Suite)</label>
          <select
            v-model="form.suite_id"
            class="input w-full"
          >
            <option
              v-for="s in suiteOptions"
              :key="s.id"
              :value="s.id"
            >
              {{ s.name }}
            </option>
          </select>
        </div>
      </div>

      <!-- Title -->
      <div>
        <label class="label">Tiêu đề <span class="text-rose-500">*</span></label>
        <input
          v-model="form.title"
          type="text"
          class="input w-full"
          placeholder="Mô tả ngắn gọn test case…"
          maxlength="255"
          autofocus
        >
        <p
          v-if="form.errors.title"
          class="mt-1 text-xs text-rose-600"
        >
          {{ form.errors.title }}
        </p>
      </div>

      <!-- Priority + Status + Owner -->
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div>
          <label class="label">Mức độ ưu tiên <span class="text-rose-500">*</span></label>
          <select
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
          <label class="label">Trạng thái</label>
          <select
            v-model="form.status"
            class="input w-full"
          >
            <option value="">
              Nháp (mặc định)
            </option>
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
          <label class="label">Người phụ trách</label>
          <select
            v-model="form.owner_id"
            class="input w-full"
          >
            <option :value="null">
              {{ displayOrEmpty(null, 'Chưa gán') }}
            </option>
            <option
              v-for="emp in employees"
              :key="emp.id ?? emp.value"
              :value="emp.id ?? emp.value"
            >
              {{ emp.name ?? emp.label }}
            </option>
          </select>
        </div>
      </div>

      <!-- Preconditions -->
      <div>
        <label class="label">Điều kiện tiên quyết</label>
        <textarea
          v-model="form.preconditions"
          class="input w-full resize-y"
          rows="2"
          placeholder="Điều kiện cần có trước khi thực hiện test…"
          maxlength="10000"
        />
      </div>

      <!-- Steps -->
      <div>
        <div class="mb-2 flex items-center justify-between">
          <label class="label mb-0">Các bước kiểm thử</label>
          <button
            type="button"
            class="inline-flex items-center gap-1 rounded-md bg-brand/10 px-2.5 py-1 text-xs font-medium text-brand hover:bg-brand/20"
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
          class="rounded-xl border border-dashed border-slate-200 px-4 py-6 text-center text-sm text-slate-400"
        >
          Chưa có bước nào. Bấm «Thêm bước» để bắt đầu.
        </div>

        <div class="space-y-3">
          <div
            v-for="(step, idx) in form.steps"
            :key="idx"
            class="flex gap-2 rounded-xl border border-slate-200 bg-slate-50/50 p-3"
          >
            <div class="flex shrink-0 flex-col items-center gap-1 pt-1">
              <span class="flex h-6 w-6 items-center justify-center rounded-full bg-brand/10 text-[10px] font-bold text-brand">{{ idx + 1 }}</span>
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

            <div class="flex min-w-0 flex-1 flex-col gap-2">
              <div>
                <label class="mb-1 block text-[11px] font-semibold text-slate-500">Thao tác</label>
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
                <label class="mb-1 block text-[11px] font-semibold text-slate-500">Kết quả mong đợi bước này</label>
                <input
                  v-model="step.expected"
                  type="text"
                  class="input w-full"
                  placeholder="Kết quả mong đợi sau bước…"
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
        <label class="label">Kết quả mong đợi (tổng thể)</label>
        <textarea
          v-model="form.expected_result"
          class="input w-full resize-y"
          rows="2"
          placeholder="Mô tả kết quả đạt yêu cầu khi hoàn thành toàn bộ test case…"
          maxlength="10000"
        />
      </div>
    </form>

    <template #footer>
      <div class="flex items-center justify-end gap-2 px-5 py-4">
        <button
          type="button"
          class="btn-ghost h-9 px-4 text-sm"
          :disabled="form.processing"
          @click="emit('close')"
        >
          Hủy
        </button>
        <button
          type="button"
          class="btn-primary h-9 gap-1.5 px-4 text-sm"
          :disabled="form.processing"
          @click="submit"
        >
          <AppIcon
            name="save"
            :size="14"
          />
          {{ isEditing ? 'Lưu thay đổi' : 'Thêm test case' }}
        </button>
      </div>
    </template>
  </Modal>
</template>
