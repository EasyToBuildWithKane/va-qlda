<script setup>
/* eslint-disable vue/no-v-html -- rendered markdown report fields */
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import ScoringPanel from '@/modules/daily-report/components/ScoringPanel.vue';
import ReviewPendingMembersList from '@/modules/daily-report/components/ReviewPendingMembersList.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import DatagridPaginationFooter from '@/shared/ui/DatagridPaginationFooter.vue';
import { EMPTY_LABELS } from '@/shared/utils/emptyDisplay';

const props = defineProps({
    reports: { type: Object, required: true }, // { data, meta, links }
    pendingMembers: { type: Array, default: () => [] },
    queueTotals: {
        type: Object,
        default: () => ({ reports: 0, members: 0 }),
    },
    filters: {
        type: Object,
        default: () => ({ employee_id: null }),
    },
});

const searchQuery = ref('');
const perPage = ref(Number(props.reports.meta?.per_page) || 15);

const activeEmployeeId = computed(() => {
    const id = props.filters?.employee_id;
    return id != null && id !== '' ? Number(id) : null;
});

const filteredReports = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return props.reports.data ?? [];
    return (props.reports.data ?? []).filter((r) =>
        (r.employee?.name ?? '').toLowerCase().includes(q) ||
        (r.title ?? '').toLowerCase().includes(q) ||
        (r.date ?? '').includes(q),
    );
});

const reportSectionTitle = computed(() => {
    if (activeEmployeeId.value) {
        const member = props.pendingMembers.find((m) => m.employee_id === activeEmployeeId.value);
        if (member?.name) {
            return `Báo cáo chờ duyệt — ${member.name}`;
        }
    }
    return 'Báo cáo chờ duyệt';
});

// Report content is rich HTML from the editor.
const render = (html) => html || `<span class="text-slate-400">${EMPTY_LABELS.generic}</span>`;

const preview = [
    ['Mục tiêu hôm nay', 'goals_today'],
    ['Tiến độ thực hiện', 'progress_update'],
    ['Khó khăn & vướng mắc', 'blockers'],
];
</script>

<template>
  <Head title="Duyệt báo cáo" />

  <AppLayout>
    <template #header>
      <PageHeader
        title="Duyệt báo cáo"
        subtitle="Xem xét và đánh giá báo cáo của thành viên"
        icon="review-reports"
        icon-color="violet"
        :badge="queueTotals.reports || null"
      />
    </template>

    <div class="w-full min-w-0 space-y-6">
      <ReviewPendingMembersList
        :members="pendingMembers"
        :totals="queueTotals"
        :active-employee-id="activeEmployeeId"
        :search-query="searchQuery"
      />

      <div
        v-if="queueTotals.reports > 0"
        class="card mb-0 min-w-0 overflow-visible px-4 py-3 sm:px-5"
      >
        <DatagridToolbarSearch
          v-model="searchQuery"
          input-id="daily-reports-review-search"
          placeholder="Tên nhân viên, tiêu đề, ngày…"
          stretch
          hide-label
          input-height="h-10"
        />
      </div>

      <div
        v-if="queueTotals.reports === 0"
        class="card flex flex-col items-center gap-2 p-14 text-center text-slate-400"
      >
        <AppIcon
          name="review-reports"
          :size="36"
          class="text-slate-300"
        />
        <p class="text-sm">
          Không có báo cáo nào đang chờ duyệt.
        </p>
      </div>

      <template v-else>
        <div class="flex flex-wrap items-center justify-between gap-2 px-0.5">
          <h2 class="font-display text-sm font-semibold text-slate-800">
            {{ reportSectionTitle }}
          </h2>
          <p class="text-xs text-slate-500">
            {{ filteredReports.length }} / {{ reports.data?.length ?? 0 }} trên trang này
          </p>
        </div>

        <div
          v-if="(reports.data?.length ?? 0) === 0"
          class="card flex flex-col items-center gap-2 p-14 text-center text-slate-400"
        >
          <p class="text-sm">
            Không có báo cáo nào cho bộ lọc hiện tại.
          </p>
        </div>

        <div
          v-else-if="filteredReports.length === 0"
          class="card flex flex-col items-center gap-2 p-14 text-center text-slate-400"
        >
          <p class="text-sm">
            Không có báo cáo khớp từ khoá tìm kiếm.
          </p>
        </div>

        <div
          v-else
          class="space-y-6"
        >
          <article
            v-for="r in filteredReports"
            :id="`report-${r.id}`"
            :key="r.id"
            class="card min-w-0 overflow-hidden p-4 sm:p-6"
          >
            <!-- Report content -->
            <div class="min-w-0 space-y-5">
              <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 pb-3">
                <div class="flex min-w-0 items-center gap-2.5">
                  <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-brand-100 text-sm font-semibold text-brand">
                    {{ (r.employee?.name || '?').charAt(0) }}
                  </span>
                  <div class="min-w-0 leading-tight">
                    <p class="truncate font-medium text-slate-800">
                      {{ r.employee?.name }}
                    </p>
                    <p class="line-clamp-2 text-xs text-slate-400">
                      {{ r.date }} · {{ r.title }}
                    </p>
                  </div>
                </div>
                <Link
                  :href="`/daily-reports/${r.id}`"
                  class="btn-ghost shrink-0 gap-1.5 text-sm"
                  title="Mở báo cáo đầy đủ"
                >
                  <AppIcon
                    name="eye"
                    :size="15"
                  /> Mở
                </Link>
              </div>

              <div
                v-for="[label, key] in preview"
                :key="key"
                class="min-w-0"
              >
                <h3 class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-slate-400">
                  {{ label }}
                </h3>
                <div
                  class="rich-content break-words text-sm text-slate-600"
                  v-html="render(r[key])"
                />
              </div>
            </div>

            <!-- Scoring -->
            <div class="mt-6 border-t border-slate-100 pt-6">
              <ScoringPanel :report="r" />
            </div>
          </article>
        </div>

        <DatagridPaginationFooter
          v-if="reports.meta?.last_page > 1"
          variant="bar"
          :meta="reports.meta"
          :per-page="perPage"
          :per-page-options="[15, 30, 50]"
        />
      </template>
    </div>
  </AppLayout>
</template>
