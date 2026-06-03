<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';

const props = defineProps({
    stats: { type: Object, required: true },
    trend: { type: Array, default: () => [] },
    byCategory: { type: Object, default: () => ({}) },
    recentErrors: { type: Array, default: () => [] },
    topActors: { type: Array, default: () => [] },
});

const maxTrend = computed(() => Math.max(1, ...props.trend.map((t) => t.count)));

const errorsSearch = ref('');

const filteredRecentErrors = computed(() => {
    const q = errorsSearch.value.trim().toLowerCase();
    if (!q) return props.recentErrors;
    return props.recentErrors.filter((err) =>
        (err.title ?? '').toLowerCase().includes(q) ||
        (err.body ?? '').toLowerCase().includes(q) ||
        (err.priority ?? '').toLowerCase().includes(q),
    );
});

const categoryLabels = {
    task: 'Công việc',
    sprint: 'Sprint',
    project: 'Dự án',
    document: 'Tài liệu',
    comment: 'Bình luận',
    system: 'Hệ thống',
    admin: 'Quản trị',
};
</script>

<template>
  <Head title="Quản lý thông báo" />

  <AppLayout>
    <template #header>
      <PageHeader
        title="Quản lý thông báo"
        subtitle="Operational monitoring — toàn hệ thống"
        icon="notifications"
        icon-color="brand"
      />
    </template>

    <div class="mb-4 flex items-center gap-2 text-[13px]">
      <Link
        href="/dashboard"
        class="text-slate-500 hover:text-brand"
      >
        Dashboard
      </Link>
      <span class="text-slate-300">/</span>
      <span class="text-slate-700 font-medium">Thông báo hệ thống</span>
    </div>

    <!-- KPI -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-6">
      <div class="card p-4">
        <p class="text-[11px] uppercase tracking-wide text-slate-500">
          Tổng
        </p>
        <p class="mt-1 text-2xl font-display font-bold text-slate-800">
          {{ stats.total }}
        </p>
      </div>
      <div class="card p-4">
        <p class="text-[11px] uppercase tracking-wide text-slate-500">
          Chưa đọc
        </p>
        <p class="mt-1 text-2xl font-display font-bold text-brand">
          {{ stats.unread }}
        </p>
      </div>
      <div class="card p-4 border-rose-100 bg-rose-50/30">
        <p class="text-[11px] uppercase tracking-wide text-rose-600">
          Critical
        </p>
        <p class="mt-1 text-2xl font-display font-bold text-rose-700">
          {{ stats.critical }}
        </p>
      </div>
      <div class="card p-4 border-amber-100 bg-amber-50/30">
        <p class="text-[11px] uppercase tracking-wide text-amber-700">
          Warning
        </p>
        <p class="mt-1 text-2xl font-display font-bold text-amber-800">
          {{ stats.warning }}
        </p>
      </div>
      <div class="card p-4 col-span-2 lg:col-span-1">
        <p class="text-[11px] uppercase tracking-wide text-slate-500">
          Info
        </p>
        <p class="mt-1 text-2xl font-display font-bold text-sky-700">
          {{ stats.info }}
        </p>
      </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-6">
      <!-- Trend -->
      <div class="card p-5">
        <h2 class="font-display font-semibold text-slate-800 mb-4 flex items-center gap-2">
          <AppIcon
            name="overview"
            :size="16"
            class="text-brand"
          />
          Notification Trend (14 ngày)
        </h2>
        <div class="flex items-end gap-1 h-32">
          <div
            v-for="point in trend"
            :key="point.date"
            class="flex-1 flex flex-col items-center gap-1 min-w-0"
          >
            <div
              class="w-full rounded-t bg-brand/80 min-h-[4px] transition-all"
              :style="{ height: `${(point.count / maxTrend) * 100}%` }"
              :title="`${point.date}: ${point.count}`"
            />
            <span class="text-[9px] text-slate-400 truncate w-full text-center">
              {{ point.date?.slice(5) }}
            </span>
          </div>
          <p
            v-if="!trend.length"
            class="text-sm text-slate-400 w-full text-center py-8"
          >
            Chưa có dữ liệu
          </p>
        </div>
      </div>

      <!-- By category -->
      <div class="card p-5">
        <h2 class="font-display font-semibold text-slate-800 mb-4">
          Phân loại
        </h2>
        <ul class="space-y-2">
          <li
            v-for="(count, cat) in byCategory"
            :key="cat"
            class="flex items-center justify-between text-[13px]"
          >
            <span class="text-slate-600">{{ categoryLabels[cat] ?? cat }}</span>
            <span class="font-semibold text-slate-800">{{ count }}</span>
          </li>
          <li
            v-if="!Object.keys(byCategory).length"
            class="text-sm text-slate-400"
          >
            Chưa có dữ liệu
          </li>
        </ul>
      </div>

      <!-- System errors -->
      <div class="card p-5 lg:col-span-2">
        <h2 class="font-display font-semibold text-slate-800 mb-4 flex items-center gap-2">
          <AppIcon
            name="blockers"
            :size="16"
            class="text-rose-500"
          />
          Lỗi hệ thống gần đây
        </h2>
        <div class="mb-4">
          <DatagridToolbarSearch
            v-model="errorsSearch"
            input-id="notifications-errors-search"
            placeholder="Tiêu đề, nội dung, mức độ…"
          />
        </div>
        <div
          v-if="!recentErrors.length"
          class="text-sm text-slate-400 py-4"
        >
          Không có lỗi ghi nhận.
        </div>
        <div
          v-else-if="!filteredRecentErrors.length"
          class="text-sm text-slate-400 py-4"
        >
          Không khớp từ khoá tìm kiếm.
        </div>
        <ul
          v-else
          class="divide-y divide-slate-100"
        >
          <li
            v-for="err in filteredRecentErrors"
            :key="err.id"
            class="py-3 flex gap-3"
          >
            <span
              class="shrink-0 rounded px-1.5 py-0.5 text-[10px] font-bold uppercase"
              :class="err.priority === 'critical' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-800'"
            >
              {{ err.priority }}
            </span>
            <div class="min-w-0">
              <p class="text-[13px] font-medium text-slate-800">
                {{ err.title }}
              </p>
              <p
                v-if="err.body"
                class="text-[12px] text-slate-500 truncate"
              >
                {{ err.body }}
              </p>
              <p class="text-[11px] text-slate-400 mt-0.5">
                {{ err.created_at }}
              </p>
            </div>
          </li>
        </ul>
      </div>

      <!-- Top actors -->
      <div class="card p-5 lg:col-span-2">
        <h2 class="font-display font-semibold text-slate-800 mb-4 flex items-center gap-2">
          <AppIcon
            name="members"
            :size="16"
            class="text-brand"
          />
          Hoạt động người dùng
        </h2>
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-2">
          <div
            v-for="actor in topActors"
            :key="actor.actor_name"
            class="rounded-xl border border-slate-100 px-3 py-2.5 flex justify-between items-center"
          >
            <span class="text-[13px] text-slate-700 truncate">{{ actor.actor_name }}</span>
            <span class="text-[12px] font-bold text-brand">{{ actor.count }}</span>
          </div>
        </div>
        <p
          v-if="!topActors.length"
          class="text-sm text-slate-400 py-4"
        >
          Chưa có hoạt động.
        </p>
      </div>
    </div>
  </AppLayout>
</template>
