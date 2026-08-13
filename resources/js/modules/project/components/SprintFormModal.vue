<script setup>
import { computed, inject, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Modal from '@/Components/Ui/Modal.vue';
import { useModalFormDraft } from '@/composables/useModalFormDraft';
import { buildDraftSaveMeta, restoreModalDraft } from '@/composables/useModalDraftHelpers';
import { useToast } from '@/shared/composables/useToast';

const props = defineProps({
    show: { type: Boolean, default: false },
    projectId: { type: Number, required: true },
    sprint: { type: Object, default: null },
    statusOptions: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'saved']);
const modalClose = inject('modalClose', () => emit('close'));
const toast = useToast();

function sprintStatusValue(raw) {
    if (raw && typeof raw === 'object' && raw.value != null) return raw.value;
    if (typeof raw === 'string' && raw.trim() !== '') return raw.trim();
    return 'planned';
}

const form = useForm({
    name: '',
    goal: '',
    status: 'planned',
    start_date: null,
    end_date: null,
});

const sprintDraftScope = computed(() => (
    props.sprint ? `edit.${props.sprint.id}` : `create.${props.projectId}`
));

const formDraft = useModalFormDraft('sprint', {
    getScope: () => sprintDraftScope.value,
    fields: ['name', 'goal', 'status', 'start_date', 'end_date'],
});

const applyFormDraft = (data) => {
    form.name = data.name ?? '';
    form.goal = data.goal ?? '';
    form.status = sprintStatusValue(data.status);
    form.start_date = data.start_date || null;
    form.end_date = data.end_date || null;
};

const saveDraftOnClose = () => {
    formDraft.saveOnClose(form.data(), buildDraftSaveMeta(props.sprint));
};

const modalTitle = computed(() => (props.sprint ? 'Chỉnh sửa sprint' : 'Thêm sprint mới'));

const durationDays = computed(() => {
    if (!form.start_date || !form.end_date) return null;
    const start = new Date(form.start_date);
    const end = new Date(form.end_date);
    if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime()) || end < start) return null;
    const diff = Math.round((end - start) / (1000 * 60 * 60 * 24)) + 1;
    return diff > 0 ? diff : null;
});

const statusPillActive = {
    slate: 'border-slate-400 bg-slate-100 text-slate-800 ring-2 ring-slate-400/40 dark:border-slate-500 dark:bg-slate-800 dark:text-slate-100',
    sky: 'border-sky-400 bg-sky-50 text-sky-800 ring-2 ring-sky-400/40 dark:border-sky-500 dark:bg-sky-950/50 dark:text-sky-200',
    emerald: 'border-emerald-400 bg-emerald-50 text-emerald-800 ring-2 ring-emerald-400/40 dark:border-emerald-500 dark:bg-emerald-950/50 dark:text-emerald-200',
};

const statusPillIdle = 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800';

watch(() => props.show, async (open) => {
    if (!open) return;
    form.clearErrors();
    const epoch = formDraft.bumpOpenEpoch();
    if (props.sprint) {
        form.name = props.sprint.name;
        form.goal = props.sprint.goal ?? '';
        form.status = sprintStatusValue(props.sprint.status);
        form.start_date = props.sprint.start_date;
        form.end_date = props.sprint.end_date;
        await restoreModalDraft(formDraft, {
            isActive: () => props.show,
            openEpoch: epoch,
            entity: props.sprint,
            applyDraft: applyFormDraft,
            form,
        });
    } else {
        form.reset();
        form.status = 'planned';
        await restoreModalDraft(formDraft, {
            isActive: () => props.show,
            openEpoch: epoch,
            entity: null,
            applyDraft: applyFormDraft,
            form,
        });
    }
});

const firstFormError = (errors) => {
    const values = Object.values(errors ?? {});
    const first = values[0];
    return Array.isArray(first) ? first[0] : first;
};

