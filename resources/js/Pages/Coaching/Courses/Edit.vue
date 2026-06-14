<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';

const props = defineProps({
    course: { type: Object, default: null },
    employees: { type: Array, default: () => [] },
    options: { type: Object, default: () => ({}) },
});

const isEdit = computed(() => Boolean(props.course?.id));

const form = useForm({
    name: props.course?.name ?? '',
    description: props.course?.description ?? '',
    objectives: props.course?.objectives ?? '',
    student_id: props.course?.student_id ?? '',
    coach_id: props.course?.coach_id ?? '',
    status: props.course?.status?.value ?? 'planning',
    start_date: props.course?.start_date ?? '',
    end_date: props.course?.end_date ?? '',
    total_fee: props.course?.total_fee ?? '',
    hourly_rate: props.course?.hourly_rate ?? '',
    total_hours: props.course?.total_hours ?? '',
});

function submit() {
    const payload = { ...form.data() };
    if (!payload.student_id) payload.student_id = null;
    if (!payload.coach_id) payload.coach_id = null;

    if (isEdit.value) {
        form.transform(() => payload).put(`/coaching/courses/${props.course.id}`);
    } else {
        form.transform(() => payload).post('/coaching/courses');
    }
}
</script>

<template>
  <Head :title="isEdit ? 'Sửa khóa học' : 'Tạo khóa học'" />
  <AppLayout>
    <PageHeader :title="isEdit ? 'Sửa khóa học' : 'Tạo khóa học'" />

    <form
      class="card mx-auto max-w-2xl space-y-4 p-5"
      @submit.prevent="submit"
    >
      <div>
        <label class="label">Tên khóa học</label>
        <input
          v-model="form.name"
          class="input w-full"
          required
        >
      </div>
      <div>
        <label class="label">Mô tả</label>
        <textarea
          v-model="form.description"
          class="input w-full"
          rows="3"
        />
      </div>
      <div>
        <label class="label">Mục tiêu</label>
        <textarea
          v-model="form.objectives"
          class="input w-full"
          rows="2"
        />
      </div>
      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label class="label">Học viên</label>
          <select
            v-model="form.student_id"
            class="input w-full"
          >
            <option value="">
              — Chọn —
            </option>
            <option
              v-for="e in employees"
              :key="e.id"
              :value="e.id"
            >
              {{ e.full_name }}
            </option>
          </select>
        </div>
        <div>
          <label class="label">Coach</label>
          <select
            v-model="form.coach_id"
            class="input w-full"
          >
            <option value="">
              — Chọn —
            </option>
            <option
              v-for="e in employees"
              :key="e.id"
              :value="e.id"
            >
              {{ e.full_name }}
            </option>
          </select>
        </div>
      </div>
      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label class="label">Ngày bắt đầu</label>
          <input
            v-model="form.start_date"
            type="date"
            class="input w-full"
          >
        </div>
        <div>
          <label class="label">Ngày kết thúc</label>
          <input
            v-model="form.end_date"
            type="date"
            class="input w-full"
          >
        </div>
      </div>
      <div class="grid gap-4 sm:grid-cols-3">
        <div>
          <label class="label">Học phí (VNĐ)</label>
          <input
            v-model="form.total_fee"
            type="number"
            class="input w-full"
            min="0"
          >
        </div>
        <div>
          <label class="label">Giá / giờ</label>
          <input
            v-model="form.hourly_rate"
            type="number"
            class="input w-full"
            min="0"
          >
        </div>
        <div>
          <label class="label">Tổng giờ KH</label>
          <input
            v-model="form.total_hours"
            type="number"
            class="input w-full"
            min="0"
            step="0.5"
          >
        </div>
      </div>
      <div>
        <label class="label">Trạng thái</label>
        <select
          v-model="form.status"
          class="input w-full"
        >
          <option
            v-for="s in options.statuses"
            :key="s.value"
            :value="s.value"
          >
            {{ s.label }}
          </option>
        </select>
      </div>
      <button
        type="submit"
        class="btn-primary"
        :disabled="form.processing"
      >
        Lưu
      </button>
    </form>
  </AppLayout>
</template>
