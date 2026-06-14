<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Ui/Modal.vue';
import ProposalFormLabel from '@/modules/aiAccount/components/ProposalFormLabel.vue';
import MoneyInput from '@/shared/ui/MoneyInput.vue';
import DecimalHoursInput from '@/shared/ui/DecimalHoursInput.vue';
import { useModalFormDraft } from '@/composables/useModalFormDraft';
import { buildDraftSaveMeta, restoreModalDraft } from '@/composables/useModalDraftHelpers';
import {
    COACHING_FORM_HINTS as H,
    COACHING_FORM_PLACEHOLDERS as P,
} from '@/modules/coaching/config/coachingFormHints';

const props = defineProps({
    show: { type: Boolean, default: false },
    course: { type: Object, default: null },
    statuses: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'saved']);

const isEdit = computed(() => Boolean(props.course?.id));

const FORM_FIELDS = [
    'name', 'description', 'objectives', 'student_name', 'coach_name',
    'status', 'start_date', 'end_date', 'total_fee', 'hourly_rate', 'total_hours',
];

const form = useForm({
    name: '',
    description: '',
    objectives: '',
    student_name: '',
    coach_name: '',
    status: 'planning',
    start_date: '',
    end_date: '',
    total_fee: null,
    hourly_rate: null,
    total_hours: null,
});

const draftScope = computed(() => (
    isEdit.value ? `edit.${props.course.id}` : 'create'
));

const formDraft = useModalFormDraft('coaching-course', {
    getScope: () => draftScope.value,
    fields: FORM_FIELDS,
});

function courseToForm(c) {
    return {
        name: c?.name ?? '',
        description: c?.description ?? '',
        objectives: c?.objectives ?? '',
        student_name: c?.student_name ?? c?.student_display ?? '',
        coach_name: c?.coach_name ?? c?.coach_display ?? '',
        status: c?.status?.value ?? 'planning',
        start_date: c?.start_date ?? '',
        end_date: c?.end_date ?? '',
        total_fee: c?.total_fee ?? null,
        hourly_rate: c?.hourly_rate ?? null,
        total_hours: c?.total_hours ?? null,
    };
}

function applyFormDraft(data) {
    const base = courseToForm(props.course);
    FORM_FIELDS.forEach((key) => {
        form[key] = data[key] ?? base[key];
    });
}

function saveDraftOnClose() {
    formDraft.saveOnClose(form.data(), buildDraftSaveMeta(props.course));
}

watch(() => props.show, async (open) => {
    if (!open) return;
    form.clearErrors();
    const epoch = formDraft.bumpOpenEpoch();
    if (isEdit.value) {
        Object.assign(form, courseToForm(props.course));
        await restoreModalDraft(formDraft, {
            isActive: () => props.show,
            openEpoch: epoch,
            entity: props.course,
            applyDraft: applyFormDraft,
            form,
        });
    } else {
        form.reset();
        form.status = 'planning';
        await restoreModalDraft(formDraft, {
            isActive: () => props.show,
            openEpoch: epoch,
            entity: null,
            applyDraft: applyFormDraft,
            form,
        });
    }
});

function submit() {
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            formDraft.clear();
            emit('saved');
            emit('close');
        },
    };
    if (isEdit.value) {
        form.put(route('coaching.courses.update', { course: props.course.id }), opts);
    } else {
        form.post(route('coaching.courses.store'), opts);
    }
}
</script>

