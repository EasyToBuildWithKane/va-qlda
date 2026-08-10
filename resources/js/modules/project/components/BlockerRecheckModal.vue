<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Ui/Modal.vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    blocker: { type: Object, default: null },
});

const emit = defineEmits(['close']);

const form = useForm({
    result: 'passed',
    note: '',
});

watch(
    () => [props.show, props.blocker?.id],
    ([open]) => {
        if (open) {
            form.reset();
            form.clearErrors();
            form.result = 'passed';
        }
    },
);

const title = computed(() => {
    const code = props.blocker?.code;
    return code ? `Xác nhận xử lý ${code}` : 'Xác nhận xử lý';
});

const requiresNote = computed(() => form.result === 'failed');

function close() {
    if (form.processing) return;
    emit('close');
}

function submit() {
    if (!props.blocker?.id) return;
    form.post(`/blockers/${props.blocker.id}/recheck`, {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
}
</script>

<template>
  <Modal
    :show="show && !!blocker"
    :title="title"
    max-width="max-w-lg"
    @close="close"
  >
    <template v-if="blocker">
      <div class="rounded-lg border border-slate-200 bg-slate-50/90 px-3 py-2.5">
        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
          Test case
        </p>
        <p class="mt-0.5 text-sm font-semibold text-slate-900">
          {{ blocker.title }}
        </p>
      </div>

      <div
        v-if="blocker.resolution"
        class="mt-3 rounded-lg border border-emerald-200/80 bg-emerald-50/40 px-3 py-2.5 text-sm text-slate-700"
      >
        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
          Hướng xử lý
        </p>
        <p class="mt-1 whitespace-pre-wrap leading-relaxed">
          {{ blocker.resolution }}
        </p>
      </div>

      <fieldset class="mt-4 space-y-2">
        <legend class="text-xs font-medium text-slate-500">
          Kết quả xác nhận
        </legend>
        <label class="flex cursor-pointer items-start gap-2 rounded-lg border border-emerald-200/80 bg-emerald-50/50 px-3 py-2.5 has-[:checked]:ring-2 has-[:checked]:ring-emerald-300/80">
          <input
            v-model="form.result"
            type="radio"
            class="mt-0.5"
            value="passed"
            name="recheck-result"
          >
          <span>
            <span class="block text-sm font-medium text-emerald-900">Đạt — đóng test case</span>
            <span class="block text-xs text-emerald-800/80">Xử lý đúng yêu cầu, chuyển sang đã đóng.</span>
          </span>
        </label>
        <label class="flex cursor-pointer items-start gap-2 rounded-lg border border-rose-200/80 bg-rose-50/40 px-3 py-2.5 has-[:checked]:ring-2 has-[:checked]:ring-rose-300/80">
          <input
            v-model="form.result"
            type="radio"
            class="mt-0.5"
            value="failed"
            name="recheck-result"
          >
          <span>
            <span class="block text-sm font-medium text-rose-900">Không đạt — trả lại xử lý</span>
            <span class="block text-xs text-rose-800/80">Trạng thái tự chuyển sang đang xử lý và thông báo Telegram.</span>
          </span>
        </label>
      </fieldset>

      <div
        v-if="requiresNote"
        class="mt-4"
      >
        <label
          for="recheck-note"
          class="text-xs font-medium text-slate-500"
        >
          Lý do không đạt <span class="text-rose-600">*</span>
        </label>
        <textarea
          id="recheck-note"
          v-model="form.note"
          rows="4"
          class="input mt-1.5 w-full text-sm"
          placeholder="Mô tả cụ thể phần chưa đúng để người xử lý sửa…"
        />
        <p
          v-if="form.errors.note"
          class="mt-1 text-xs text-rose-600"
        >
          {{ form.errors.note }}
        </p>
      </div>
      <p
        v-else-if="form.errors.result"
        class="mt-3 text-xs text-rose-600"
      >
        {{ form.errors.result }}
      </p>

      <div class="mt-5 flex flex-wrap justify-end gap-2 border-t border-slate-100 pt-4">
        <button
          type="button"
          class="btn-secondary h-9 px-4 text-sm"
          :disabled="form.processing"
          @click="close"
        >
          Huỷ
        </button>
        <button
          type="button"
          class="btn-primary inline-flex h-9 items-center gap-1.5 px-4 text-sm"
          :disabled="form.processing"
          @click="submit"
        >
          <AppIcon
            name="done"
            :size="15"
          />
          Gửi kết quả
        </button>
      </div>
    </template>
  </Modal>
</template>
