<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import ProposalFormLabel from '@/modules/aiAccount/components/ProposalFormLabel.vue';
import MoneyInput from '@/shared/ui/MoneyInput.vue';
import DecimalHoursInput from '@/shared/ui/DecimalHoursInput.vue';
import {
    COACHING_FORM_HINTS as H,
    COACHING_FORM_PLACEHOLDERS as P,
} from '@/modules/coaching/config/coachingFormHints';

const props = defineProps({
    course: { type: Object, default: null },
    options: { type: Object, default: () => ({}) },
});

const isEdit = computed(() => Boolean(props.course?.id));

const form = useForm({
    name: props.course?.name ?? '',
    description: props.course?.description ?? '',
    objectives: props.course?.objectives ?? '',
    student_name: props.course?.student_name ?? props.course?.student_display ?? '',
    coach_name: props.course?.coach_name ?? props.course?.coach_display ?? '',
    status: props.course?.status?.value ?? 'planning',
    start_date: props.course?.start_date ?? '',
    end_date: props.course?.end_date ?? '',
    total_fee: props.course?.total_fee ?? null,
    hourly_rate: props.course?.hourly_rate ?? null,
    total_hours: props.course?.total_hours ?? null,
});

function submit() {
    if (isEdit.value) {
        form.put(`/coaching/courses/${props.course.id}`);
    } else {
        form.post('/coaching/courses');
    }
}
</script>

<template>
  <Head :title="isEdit ? 'Sửa khóa học' : 'Tạo khóa học'" />
  <AppLayout>
    <PageHeader
      :title="isEdit ? 'Sửa khóa học' : 'Tạo khóa học'"
      :subtitle="isEdit ? course?.code : 'Nhập thông tin khóa coaching / mentoring'"
    >
      <Link
        href="/coaching/courses"
        class="btn-ghost h-9 px-3 text-sm"
      >
        Danh sách khóa
      </Link>
    </PageHeader>

    <form
      class="mx-auto max-w-5xl space-y-8"
      @submit.prevent="submit"
    >
      <!-- Thông tin chung -->
      <section class="card overflow-hidden">
        <div class="border-b border-slate-100 bg-slate-50/80 px-6 py-4">
          <h2 class="font-display text-sm font-semibold text-slate-800">
            Thông tin khóa học
          </h2>
          <p class="mt-0.5 text-xs text-slate-500">
            Tên, mô tả và mục tiêu hiển thị trên trang chi tiết và báo cáo.
          </p>
        </div>
        <div class="space-y-5 p-6">
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
          <div>
            <ProposalFormLabel
              label="Mô tả"
              :tooltip="H.description"
            />
            <textarea
              v-model="form.description"
              class="input w-full"
              rows="4"
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
              rows="4"
              :placeholder="P.objectives"
            />
          </div>
        </div>
      </section>

      <!-- Người tham gia -->
      <section class="card overflow-hidden">
        <div class="border-b border-slate-100 bg-slate-50/80 px-6 py-4">
          <h2 class="font-display text-sm font-semibold text-slate-800">
            Học viên & Coach
          </h2>
          <p class="mt-0.5 text-xs text-slate-500">
            Nhập tên trực tiếp — không cần chọn từ danh sách nhân sự.
          </p>
        </div>
        <div class="grid gap-5 p-6 sm:grid-cols-2">
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
      </section>

      <!-- Thời gian & tài chính -->
      <section class="card overflow-hidden">
        <div class="border-b border-slate-100 bg-slate-50/80 px-6 py-4">
          <h2 class="font-display text-sm font-semibold text-slate-800">
            Thời gian & học phí dự kiến
          </h2>
          <p class="mt-0.5 text-xs text-slate-500">
            Số tiền và giờ được format theo chuẩn Việt Nam khi nhập.
          </p>
        </div>
        <div class="space-y-5 p-6">
          <div class="grid gap-5 sm:grid-cols-2">
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

          <div class="grid gap-6 lg:grid-cols-3">
            <div class="rounded-card border border-brand/15 bg-brand/5 p-4 lg:col-span-1">
              <ProposalFormLabel
                label="Học phí dự kiến (VNĐ)"
                :tooltip="H.total_fee"
              />
              <MoneyInput
                v-model="form.total_fee"
                :placeholder="P.total_fee"
                words-class="mt-1.5 text-xs font-medium italic text-brand"
              />
            </div>
            <div>
              <ProposalFormLabel
                label="Đơn giá / giờ (VNĐ)"
                :tooltip="H.hourly_rate"
              />
              <MoneyInput
                v-model="form.hourly_rate"
                :placeholder="P.hourly_rate"
                words-class="mt-1.5 text-xs font-medium italic text-brand"
              />
            </div>
            <div>
              <ProposalFormLabel
                label="Tổng giờ dự kiến"
                :tooltip="H.total_hours"
              />
              <DecimalHoursInput
                v-model="form.total_hours"
                :placeholder="P.total_hours"
              />
            </div>
          </div>
        </div>
      </section>

      <!-- Trạng thái -->
      <section class="card p-6">
        <ProposalFormLabel
          label="Trạng thái khóa"
          :tooltip="H.status"
        />
        <select
          v-model="form.status"
          class="input max-w-md w-full"
        >
          <option
            v-for="s in options.statuses"
            :key="s.value"
            :value="s.value"
          >
            {{ s.label }}
          </option>
        </select>
      </section>

      <div class="flex flex-wrap items-center gap-3 pb-8">
        <button
          type="submit"
          class="btn-primary h-10 px-6"
          :disabled="form.processing"
        >
          {{ isEdit ? 'Cập nhật khóa học' : 'Tạo khóa học' }}
        </button>
        <Link
          href="/coaching/courses"
          class="btn-ghost h-10 px-4 text-sm"
        >
          Huỷ
        </Link>
      </div>
    </form>
  </AppLayout>
</template>
