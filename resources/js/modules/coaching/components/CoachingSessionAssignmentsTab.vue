<script setup>
/* eslint-disable vue/no-v-html */
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Modal from '@/Components/Ui/Modal.vue';
import KbRichTextField from '@/Components/KnowledgeBase/KbRichTextField.vue';
import DateInput from '@/shared/ui/form/DateInput.vue';
import { useToast } from '@/shared/composables/useToast';
import { useDialog } from '@/composables/useDialog';
import { date as fmtDate } from '@/composables/useFormat';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    sessionId: { type: Number, required: true },
    assignments: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
    canComplete: { type: Boolean, default: false },
});

const toast = useToast();
const dialog = useDialog();

const addForm = useForm({ title: '', description: '', deadline: '' });
const showAddModal = ref(false);

const completingId = ref(null);
const completeNotes = ref('');
const completeProcessing = ref(false);
const completeError = ref('');

const doneCount = computed(() => props.assignments.filter((a) => a.status === 'done').length);
const totalCount = computed(() => props.assignments.length);
const progressPct = computed(() => (totalCount.value ? Math.round((doneCount.value / totalCount.value) * 100) : 0));

function isDone(a) {
    return a.status === 'done';
}

function canToggle() {
    return props.canComplete;
}

function startComplete(a) {
    if (!canToggle() || isDone(a)) return;
    completingId.value = a.id;
    completeNotes.value = '';
    completeError.value = '';
}

function cancelComplete() {
    completingId.value = null;
    completeNotes.value = '';
    completeError.value = '';
}

function submitComplete(a) {
    const notes = completeNotes.value.trim();
    if (!notes) {
        completeError.value = 'Vui lòng nhập nội dung hoàn thành.';
        return;
    }
    completeError.value = '';
    completeProcessing.value = true;
    router.patch(`/coaching/assignments/${a.id}`, { status: 'done', notes }, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Đã đánh dấu hoàn thành.');
            cancelComplete();
        },
        onError: (errors) => {
            const msg = errors?.notes || errors?.status || 'Không cập nhật được bài tập.';
            completeError.value = Array.isArray(msg) ? msg[0] : msg;
            toast.error(completeError.value);
        },
        onFinish: () => { completeProcessing.value = false; },
    });
}

function reopen(a) {
    if (!canToggle() || !isDone(a)) return;
    router.patch(`/coaching/assignments/${a.id}`, { status: 'todo' }, {
        preserveScroll: true,
        onSuccess: () => toast.success('Đã mở lại bài tập.'),
        onError: () => toast.error('Không đổi được trạng thái.'),
    });
}

function openAddModal() {
    addForm.clearErrors();
    showAddModal.value = true;
}

function closeAddModal() {
    showAddModal.value = false;
    addForm.reset();
    addForm.clearErrors();
}

function submitAdd() {
    addForm.post(`/coaching/sessions/${props.sessionId}/assignments`, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Đã giao bài tập.');
            closeAddModal();
        },
    });
}

const deletingId = ref(null);

async function removeAssignment(a) {
    if (!props.canManage || deletingId.value) return;
    if (!await dialog.confirm({
        title: 'Xóa bài tập',
        message: `Xóa «${a.title}»? Hành động không hoàn tác.`,
        tone: 'danger',
        confirmText: 'Xóa',
    })) return;

    deletingId.value = a.id;
    router.delete(route('coaching.assignments.destroy', { assignment: a.id }), {
        preserveScroll: true,
        onSuccess: () => toast.success('Đã xóa bài tập.'),
        onError: () => toast.error('Không xóa được bài tập.'),
        onFinish: () => { deletingId.value = null; },
    });
}
</script>