<template>
  <Modal
    :show="show"
    :dirty="form.isDirty"
    :title="isEdit ? 'Sửa khóa học' : 'Thêm khóa học mới'"
    max-width="max-w-2xl"
    :on-save-draft="saveDraftOnClose"
    close-confirm-title="Đóng form?"
    :close-confirm-message="isEdit ? 'Thay đổi chưa lưu sẽ bị mất.' : 'Thông tin khóa học chưa lưu sẽ bị mất.'"
    @close="emit('close')"
  >
    <form
      class="flex max-h-[min(70vh,36rem)] flex-col"
      @submit.prevent="submit"
    >
      <div class="min-h-0 flex-1 space-y-5 overflow-y-auto pr-1">
        <div>
          <ProposalFormLabel
            label="Tên khóa học"
            required
            :tooltip="H.name"
          />
          <input
            v-model="form.name"
            type="text"
            class="input w-full"
            :placeholder="P.name"
            required
          >
          <p
            v-if="form.errors.name"
            class="mt-1 text-xs text-danger"
          >
            {{ form.errors.name }}
          </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <ProposalFormLabel
              label="Học viên"
              :tooltip="H.student_name"
            />
            <input
              v-model="form.student_name"
              type="text"
              class="input w-full"
              :placeholder="P.student_name"
              autocomplete="name"
            >
          </div>
          <div>
            <ProposalFormLabel
              label="Coach / Mentor"
              :tooltip="H.coach_name"
            />
            <input
              v-model="form.coach_name"
              type="text"
              class="input w-full"
              :placeholder="P.coach_name"
              autocomplete="name"
            >
          </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <ProposalFormLabel
              label="Ngày bắt đầu"
              :tooltip="H.start_date"
            />
            <input
              v-model="form.start_date"
              type="date"
              class="input w-full"
            >
          </div>
          <div>
            <ProposalFormLabel
              label="Ngày kết thúc"
              :tooltip="H.end_date"
            />
            <input
              v-model="form.end_date"
              type="date"
              class="input w-full"
            >
          </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
          <div class="rounded-card border border-brand/15 bg-brand/5 p-3 sm:col-span-1">
            <ProposalFormLabel
              label="Học phí (VNĐ)"
              :tooltip="H.total_fee"
            />
            <MoneyInput
              v-model="form.total_fee"
              :placeholder="P.total_fee"
              words-class="mt-1 text-[10px] font-medium italic text-brand"
            />
          </div>
          <div>
            <ProposalFormLabel
              label="Đơn giá / giờ"
              :tooltip="H.hourly_rate"
            />
            <MoneyInput
              v-model="form.hourly_rate"
              :placeholder="P.hourly_rate"
              words-class="mt-1 text-[10px] font-medium italic text-brand"
            />
          </div>
          <div>
            <ProposalFormLabel
              label="Tổng giờ"
              :tooltip="H.total_hours"
            />
            <DecimalHoursInput
              v-model="form.total_hours"
              :placeholder="P.total_hours"
            />
          </div>
        </div>

        <div>
          <ProposalFormLabel
            label="Trạng thái"
            :tooltip="H.status"
          />
          <select
            v-model="form.status"
            class="input w-full"
          >
            <option
              v-for="s in statuses"
              :key="s.value"
              :value="s.value"
            >
              {{ s.label }}
            </option>
          </select>
        </div>

        <div>
          <ProposalFormLabel
            label="Mô tả"
            :tooltip="H.description"
          />
          <textarea
            v-model="form.description"
            class="input w-full"
            rows="2"
            :placeholder="P.description"
          />
        </div>

        <div>
          <ProposalFormLabel
            label="Mục tiêu"
            :tooltip="H.objectives"
          />
          <textarea
            v-model="form.objectives"
            class="input w-full font-mono text-sm"
            rows="2"
            :placeholder="P.objectives"
          />
        </div>
      </div>

      <div class="mt-4 flex shrink-0 justify-end gap-2 border-t border-slate-100 pt-4">
        <button
          type="button"
          class="btn-ghost h-9 px-4 text-sm"
          @click="emit('close')"
        >
          Huỷ
        </button>
        <button
          type="submit"
          class="btn-primary h-9 px-5 text-sm"
          :disabled="form.processing"
        >
          {{ isEdit ? 'Cập nhật' : 'Tạo khóa học' }}
        </button>
      </div>
    </form>
  </Modal>
</template>
