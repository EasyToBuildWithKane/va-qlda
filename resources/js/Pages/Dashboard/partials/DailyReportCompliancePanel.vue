<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import EmptyState from '@/shared/ui/EmptyState.vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';
import DatagridPaginationFooter from '@/shared/ui/DatagridPaginationFooter.vue';
import { useClientPagination } from '@/shared/composables/useClientPagination';

const props = defineProps({
    compliance: { type: Object, default: () => ({}) },
});

const peopleAll = computed(() => props.compliance.people ?? []);
const summary = computed(() => props.compliance.summary ?? {});
const period = computed(() => props.compliance.period ?? {});

const teamRate = computed(() => summary.value.teamRate ?? 0);

const summaryCards = computed(() => {
    const s = summary.value;
    const total = s.totalPeople ?? 0;
    const complete = s.completeCount ?? 0;

    return [
        {
            key: 'compliance',
            label: 'Đủ báo cáo tuần',
            value: complete,
            suffix: total ? `/${total}` : '',
            suffixClass: 'text-lg font-medium text-slate-400',
            tone: 'brand',
            icon: 'daily',
            sub: period.value.label ? `Tuần ${period.value.label}` : 'Tuần hiện tại',
            progress: total > 0 ? Math.round((complete / total) * 100) : 0,
        },
        {
            key: 'team_rate',
            label: 'Tỉ lệ tuân thủ',
            value: teamRate.value,
            suffix: '%',
            tone: 'sky',
            icon: 'performance',
            sub: 'Đã nộp / ngày làm việc kỳ vọng',
        },
        {
            key: 'tasks_done',
            label: 'Xong trong ngày',
            value: s.completedTasksToday ?? 0,
            tone: 'emerald',
            icon: 'task',
            sub: 'Tổng công việc hoàn thành hôm nay',
        },
        {
            key: 'tasks_due',
            label: 'Đến hạn hôm nay',
            value: s.dueTasksToday ?? 0,
            tone: 'amber',
            icon: 'calendar-clock',
            sub: `${s.hoursLoggedToday ?? 0}h đã log hôm nay`,
        },
    ];
});

const {
    paginatedItems: people,
    meta: paginationMeta,
    perPage,
    setPerPage,
    goToPage,
    PER_PAGE_OPTIONS,
} = useClientPagination(peopleAll, 'va-workspace.dashboard.compliance.perPage', 5);

const kindBadge = {
    completed: 'bg-emerald-50 text-emerald-700',
    due: 'bg-amber-50 text-amber-800',
    active: 'bg-sky-50 text-sky-700',
};

const kindLabel = {
    completed: 'Xong HT',
    due: 'Đến hạn',
    active: 'Trong ngày',
};
</script>

