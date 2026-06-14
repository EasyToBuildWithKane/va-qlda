<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import CoachingWorkspace from '@/modules/coaching/components/CoachingWorkspace.vue';
import { date, hours as fmtHours } from '@/composables/useFormat';

const props = defineProps({
    sessions: { type: Array, default: () => [] },
    month: { type: String, required: true },
});

function sessionStatusColor(value) {
    if (value === 'in_progress') return 'amber';
    if (value === 'completed') return 'emerald';
    if (value === 'cancelled') return 'rose';
    return 'slate';
}

const monthLabel = computed(() => {
    const [y, m] = props.month.split('-').map(Number);
    if (!y || !m) return props.month;
    return new Date(y, m - 1, 1).toLocaleDateString('vi-VN', { month: 'long', year: 'numeric' });
});

function shiftMonth(delta) {
    const [y, m] = props.month.split('-').map(Number);
    const d = new Date(y, m - 1 + delta, 1);
    const next = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
    router.get(route('coaching.sessions.schedule'), { month: next }, { preserveScroll: true });
}

const groupedByDate = computed(() => {
    const map = new Map();
    for (const s of props.sessions) {
        const key = s.date || 'no-date';
        if (!map.has(key)) map.set(key, []);
        map.get(key).push(s);
    }
    return [...map.entries()].sort(([a], [b]) => String(a).localeCompare(String(b)));
});

function formatTimeRange(s) {
    if (s.start_time && s.end_time) return `${s.start_time} – ${s.end_time}`;
    if (s.start_time) return s.start_time;
    return null;
}
</script>

<template>
  <Head title="Lịch buổi học" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Lịch buổi học"
        subtitle="Xem buổi học theo tháng — bấm vào buổi để mở chi tiết"
        icon="calendar"
        back-href="/coaching"
      >
        <Link
          :href="route('coaching.sessions.index')"
          class="btn-ghost h-9 gap-1.5 px-3 text-sm"
        >
          <AppIcon
            name="weekly"
            :size="15"
          />
          Danh sách
        </Link>
      </PageHeader>
    </template>

    <CoachingWorkspace>
      <div class="card overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-slate-50/80 px-5 py-4">
          <div>
            <h2 class="font-display text-base font-semibold capitalize text-slate-800">
              {{ monthLabel }}
            </h2>
            <p class="mt-0.5 text-xs text-slate-500">
              {{ sessions.length }} buổi có ngày trong tháng này
            </p>
          </div>
          <div class="flex items-center gap-1">
            <button
              type="button"
              class="inline-flex h-9 w-9 items-center justify-center rounded-btn border border-slate-200 bg-white text-slate-600 hover:border-slate-300"
              aria-label="Tháng trước"
              @click="shiftMonth(-1)"
            >
              <AppIcon
                name="chevron-left"
                :size="18"
              />
            </button>
            <input
              type="month"
              :value="month"
              class="input h-9 text-sm"
              aria-label="Chọn tháng"
              @change="(e) => router.get(route('coaching.sessions.schedule'), { month: e.target.value }, { preserveScroll: true })"
            >
            <button
              type="button"
              class="inline-flex h-9 w-9 items-center justify-center rounded-btn border border-slate-200 bg-white text-slate-600 hover:border-slate-300"
              aria-label="Tháng sau"
              @click="shiftMonth(1)"
            >
              <AppIcon
                name="chevron-right"
                :size="18"
              />
            </button>
          </div>
        </div>

        <div
          v-if="!sessions.length"
          class="px-6 py-14 text-center"
        >
          <p class="text-sm text-slate-500">
            Không có buổi học nào được lên lịch trong tháng này.
          </p>
          <Link
            :href="route('coaching.sessions.index')"
            class="mt-3 inline-block text-sm font-medium text-brand hover:underline"
          >
            Xem danh sách buổi học
          </Link>
        </div>

        <div
          v-else
          class="divide-y divide-slate-100"
        >
          <section
            v-for="[day, daySessions] in groupedByDate"
            :key="day"
            class="px-5 py-4"
          >
            <div class="mb-3 flex items-baseline gap-2">
              <span class="font-display text-sm font-semibold text-brand">
                {{ date(day) }}
              </span>
              <span class="text-xs text-slate-400">{{ daySessions.length }} buổi</span>
            </div>
            <ul class="space-y-2">
              <li
                v-for="s in daySessions"
                :key="s.id"
              >
                <Link
                  :href="route('coaching.sessions.show', { session: s.id })"
                  class="flex flex-col gap-2 rounded-xl border border-slate-100 bg-white p-4 transition hover:border-brand/25 hover:bg-brand/[0.02] sm:flex-row sm:items-center sm:justify-between"
                >
                  <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-slate-800">
                      <span class="mr-2 font-mono text-xs text-slate-400">#{{ s.session_number }}</span>
                      {{ s.title }}
                    </p>
                    <p
                      v-if="s.course"
                      class="mt-1 text-xs text-slate-500"
                    >
                      {{ s.course.code }} · {{ s.course.name }}
                    </p>
                    <p
                      v-if="s.topic"
                      class="mt-1 truncate text-xs text-slate-600"
                    >
                      {{ s.topic }}
                    </p>
                  </div>
                  <div class="flex shrink-0 flex-wrap items-center gap-2 sm:justify-end">
                    <span
                      v-if="formatTimeRange(s)"
                      class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-600"
                    >
                      <AppIcon
                        name="calendar-clock"
                        :size="13"
                      />
                      {{ formatTimeRange(s) }}
                    </span>
                    <span
                      v-if="s.total_hours != null"
                      class="text-xs text-slate-500"
                    >
                      {{ fmtHours(s.total_hours) }}
                    </span>
                    <Badge
                      v-if="s.status"
                      :label="s.status.label"
                      :color="sessionStatusColor(s.status.value)"
                    />
                  </div>
                </Link>
              </li>
            </ul>
          </section>
        </div>
      </div>
    </CoachingWorkspace>
  </AppLayout>
</template>