const submit = () => {
    if (!form.name.trim()) {
        toast.error('Vui lòng nhập tên sprint.');
        return;
    }
    if (!props.sprint && (!form.start_date || !form.end_date)) {
        toast.error('Vui lòng chọn ngày bắt đầu và ngày kết thúc.');
        return;
    }

    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            formDraft.clear();
            emit('saved');
            emit('close');
        },
        onError: (errors) => {
            toast.error(firstFormError(errors) || 'Không thể lưu sprint.');
        },
    };

    form.transform((data) => ({
        ...data,
        name: data.name.trim(),
        goal: data.goal?.trim() || null,
        status: sprintStatusValue(data.status),
        start_date: data.start_date || null,
        end_date: data.end_date || null,
    }));

    if (props.sprint) form.put(`/projects/${props.projectId}/sprints/${props.sprint.id}`, opts);
    else form.post(`/projects/${props.projectId}/sprints`, opts);
};
</script>

<template>
  <Modal
    :show="show"
    :dirty="form.isDirty"
    :title="modalTitle"
    max-width="max-w-2xl"
    :on-save-draft="saveDraftOnClose"
    @close="emit('close')"
  >
    <form
      class="space-y-5"
      @submit.prevent="submit"
    >
      <div class="space-y-4">
        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
          Thông tin sprint
        </p>

        <div>
          <label class="label">
            Tên sprint <span class="text-danger">*</span>
          </label>
          <input
            v-model="form.name"
            type="text"
            class="input"
            placeholder="VD: Sprint 2 — Tính năng"
            required
            autofocus
          >
          <p
            v-if="form.errors.name"
            class="mt-1 text-xs text-danger"
          >
            {{ form.errors.name }}
          </p>
        </div>

        <div>
          <label class="label">Mục tiêu sprint</label>
          <textarea
            v-model="form.goal"
            rows="2"
            class="input resize-none"
            placeholder="VD: Trang chủ & thông báo"
          />
        </div>
      </div>

      <div class="space-y-4 rounded-xl border border-slate-200/80 bg-slate-50/50 p-4 dark:border-slate-700 dark:bg-slate-900/40">
        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
          Thời gian & trạng thái
        </p>

        <div>
          <label class="label mb-2">
            Trạng thái <span class="text-danger">*</span>
          </label>
          <div class="flex flex-wrap gap-2">
            <button
              v-for="o in statusOptions"
              :key="o.value"
              type="button"
              class="rounded-lg border px-3 py-2 text-sm font-medium transition"
              :class="form.status === o.value
                ? (statusPillActive[o.color] || statusPillActive.slate)
                : statusPillIdle"
              @click="form.status = o.value"
            >
              {{ o.label }}
            </button>
          </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-[1fr_auto_1fr] sm:items-end">
          <div>
            <label class="label">Ngày bắt đầu <span class="text-danger">*</span></label>
            <input
              v-model="form.start_date"
              type="date"
              class="input"
              :required="!sprint"
            >
            <p
              v-if="form.errors.start_date"
              class="mt-1 text-xs text-danger"
            >
              {{ form.errors.start_date }}
            </p>
          </div>
          <span
            class="hidden pb-2.5 text-slate-300 sm:block"
            aria-hidden="true"
          >→</span>
          <div>
            <label class="label">Ngày kết thúc <span class="text-danger">*</span></label>
            <input
              v-model="form.end_date"
              type="date"
              class="input"
              :required="!sprint"
            >
            <p
              v-if="form.errors.end_date"
              class="mt-1 text-xs text-danger"
            >
              {{ form.errors.end_date }}
            </p>
          </div>
        </div>

        <p
          v-if="durationDays"
          class="flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-400"
        >
          <AppIcon
            name="calendar"
            :size="14"
            class="shrink-0 text-slate-400"
          />
          Khoảng {{ durationDays }} ngày làm việc (tính cả hai đầu mốc).
        </p>
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
          {{ sprint ? 'Lưu thay đổi' : 'Tạo sprint' }}
        </button>
      </div>
    </form>
  </Modal>
</template>