<template>
  <div
    class="flex w-full flex-1 flex-col space-y-4"
    v-bind="$attrs"
  >
    <div
      v-if="canManage"
      class="flex flex-wrap items-center justify-end gap-2"
    >
      <button
        type="button"
        class="btn-primary inline-flex h-9 items-center gap-1.5 px-4 text-sm"
        @click="openAddModal"
      >
        <AppIcon
          name="add"
          :size="15"
        />
        Giao bài tập
      </button>
    </div>

    <div
      v-if="totalCount"
      class="rounded-xl border border-slate-100 bg-white px-4 py-3 shadow-sm"
    >
      <div class="flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm font-semibold text-slate-800">
          Tiến độ bài tập
        </p>
        <p class="text-xs font-medium text-slate-500">
          {{ doneCount }}/{{ totalCount }} hoàn thành
        </p>
      </div>
      <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
        <div
          class="h-full rounded-full bg-brand transition-all duration-300"
          :style="{ width: `${progressPct}%` }"
        />
      </div>
    </div>

    <ul
      v-if="totalCount"
      class="space-y-2"
      role="list"
    >
      <li
        v-for="a in assignments"
        :key="a.id"
        class="overflow-hidden rounded-xl border border-slate-100 bg-white shadow-sm transition-colors"
        :class="isDone(a) ? 'border-emerald-100/80 bg-emerald-50/20' : ''"
      >
        <div class="flex gap-3 px-4 py-3">
          <button
            type="button"
            class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-md border transition-colors"
            :class="isDone(a)
              ? 'border-emerald-500 bg-emerald-500 text-white'
              : 'border-slate-300 bg-white text-transparent hover:border-brand hover:bg-brand/5'"
            :disabled="!canComplete || completeProcessing"
            :title="isDone(a) ? 'Mở lại' : 'Hoàn thành'"
            :aria-label="isDone(a) ? `Mở lại: ${a.title}` : `Hoàn thành: ${a.title}`"
            @click="isDone(a) ? reopen(a) : startComplete(a)"
          >
            <AppIcon
              v-if="isDone(a)"
              name="check"
              :size="14"
            />
          </button>

          <div class="min-w-0 flex-1">
            <div class="flex items-start justify-between gap-2">
              <p
                class="min-w-0 flex-1 text-sm font-semibold text-slate-800"
                :class="isDone(a) ? 'text-slate-500 line-through decoration-slate-400' : ''"
              >
                {{ a.title }}
              </p>
              <button
                v-if="canManage"
                type="button"
                class="inline-flex h-8 shrink-0 items-center gap-1 rounded-btn border border-slate-200 px-2 text-xs font-medium text-slate-500 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600"
                :disabled="deletingId === a.id || completeProcessing"
                title="Xóa bài tập"
                @click="removeAssignment(a)"
              >
                <AppIcon
                  name="trash"
                  :size="14"
                />
                <span class="hidden sm:inline">Xóa</span>
              </button>
            </div>
            <div
              v-if="a.description"
              class="rich-content prose prose-sm mt-1 max-w-none text-slate-600 prose-headings:text-sm prose-p:text-xs prose-p:leading-relaxed"
              v-html="a.description"
            />
            <p
              v-if="a.deadline"
              class="mt-1.5 flex items-center gap-1 text-xs text-slate-500"
            >
              <AppIcon
                name="calendar"
                :size="12"
              />
              Hạn {{ fmtDate(a.deadline) }}
            </p>
            <div
              v-if="isDone(a) && a.notes"
              class="mt-2 rounded-lg border border-emerald-100 bg-white/80 px-3 py-2"
            >
              <p class="text-[10px] font-semibold uppercase tracking-wide text-emerald-700">
                Nội dung hoàn thành
              </p>
              <p class="mt-1 whitespace-pre-wrap text-sm text-slate-700">
                {{ a.notes }}
              </p>
            </div>
          </div>
        </div>

        <div
          v-if="completingId === a.id"
          class="border-t border-brand/15 bg-brand/[0.04] px-4 py-3"
        >
          <label
            :for="`assignment-complete-${a.id}`"
            class="label"
          >
            Nội dung hoàn thành <span class="text-danger">*</span>
          </label>
          <textarea
            :id="`assignment-complete-${a.id}`"
            v-model="completeNotes"
            class="input mt-1 w-full text-sm"
            rows="4"
            placeholder="Mô tả kết quả, link repo, ghi chú đã làm…"
            :disabled="completeProcessing"
          />
          <p
            v-if="completeError"
            class="mt-1 text-xs text-danger"
          >
            {{ completeError }}
          </p>
          <div class="mt-3 flex flex-wrap justify-end gap-2">
            <button
              type="button"
              class="inline-flex h-9 items-center rounded-btn border border-slate-200 px-3 text-xs font-medium text-slate-600 hover:bg-slate-50"
              :disabled="completeProcessing"
              @click="cancelComplete"
            >
              Huỷ
            </button>
            <button
              type="button"
              class="btn-primary inline-flex h-9 items-center gap-1.5 px-4 text-xs"
              :disabled="completeProcessing"
              @click="submitComplete(a)"
            >
              <AppIcon
                name="check"
                :size="14"
              />
              Xác nhận hoàn thành
            </button>
          </div>
        </div>
      </li>
    </ul>

    <div
      v-else
      class="flex flex-col items-center justify-center rounded-lg border border-dashed border-slate-200 bg-slate-50/50 px-6 py-12 text-center"
    >
      <AppIcon
        name="task"
        :size="32"
        class="text-slate-300"
      />
      <p class="mt-3 text-sm font-medium text-slate-600">
        Chưa có bài tập
      </p>
      <p
        v-if="canManage"
        class="mt-1 text-xs text-slate-500"
      >
        Bấm «Giao bài tập» phía trên để giao cho học viên.
      </p>
      <p
        v-else-if="!canComplete"
        class="mt-1 text-xs text-slate-500"
      >
        Coach sẽ giao bài tập tại đây.
      </p>
    </div>

    <Modal
      :show="showAddModal"
      title="Giao bài tập mới"
      max-width="max-w-4xl"
      :dirty="addForm.isDirty"
      close-confirm-title="Huỷ giao bài tập?"
      close-confirm-message="Thông tin đã nhập sẽ bị bỏ."
      @close="closeAddModal"
    >
      <form
        class="space-y-5"
        @submit.prevent="submitAdd"
      >
        <div>
          <label class="label">Tiêu đề</label>
          <input
            v-model="addForm.title"
            class="input w-full text-sm"
            placeholder="VD: Làm CRUD User, đọc chương 3…"
            required
            autofocus
          >
          <p
            v-if="addForm.errors.title"
            class="mt-1 text-xs text-danger"
          >
            {{ addForm.errors.title }}
          </p>
        </div>
        <KbRichTextField
          v-model="addForm.description"
          label="Yêu cầu / mô tả"
          placeholder="Mô tả chi tiết: bước làm, tiêu chí nộp bài, link tham khảo…"
          hint="In đậm, gạch chân, danh sách, tiêu đề H2/H3, liên kết — giống soạn nội dung buổi học."
          editor-min-height-class="min-h-[280px]"
        />
        <div class="grid gap-4 border-t border-slate-100 pt-4 sm:grid-cols-[minmax(11rem,14rem)_1fr] sm:items-end">
          <div>
            <label class="label">Hạn nộp</label>
            <DateInput v-model="addForm.deadline" />
          </div>
          <div class="flex flex-wrap justify-end gap-2 sm:justify-end">
            <button
              type="button"
              class="inline-flex h-10 items-center rounded-btn border border-slate-200 px-4 text-sm font-medium text-slate-600 hover:bg-slate-50"
              :disabled="addForm.processing"
              @click="closeAddModal"
            >
              Huỷ
            </button>
            <button
              type="submit"
              class="btn-primary inline-flex h-10 items-center gap-1.5 px-5 text-sm"
              :disabled="addForm.processing"
            >
              <AppIcon
                name="add"
                :size="15"
              />
              Giao bài tập
            </button>
          </div>
        </div>
      </form>
    </Modal>

    <p
      v-if="!canManage && canComplete && totalCount"
      class="text-center text-xs text-slate-500"
    >
      Đánh dấu từng mục khi xong — cần điền nội dung hoàn thành.
    </p>
  </div>
</template>
