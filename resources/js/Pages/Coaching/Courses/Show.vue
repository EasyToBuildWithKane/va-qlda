<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import CoachingSessionFormModal from '@/modules/coaching/components/CoachingSessionFormModal.vue';
import { currency, date, hours as fmtHours } from '@/composables/useFormat';

const props = defineProps({
    course: { type: Object, required: true },
    sessionStatuses: { type: Array, default: () => [] },
});

const sessionModal = ref(false);

const sessions = computed(() => props.course.sessions ?? []);
const nextSessionNumber = computed(() => (sessions.value.length ?? 0) + 1);

const statusColor = computed(() => {
    const v = props.course.status?.value;
    if (v === 'active') return 'emerald';
    if (v === 'completed') return 'sky';
    if (v === 'cancelled') return 'rose';
    return 'slate';
});
</script>

<template>
  <Head :title="course.name" />
  <AppLayout>
    <PageHeader
      :title="course.name"
      :subtitle="course.code"
    >
      <Link
        href="/coaching/courses"
        class="btn-ghost h-9 gap-1.5 px-3 text-sm"
      >
        <AppIcon
          name="arrow-left"
          :size="14"
        />
        Danh sách
      </Link>
      <Link
        v-if="course.can?.update"
        :href="route('coaching.courses.edit', { course: course.id })"
        class="btn-ghost h-9 px-3 text-sm"
      >
        Sửa khóa
      </Link>
      <button
        v-if="course.can?.update"
        type="button"
        class="btn-primary h-9 gap-1.5 px-3 text-sm"
        @click="sessionModal = true"
      >
        <AppIcon
          name="add"
          :size="15"
        />
        Thêm buổi
      </button>
    </PageHeader>

    <!-- KPI -->
    <div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <div class="card p-4">
        <p class="text-xs font-medium text-slate-500">
          Trạng thái
        </p>
        <div class="mt-2">
          <Badge
            v-if="course.status"
            :label="course.status.label"
            :color="statusColor"
          />
        </div>
      </div>
      <div class="card p-4">
        <p class="text-xs font-medium text-slate-500">
          Tiến độ hoàn thành
        </p>
        <p class="mt-1 font-display text-2xl font-semibold text-brand">
          {{ course.progress_percent ?? 0 }}%
        </p>
        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100">
          <div
            class="h-full bg-brand transition-all"
            :style="{ width: `${course.progress_percent ?? 0}%` }"
          />
        </div>
      </div>
      <div class="card p-4">
        <p class="text-xs font-medium text-slate-500">
          Số buổi học
        </p>
        <p class="mt-1 font-display text-2xl font-semibold text-slate-800">
          {{ course.sessions_count ?? sessions.length }}
        </p>
      </div>
      <div class="card p-4">
        <p class="text-xs font-medium text-slate-500">
          Học phí dự kiến
        </p>
        <p class="mt-1 font-display text-lg font-semibold text-slate-800">
          {{ currency(course.total_fee) }}
        </p>
      </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
      <!-- Cột trái: thông tin -->
      <div class="space-y-6 lg:col-span-2">
        <section class="card overflow-hidden">
          <div class="border-b border-slate-100 bg-slate-50/80 px-6 py-4">
            <h2 class="font-display text-sm font-semibold text-slate-800">
              Thông tin khóa học
            </h2>
          </div>
          <dl class="grid gap-4 p-6 sm:grid-cols-2">
            <div class="sm:col-span-2">
              <dt class="text-xs font-medium text-slate-500">
                Mô tả
              </dt>
              <dd class="mt-1 whitespace-pre-wrap text-sm text-slate-700">
                {{ course.description || '—' }}
              </dd>
            </div>
            <div class="sm:col-span-2">
              <dt class="text-xs font-medium text-slate-500">
                Mục tiêu
              </dt>
              <dd class="mt-1 whitespace-pre-wrap font-mono text-sm text-slate-700">
                {{ course.objectives || '—' }}
              </dd>
            </div>
            <div>
              <dt class="text-xs font-medium text-slate-500">
                Ngày bắt đầu
              </dt>
              <dd class="mt-1 text-sm text-slate-800">
                {{ date(course.start_date) }}
              </dd>
            </div>
            <div>
              <dt class="text-xs font-medium text-slate-500">
                Ngày kết thúc
              </dt>
              <dd class="mt-1 text-sm text-slate-800">
                {{ date(course.end_date) }}
              </dd>
            </div>
            <div>
              <dt class="text-xs font-medium text-slate-500">
                Đơn giá / giờ
              </dt>
              <dd class="mt-1 text-sm text-slate-800">
                {{ currency(course.hourly_rate) }}
              </dd>
            </div>
            <div>
              <dt class="text-xs font-medium text-slate-500">
                Tổng giờ dự kiến
              </dt>
              <dd class="mt-1 text-sm text-slate-800">
                {{ course.total_hours != null ? fmtHours(course.total_hours) : '—' }}
              </dd>
            </div>
          </dl>
        </section>

        <section class="card overflow-hidden">
          <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 bg-slate-50/80 px-6 py-4">
            <div>
              <h2 class="font-display text-sm font-semibold text-slate-800">
                Lịch buổi học
              </h2>
              <p class="mt-0.5 text-xs text-slate-500">
                Bấm vào buổi để xem tài liệu, bài tập và ghi chú.
              </p>
            </div>
            <button
              v-if="course.can?.update"
              type="button"
              class="inline-flex h-8 items-center gap-1 rounded-btn border border-brand/25 bg-brand/5 px-2.5 text-xs font-medium text-brand hover:bg-brand/10"
              @click="sessionModal = true"
            >
              <AppIcon
                name="add"
                :size="14"
              />
              Thêm buổi
            </button>
          </div>

          <div
            v-if="!sessions.length"
            class="px-6 py-10 text-center text-sm text-slate-500"
          >
            Chưa có buổi học.
            <button
              v-if="course.can?.update"
              type="button"
              class="ml-1 font-medium text-brand hover:underline"
              @click="sessionModal = true"
            >
              Thêm buổi đầu tiên
            </button>
          </div>

          <div
            v-else
            class="overflow-x-auto"
          >
            <table class="w-full min-w-[32rem] text-left text-sm">
              <thead>
                <tr class="border-b border-slate-100 bg-slate-50/50 text-xs font-medium text-slate-500">
                  <th class="px-6 py-3">
                    #
                  </th>
                  <th class="px-4 py-3">
                    Tên buổi
                  </th>
                  <th class="px-4 py-3">
                    Ngày
                  </th>
                  <th class="px-4 py-3 text-right">
                    Giờ
                  </th>
                  <th class="px-4 py-3">
                    Trạng thái
                  </th>
                  <th class="px-4 py-3 w-24" />
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="s in sessions"
                  :key="s.id"
                  class="border-b border-slate-50 transition hover:bg-brand/[0.02]"
                >
                  <td class="px-6 py-3 font-mono text-xs text-slate-400">
                    {{ s.session_number }}
                  </td>
                  <td class="px-4 py-3 font-medium text-slate-800">
                    {{ s.title }}
                  </td>
                  <td class="px-4 py-3 text-slate-600">
                    {{ date(s.date) }}
                  </td>
                  <td class="px-4 py-3 text-right text-slate-600">
                    {{ s.total_hours != null ? fmtHours(s.total_hours) : '—' }}
                  </td>
                  <td class="px-4 py-3">
                    <Badge
                      v-if="s.status"
                      :label="s.status.label"
                      color="slate"
                    />
                  </td>
                  <td class="px-4 py-3 text-right">
                    <Link
                      :href="route('coaching.sessions.show', { session: s.id })"
                      class="text-xs font-medium text-brand hover:underline"
                    >
                      Chi tiết
                    </Link>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </div>

      <!-- Cột phải: người tham gia -->
      <div class="space-y-6">
        <section class="card overflow-hidden">
          <div class="border-b border-slate-100 bg-slate-50/80 px-5 py-4">
            <h2 class="font-display text-sm font-semibold text-slate-800">
              Học viên & Coach
            </h2>
          </div>
          <div class="space-y-4 p-5">
            <div>
              <p class="text-xs font-medium text-slate-500">
                Học viên
              </p>
              <p class="mt-1 text-sm font-medium text-slate-800">
                {{ course.student_display || '—' }}
              </p>
            </div>
            <div>
              <p class="text-xs font-medium text-slate-500">
                Coach / Mentor
              </p>
              <p class="mt-1 text-sm font-medium text-slate-800">
                {{ course.coach_display || '—' }}
              </p>
            </div>
          </div>
        </section>
      </div>
    </div>

    <CoachingSessionFormModal
      :show="sessionModal"
      :course-id="course.id"
      :next-session-number="nextSessionNumber"
      @close="sessionModal = false"
    />
  </AppLayout>
</template>