<template>
  <section class="card overflow-hidden">
    <KpiSummaryStrip
      :cards="summaryCards"
      :progress-denominator="summary.totalPeople ?? 0"
      variant="embedded"
      eyebrow="Thống kê"
      heading="Tuân thủ báo cáo & công việc ngày"
      :hint="compliance.scope?.departmentName ? compliance.scope.departmentName : ''"
      aria-label="Thống kê tuân thủ báo cáo và công việc trong ngày"
      grid-class="grid-cols-2 lg:grid-cols-4"
    />

    <div class="border-t border-slate-100 px-5 py-4">
      <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <p class="text-xs text-slate-500">
          Chi tiết theo nhân sự — báo cáo tuần và công việc trong ngày
        </p>
        <Link
          href="/daily-reports"
          class="inline-flex items-center gap-1 text-xs font-medium text-brand hover:underline"
        >
          Lịch sử báo cáo
          <AppIcon
            name="chevron-right"
            :size="12"
          />
        </Link>
      </div>

      <div v-if="peopleAll.length">
        <div class="overflow-x-auto">
          <table class="w-full min-w-[720px] text-left text-sm">
            <thead>
              <tr class="border-b border-slate-100 text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                <th class="pb-2 pr-3 font-semibold">
                  Nhân sự
                </th>
                <th class="pb-2 pr-3 font-semibold">
                  Báo cáo tuần
                </th>
                <th class="pb-2 pr-3 font-semibold">
                  CV hôm nay
                </th>
                <th class="pb-2 pr-3 font-semibold">
                  Tỉ lệ BC
                </th>
                <th class="pb-2 font-semibold">
                  Công việc trong ngày
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              <tr
                v-for="row in people"
                :key="row.id"
                class="align-top"
              >
                <td class="py-3 pr-3">
                  <div class="flex items-center gap-2.5">
                    <Avatar
                      :name="row.name"
                      :src="row.avatar"
                      :size="28"
                    />
                    <div class="min-w-0">
                      <span class="font-medium text-slate-800">{{ row.name }}</span>
                      <p
                        v-if="row.ok"
                        class="text-[10px] font-semibold text-emerald-600"
                      >
                        Đủ báo cáo tuần
                      </p>
                      <p
                        v-else
                        class="text-[10px] font-medium text-amber-700"
                      >
                        Thiếu {{ row.missing }} ngày BC
                      </p>
                    </div>
                  </div>
                </td>
                <td class="py-3 pr-3 tabular-nums text-slate-700">
                  <span class="font-semibold">{{ row.submitted }}</span>
                  <span class="text-slate-400">/{{ row.expected }}</span>
                </td>
                <td class="py-3 pr-3">
                  <div class="flex flex-wrap gap-1.5 text-[11px] font-semibold tabular-nums">
                    <span class="rounded-md bg-emerald-50 px-1.5 py-0.5 text-emerald-700">
                      {{ row.workToday?.completed ?? 0 }} xong
                    </span>
                    <span class="rounded-md bg-amber-50 px-1.5 py-0.5 text-amber-800">
                      {{ row.workToday?.due ?? 0 }} hạn
                    </span>
                    <span class="rounded-md bg-sky-50 px-1.5 py-0.5 text-sky-700">
                      {{ row.workToday?.hours ?? 0 }}h
                    </span>
                  </div>
                </td>
                <td class="py-3 pr-3">
                  <div class="flex min-w-[7rem] items-center gap-2">
                    <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-100">
                      <div
                        class="h-full rounded-full transition-all"
                        :class="row.ok ? 'bg-emerald-500' : 'bg-amber-500'"
                        :style="{ width: Math.min(100, row.rate) + '%' }"
                      />
                    </div>
                    <span class="w-10 shrink-0 text-right text-xs font-semibold tabular-nums text-slate-600">
                      {{ row.rate }}%
                    </span>
                  </div>
                </td>
                <td class="py-3">
                  <ul
                    v-if="row.workToday?.tasks?.length"
                    class="space-y-1.5"
                  >
                    <li
                      v-for="task in row.workToday.tasks"
                      :key="task.id"
                      class="flex min-w-0 items-start gap-2 rounded-lg border border-slate-100 bg-slate-50/80 px-2.5 py-1.5"
                    >
                      <span
                        class="mt-1 h-2 w-2 shrink-0 rounded-full"
                        :style="{ backgroundColor: task.project?.color || '#94a3b8' }"
                      />
                      <div class="min-w-0 flex-1">
                        <p class="truncate text-xs font-medium text-slate-800">
                          {{ task.title }}
                        </p>
                        <p class="truncate text-[10px] text-slate-400">
                          {{ task.project?.name || '—' }}
                        </p>
                      </div>
                      <span
                        class="shrink-0 rounded px-1.5 py-0.5 text-[10px] font-semibold"
                        :class="kindBadge[task.kind] ?? kindBadge.active"
                      >
                        {{ kindLabel[task.kind] ?? task.statusLabel }}
                      </span>
                    </li>
                  </ul>
                  <p
                    v-else
                    class="text-xs text-slate-400"
                  >
                    Chưa có công việc ghi nhận hôm nay
                  </p>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <DatagridPaginationFooter
          v-if="paginationMeta.total"
          variant="bar"
          client
          :meta="paginationMeta"
          :per-page="perPage"
          :per-page-options="PER_PAGE_OPTIONS"
          @update:per-page="setPerPage"
          @page-change="goToPage"
        />
      </div>
      <EmptyState
        v-else
        icon="people"
        title="Chưa có nhân sự hoạt động"
        description="Thống kê tuân thủ sẽ hiển thị khi có nhân sự Phòng Công nghệ."
      />
    </div>
  </section>
</template>
