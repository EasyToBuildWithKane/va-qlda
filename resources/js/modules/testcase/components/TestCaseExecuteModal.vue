<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Modal from '@/Components/Ui/Modal.vue';
import { useToast } from '@/shared/composables/useToast';

const props = defineProps({
    show: { type: Boolean, default: false },
    testCase: { type: Object, default: null },
    runResultOptions: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'saved']);
const toast = useToast();

const form = useForm({
    result: 'pass',
    actual_result: '',
    note: '',
    create_blocker: false,
    blocker_title: '',
});

watch(() => props.show, (v) => {
    if (v) {
        form.result = 'pass';
        form.actual_result = '';
        form.note = '';
        form.create_blocker = false;
        form.blocker_title = '';
        form.clearErrors();
    }
});

const showBlockerField = computed(() =>
    form.create_blocker && form.result === 'fail',
);

const resultColorMap = {
    pass: { bg: 'bg-emerald-50 border-emerald-200', text: 'text-emerald-700', check: 'text-emerald-500' },
    fail: { bg: 'bg-rose-50 border-rose-200', text: 'text-rose-700', check: 'text-rose-500' },
    blocked: { bg: 'bg-violet-50 border-violet-200', text: 'text-violet-700', check: 'text-violet-500' },
    skipped: { bg: 'bg-slate-50 border-slate-200', text: 'text-slate-600', check: 'text-slate-400' },
};

const RESULT_OPTIONS = [
    { value: 'pass', label: 'Đạt', icon: 'done', desc: 'Test case thực thi thành công' },
    { value: 'fail', label: 'Không đạt', icon: 'alert', desc: 'Test case không đáp ứng yêu cầu' },
    { value: 'blocked', label: 'Bị chặn', icon: 'blockers', desc: 'Không thể chạy do vướng mắc' },
    { value: 'skipped', label: 'Bỏ qua', icon: 'minus-circle', desc: 'Bỏ qua trong lần chạy này' },
];

function submit() {
    if (!props.testCase?.id) return;

    form.post(route('test-cases.execute', { testCase: props.testCase.id }), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Đã ghi nhận kết quả thực thi.');
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
    :title="testCase ? `Thực thi: ${testCase.title}` : 'Thực thi test case'"
    max-width="xl"
    @close="emit('close')"
  >
    <template #header-icon>
      <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-violet-100 text-violet-600">
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
      <!-- Test case info -->
      <div
        v-if="testCase"
        class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3"
      >
        <p class="text-xs font-semibold text-slate-500">
          {{ testCase.code }}
          <span
            v-if="testCase.suite?.name"
            class="ml-1 text-slate-400"
          >· {{ testCase.suite.name }}</span>
        </p>
        <p class="mt-0.5 text-sm font-medium text-slate-800">
          {{ testCase.title }}
        </p>
        <p
          v-if="testCase.expected_result"
          class="mt-1.5 text-xs text-slate-500"
        >
          <span class="font-semibold">Kết quả mong đợi:</span> {{ testCase.expected_result }}
        </p>
      </div>

      <!-- Steps summary -->
      <div
        v-if="testCase?.steps?.length"
        class="space-y-1.5"
      >
        <p class="text-xs font-semibold text-slate-500">
          Các bước kiểm thử ({{ testCase.steps.length }})
        </p>
        <ol class="space-y-1 pl-4 text-xs text-slate-600">
          <li
            v-for="(step, idx) in testCase.steps"
            :key="idx"
            class="list-decimal"
          >
            <span class="font-medium">{{ step.step }}</span>
            <span
              v-if="step.expected"
              class="ml-1 text-slate-400"
            > → {{ step.expected }}</span>
          </li>
        </ol>
      </div>

      <!-- Result selection -->
      <div>
        <label class="label">Kết quả thực thi <span class="text-rose-500">*</span></label>
        <div class="grid grid-cols-2 gap-2">
          <button
            v-for="opt in RESULT_OPTIONS"
            :key="opt.value"
            type="button"
            class="flex items-center gap-2.5 rounded-xl border px-3 py-2.5 text-left transition-colors"
            :class="form.result === opt.value
              ? [resultColorMap[opt.value].bg, resultColorMap[opt.value].text, 'ring-1 ring-inset ring-current/30']
              : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'"
            @click="form.result = opt.value"
          >
            <AppIcon
              :name="opt.icon"
              :size="16"
              :class="form.result === opt.value ? resultColorMap[opt.value].check : 'text-slate-400'"
            />
            <div>
              <p class="text-sm font-semibold">
                {{ opt.label }}
              </p>
              <p class="text-[11px] opacity-70">
                {{ opt.desc }}
              </p>
            </div>
          </button>
        </div>
        <p
          v-if="form.errors.result"
          class="mt-1 text-xs text-rose-600"
        >
          {{ form.errors.result }}
        </p>
      </div>

      <!-- Actual result -->
      <div>
        <label class="label">Kết quả thực tế</label>
        <textarea
          v-model="form.actual_result"
          class="input w-full resize-y"
          rows="3"
          placeholder="Mô tả kết quả quan sát được khi thực thi…"
          maxlength="10000"
        />
      </div>

      <!-- Note -->
      <div>
        <label class="label">Ghi chú</label>
        <textarea
          v-model="form.note"
          class="input w-full resize-y"
          rows="2"
          placeholder="Ghi chú thêm nếu cần…"
          maxlength="5000"
        />
      </div>

      <!-- Create blocker -->
      <div
        v-if="form.result === 'fail'"
        class="rounded-xl border border-rose-100 bg-rose-50 px-4 py-3"
      >
        <label class="flex cursor-pointer items-center gap-2">
          <input
            v-model="form.create_blocker"
            type="checkbox"
            class="h-4 w-4 rounded border-slate-300 text-brand"
          >
          <span class="text-sm font-medium text-rose-700">Tạo vướng mắc từ kết quả này</span>
        </label>

        <div
          v-if="showBlockerField"
          class="mt-3"
        >
          <label class="label text-rose-700">Tiêu đề vướng mắc <span class="text-rose-500">*</span></label>
          <input
            v-model="form.blocker_title"
            type="text"
            class="input w-full border-rose-200 focus:border-rose-400 focus:ring-rose-200"
            :placeholder="`[Fail] ${testCase?.title ?? 'Test case'}`"
            maxlength="255"
          >
          <p
            v-if="form.errors.blocker_title"
            class="mt-1 text-xs text-rose-600"
          >
            {{ form.errors.blocker_title }}
          </p>
        </div>
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
            name="check-circle"
            :size="14"
          />
          Ghi nhận kết quả
        </button>
      </div>
    </template>
  </Modal>
</template>
