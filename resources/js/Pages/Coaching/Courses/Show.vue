<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import CoachingWorkspace from '@/modules/coaching/components/CoachingWorkspace.vue';
import {
    displayCourseCoach,
    displayCourseFee,
    displayCourseHourlyRate,
    displayCourseSingleDate,
    displayCourseStudent,
    displayCourseTotalHours,
} from '@/composables/coachingCourseDisplay';

const props = defineProps({
    course: { type: Object, required: true },
});

const courseStatusColor = computed(() => {
    const v = props.course.status?.value;
    if (v === 'active') return 'emerald';
    if (v === 'completed') return 'sky';
    if (v === 'cancelled') return 'rose';
    return 'slate';
});

const sessionsCount = computed(() => props.course.sessions_count ?? 0);

const sessionsListHref = computed(() => route('coaching.sessions.index', { course: props.course.id }));
const sessionsScheduleHref = computed(() => route('coaching.sessions.schedule'));
</script>

<template>
  <Head :title="course.name" />
  <AppLayout>
    <template #header>
      <PageHeader
        :title="course.name"
        :subtitle="course.code"
        icon="knowledge"
        back-href="/coaching/courses"
      >
        <Link
          v-if="course.can?.update"
          :href="route('coaching.courses.edit', { course: course.id })"
          class="btn-ghost h-9 px-3 text-sm"
        >
          Sửa khóa
        </Link>
      </PageHeader>
    </template>

    <CoachingWorkspace>
      <!-- KPI -->
      <div class="mb-6 grid grid-cols-2 gap-3 xl:grid-cols-4">
        <div class="card border-slate-100 p-4">
          <p class="text-xs font-medium uppercase tracking-wide text-slate-500">
            Trạng thái
          </p>
          <div class="mt-2">
            <Badge
              v-if="course.status"
              :label="course.status.label"
              :color="courseStatusColor"
            />
          </div>
        </div>
        <div class="card border-slate-100 p-4">
          <p class="text-xs font-medium uppercase tracking-wide text-slate-500">
            Tiến độ hoàn thành
          </p>
          <p class="mt-1 font-display text-2xl font-semibold text-brand">
            {{ course.progress_percent ?? 0 }}%
          </p>
          <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
            <div
              class="h-full rounded-full bg-brand transition-all"
              :style="{ width: `${course.progress_percent ?? 0}%` }"
            />
          </div>
        </div>
        <Link
          :href="sessionsListHref"
          class="card group border-slate-100 p-4 transition hover:border-brand/20 hover:shadow-sm"
        >
          <p class="text-xs font-medium uppercase tracking-wide text-slate-500">
            Số buổi học
          </p>
          <p class="mt-1 font-display text-2xl font-semibold text-slate-800 group-hover:text-brand">
            {{ sessionsCount }}
          </p>
          <p class="mt-2 text-xs font-medium text-brand opacity-80 group-hover:opacity-100">
            Xem danh sách →
          </p>
        </Link>
        <div class="card border-slate-100 p-4">
          <p class="text-xs font-medium uppercase tracking-wide text-slate-500">
            Học phí dự kiến
          </p>
          <p class="mt-1 font-display text-lg font-semibold text-slate-800">
            {{ displayCourseFee(course.total_fee) }}
          </p>
        </div>
      </div>

      <div class="grid gap-5 xl:grid-cols-3">
        <!-- Mô tả & mục tiêu -->
        <div class="space-y-5 xl:col-span-2">
          <section class="card overflow-hidden">
            <div class="border-b border-slate-100 bg-gradient-to-r from-brand/[0.06] to-transparent px-5 py-3.5">
              <h2 class="font-display text-sm font-semibold text-slate-800">
                Mô tả khóa học
              </h2>
            </div>
            <div class="px-5 py-5">
              <p
                v-if="course.description"
                class="text-sm leading-relaxed text-slate-700 whitespace-pre-wrap"
              >
                {{ course.description }}
              </p>
              <p
                v-else
                class="text-sm italic text-slate-400"
              >
                Chưa có mô tả. Bạn có thể bổ sung khi sửa khóa học.
              </p>
            </div>
          </section>

          <section class="card overflow-hidden">
            <div class="border-b border-slate-100 bg-slate-50/80 px-5 py-3.5">
              <h2 class="font-display text-sm font-semibold text-slate-800">
                Mục tiêu
              </h2>
            </div>
            <div class="px-5 py-5">
              <pre
                v-if="course.objectives"
                class="whitespace-pre-wrap font-sans text-sm leading-relaxed text-slate-700"
              >{{ course.objectives }}</pre>
              <p
                v-else
                class="text-sm italic text-slate-400"
              >
                Chưa ghi mục tiêu.
              </p>
            </div>
          </section>

          <section class="card overflow-hidden">
            <div class="border-b border-slate-100 bg-slate-50/80 px-5 py-3.5">
              <h2 class="font-display text-sm font-semibold text-slate-800">
                Thời gian &amp; học phí
              </h2>
            </div>
            <dl class="grid gap-4 p-5 sm:grid-cols-2">
              <div>
                <dt class="text-xs font-medium text-slate-500">
                  Ngày bắt đầu
                </dt>
                <dd
                  class="mt-1 text-sm font-medium"
                  :class="course.start_date ? 'text-slate-800' : 'text-slate-500'"
                >
                  {{ displayCourseSingleDate(course.start_date) }}
                </dd>
              </div>
              <div>
                <dt class="text-xs font-medium text-slate-500">
                  Ngày kết thúc
                </dt>
                <dd
                  class="mt-1 text-sm font-medium"
                  :class="course.end_date ? 'text-slate-800' : 'text-slate-500'"
                >
                  {{ displayCourseSingleDate(course.end_date) }}
                </dd>
              </div>
              <div>
                <dt class="text-xs font-medium text-slate-500">
                  Đơn giá / giờ
                </dt>
                <dd
                  class="mt-1 text-sm font-medium"
                  :class="course.hourly_rate != null && course.hourly_rate !== '' ? 'text-slate-800' : 'text-slate-500'"
                >
                  {{ displayCourseHourlyRate(course.hourly_rate) }}
                </dd>
              </div>
              <div>
                <dt class="text-xs font-medium text-slate-500">
                  Tổng giờ dự kiến
                </dt>
                <dd
                  class="mt-1 text-sm font-medium"
                  :class="course.total_hours != null ? 'text-slate-800' : 'text-slate-500'"
                >
                  {{ displayCourseTotalHours(course.total_hours) }}
                </dd>
              </div>
            </dl>
          </section>
        </div>

        <!-- Cột phải -->
        <div class="space-y-5">
          <section class="card overflow-hidden">
            <div class="border-b border-slate-100 bg-slate-50/80 px-5 py-3.5">
              <h2 class="font-display text-sm font-semibold text-slate-800">
                Học viên &amp; Coach
              </h2>
            </div>
            <div class="space-y-4 p-5">
              <div class="rounded-lg bg-slate-50/80 px-4 py-3">
                <p class="text-xs font-medium text-slate-500">
                  Học viên
                </p>
                <p
                  class="mt-1 text-sm font-semibold"
                  :class="course.student_display ? 'text-slate-800' : 'text-slate-500'"
                >
                  {{ displayCourseStudent(course.student_display) }}
                </p>
              </div>
              <div class="rounded-lg bg-slate-50/80 px-4 py-3">
                <p class="text-xs font-medium text-slate-500">
                  Coach / Mentor
                </p>
                <p
                  class="mt-1 text-sm font-semibold"
                  :class="course.coach_display ? 'text-slate-800' : 'text-slate-500'"
                >
                  {{ displayCourseCoach(course.coach_display) }}
                </p>
              </div>
            </div>
          </section>

          <section class="card overflow-hidden border-brand/10 bg-brand/[0.02]">
            <div class="border-b border-brand/10 px-5 py-3.5">
              <h2 class="font-display text-sm font-semibold text-slate-800">
                Buổi học
              </h2>
              <p class="mt-0.5 text-xs text-slate-500">
                Lịch và danh sách đã chuyển sang menu Coaching.
              </p>
            </div>
            <div class="flex flex-col gap-2 p-5">
              <Link
                :href="sessionsScheduleHref"
                class="inline-flex h-10 items-center gap-2 rounded-btn border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700 transition hover:border-brand/30 hover:text-brand"
              >
                <AppIcon
                  name="calendar"
                  :size="16"
                />
                Lịch buổi học
              </Link>
              <Link
                :href="sessionsListHref"
                class="inline-flex h-10 items-center gap-2 rounded-btn border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700 transition hover:border-brand/30 hover:text-brand"
              >
                <AppIcon
                  name="weekly"
                  :size="16"
                />
                Danh sách buổi học
              </Link>
            </div>
          </section>
        </div>
      </div>
    </CoachingWorkspace>
  </AppLayout>
</template>
