<script setup>
import { computed, reactive, watch } from 'vue';
import Modal from '@/Components/Ui/Modal.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    /** 'create' | 'edit' */
    mode: { type: String, default: 'create' },
    courses: { type: Array, default: () => [] },
    /** Prefill for create: { date, start_time, end_time, course_id }. */
    prefill: { type: Object, default: () => ({}) },
    /** Existing session for edit. */
    session: { type: Object, default: null },
    busy: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'submit']);

const blank = () => ({
    course_id: '',
    title: 'Buổi học',
    date: '',
    start_time: '',
    end_time: '',
    topic: '',
});

const form = reactive(blank());
const errors = reactive({});
let snapshot = JSON.stringify(form);

const isEdit = computed(() => props.mode === 'edit');
const title = computed(() => (isEdit.value ? 'Sửa buổi học' : 'Thêm buổi học'));

watch(
    () => props.show,
    (open) => {
        if (!open) return;
        Object.keys(errors).forEach((k) => delete errors[k]);
        if (isEdit.value && props.session) {
            const s = props.session;
            Object.assign(form, {
                course_id: s.courseId || '',
                title: s.title || 'Buổi học',
                date: s.date || '',
                start_time: s.startTime || '',
                end_time: s.endTime || '',
                topic: s.topic || '',
            });
        } else {
            Object.assign(form, blank(), {
                course_id: props.prefill.course_id || (props.courses.length === 1 ? props.courses[0].id : ''),
                date: props.prefill.date || '',
                start_time: props.prefill.start_time || '',
                end_time: props.prefill.end_time || '',
            });
        }
        snapshot = JSON.stringify(form);
    },
    { immediate: true },
);

const dirty = computed(() => JSON.stringify(form) !== snapshot);

function validate() {
    Object.keys(errors).forEach((k) => delete errors[k]);
    if (!isEdit.value && !form.course_id) errors.course_id = 'Vui lòng chọn khóa.';
    if (!form.title.trim()) errors.title = 'Vui lòng nhập tên buổi học.';
    if (!form.date) errors.date = 'Vui lòng chọn ngày.';
    if (form.start_time && form.end_time && form.end_time <= form.start_time) {
        errors.end_time = 'Giờ kết thúc phải sau giờ bắt đầu.';
    }
    return Object.keys(errors).length === 0;
}

function submit() {
    if (!validate()) return;
    const payload = {
        title: form.title.trim(),
        date: form.date,
        start_time: form.start_time || null,
        end_time: form.end_time || null,
        topic: form.topic || null,
    };
    if (isEdit.value) {
        emit('submit', { mode: 'edit', id: props.session.id, payload });
    } else {
        emit('submit', { mode: 'create', payload: { ...payload, course_id: form.course_id } });
    }
}

const courseLabel = computed(() => {
    if (!isEdit.value || !props.session) return '';
    return [props.session.courseCode, props.session.courseName].filter(Boolean).join(' · ');
});
</script>

<template>
  <Modal
    :show="show"
    :title="title"
    max-width="max-w-lg"
    :dirty="dirty"
    @close="emit('close')"
  >
    <form
      class="space-y-4"
      @submit.prevent="submit"
    >
      <!-- Course -->
      <div v-if="!isEdit">
        <label class="mb-1 block text-sm font-medium text-slate-700">Khóa coaching</label>
        <select
          v-model="form.course_id"
          class="input w-full"
          :class="{ 'border-rose-300': errors.course_id }"
        >
          <option
            value=""
            disabled
          >
            Chọn khóa…
          </option>
          <option
            v-for="c in courses"
            :key="c.id"
            :value="c.id"
          >
            {{ c.code ? `${c.code} · ` : '' }}{{ c.name }}
          </option>
        </select>
        <p
          v-if="errors.course_id"
          class="mt-1 text-xs text-rose-600"
        >
          {{ errors.course_id }}
        </p>
      </div>
      <div
        v-else
        class="rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-600"
      >
        {{ courseLabel || 'Khóa coaching' }}
      </div>

      <!-- Title -->
      <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Tên buổi học</label>
        <input
          v-model="form.title"
          type="text"
          class="input w-full"
          :class="{ 'border-rose-300': errors.title }"
          placeholder="Buổi học"
        >
        <p
          v-if="errors.title"
          class="mt-1 text-xs text-rose-600"
        >
          {{ errors.title }}
        </p>
      </div>

      <!-- Date + times -->
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div>
          <label class="mb-1 block text-sm font-medium text-slate-700">Ngày</label>
          <input
            v-model="form.date"
            type="date"
            class="input w-full"
            :class="{ 'border-rose-300': errors.date }"
          >
          <p
            v-if="errors.date"
            class="mt-1 text-xs text-rose-600"
          >
            {{ errors.date }}
          </p>
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-slate-700">Bắt đầu</label>
          <input
            v-model="form.start_time"
            type="time"
            class="input w-full"
          >
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-slate-700">Kết thúc</label>
          <input
            v-model="form.end_time"
            type="time"
            class="input w-full"
            :class="{ 'border-rose-300': errors.end_time }"
          >
          <p
            v-if="errors.end_time"
            class="mt-1 text-xs text-rose-600"
          >
            {{ errors.end_time }}
          </p>
        </div>
      </div>

      <!-- Topic -->
      <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Chủ đề (tuỳ chọn)</label>
        <input
          v-model="form.topic"
          type="text"
          class="input w-full"
          placeholder="Nội dung chính của buổi học"
        >
      </div>

      <div class="flex justify-end gap-2 pt-2">
        <button
          type="button"
          class="btn-ghost h-9 px-4 text-sm"
          @click="emit('close')"
        >
          Hủy
        </button>
        <button
          type="submit"
          class="btn-primary h-9 px-4 text-sm"
          :disabled="busy"
        >
          {{ isEdit ? 'Lưu' : 'Thêm buổi' }}
        </button>
      </div>
    </form>
  </Modal>
</template>
