<script setup>
/* eslint-disable vue/no-v-html -- rendered markdown report fields */
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import ScoringPanel from '@/Components/DailyReport/ScoringPanel.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';

const props = defineProps({
    reports: { type: Object, required: true }, // { data, meta }
});

const searchQuery = ref('');

const filteredReports = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return props.reports.data ?? [];
    return (props.reports.data ?? []).filter((r) =>
        (r.employee?.name ?? '').toLowerCase().includes(q) ||
        (r.title ?? '').toLowerCase().includes(q) ||
        (r.date ?? '').includes(q),
    );
});

// Report content is rich HTML from the editor.
const render = (html) => html || '<span class="text-slate-300">—</span>';

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
        :badge="reports.data?.length"
      />
    </template>

    <div
      v-if="(reports.data?.length ?? 0) > 0"
      class="card mb-4 overflow-visible px-5 py-3"
    >
      <DatagridToolbarSearch
        v-model="searchQuery"
        input-id="daily-reports-review-search"
        placeholder="Tên nhân viên, tiêu đề, ngày…"
      />
    </div>

    <div
      v-if="reports.data.length === 0"
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
        :key="r.id"
        class="card grid grid-cols-1 gap-6 p-6 lg:grid-cols-3"
      >
        <!-- Report content -->
        <div class="space-y-4 lg:col-span-2">
          <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2.5">
              <span class="grid h-9 w-9 place-items-center rounded-full bg-brand-100 text-sm font-semibold text-brand">
                {{ (r.employee?.name || '?').charAt(0) }}
              </span>
              <div class="leading-tight">
                <p class="font-medium text-slate-800">
                  {{ r.employee?.name }}
                </p>
                <p class="text-xs text-slate-400">
                  {{ r.date }} · {{ r.title }}
                </p>
              </div>
            </div>
            <Link
              :href="`/daily-reports/${r.id}`"
              class="btn-ghost gap-1.5 text-sm"
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
          >
            <h3 class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-400">
              {{ label }}
            </h3>
            <div
              class="rich-content text-sm text-slate-600"
              v-html="render(r[key])"
            />
          </div>
        </div>

        <!-- Scoring -->
        <div class="border-t border-slate-100 pt-4 lg:border-l lg:border-t-0 lg:pl-6 lg:pt-0">
          <ScoringPanel :report="r" />
        </div>
      </article>
    </div>
  </AppLayout>
</template>
