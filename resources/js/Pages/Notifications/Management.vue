<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import NotificationOpsSummaryBar from '@/modules/notifications/components/NotificationOpsSummaryBar.vue';

const props = defineProps({
    stats: { type: Object, required: true },
    health: { type: Object, required: true },
    trend: { type: Array, default: () => [] },
    byCategory: { type: Array, default: () => [] },
    aggregatedFeed: { type: Array, default: () => [] },
    topActors: { type: Array, default: () => [] },
    recentErrors: { type: Array, default: () => [] },
});

// ─── State ────────────────────────────────────────────────────────────────────
const activeTab = ref('overview');
const feedCategory = ref('all');
const expandedGroups = ref({});
const errorsSearch = ref('');

// ─── Config ───────────────────────────────────────────────────────────────────
const TABS = [
    { key: 'overview',    label: 'Tổng quan',  icon: 'overview' },
    { key: 'feed',        label: 'Hoạt động',  icon: 'notifications' },
    { key: 'monitoring',  label: 'Giám sát',   icon: 'blockers' },
];

const CATEGORY_CONFIG = {
    project:  { label: 'Dự án',      icon: 'projects',  iconBg: 'bg-indigo-100', iconColor: 'text-indigo-600', dot: 'bg-indigo-500', badge: 'bg-indigo-100 text-indigo-700', verb: 'cập nhật dự án',     plural: 'dự án' },
    task:     { label: 'Công việc',  icon: 'task',       iconBg: 'bg-sky-100',    iconColor: 'text-sky-600',    dot: 'bg-sky-500',    badge: 'bg-sky-100 text-sky-700',       verb: 'thao tác công việc', plural: 'công việc' },
    sprint:   { label: 'Sprint',     icon: 'sprint',     iconBg: 'bg-violet-100', iconColor: 'text-violet-600', dot: 'bg-violet-500', badge: 'bg-violet-100 text-violet-700', verb: 'thao tác sprint',    plural: 'sprint' },
    document: { label: 'Tài liệu',  icon: 'documents',  iconBg: 'bg-amber-100',  iconColor: 'text-amber-600',  dot: 'bg-amber-500',  badge: 'bg-amber-100 text-amber-700',   verb: 'thao tác tài liệu', plural: 'tài liệu' },
    comment:  { label: 'Bình luận', icon: 'comment',    iconBg: 'bg-teal-100',   iconColor: 'text-teal-600',   dot: 'bg-teal-500',   badge: 'bg-teal-100 text-teal-700',     verb: 'bình luận',         plural: 'bình luận' },
    system:   { label: 'Hệ thống',  icon: 'settings',   iconBg: 'bg-slate-100',  iconColor: 'text-slate-500',  dot: 'bg-slate-400',  badge: 'bg-slate-100 text-slate-600',   verb: 'sự kiện hệ thống',  plural: 'sự kiện' },
    admin:    { label: 'Quản trị',  icon: 'blockers',   iconBg: 'bg-rose-100',   iconColor: 'text-rose-600',   dot: 'bg-rose-500',   badge: 'bg-rose-100 text-rose-700',     verb: 'thao tác quản trị', plural: 'thao tác' },
};

const CATEGORY_ORDER = ['project', 'task', 'sprint', 'document', 'comment', 'system', 'admin'];

const HEALTH_SERVICES = [
    { key: 'queue',    label: 'Queue',         desc: 'Hàng đợi job',    icon: 'sprint' },
    { key: 'jobs',     label: 'Scheduled',     desc: 'Tác vụ định kỳ', icon: 'calendar-clock' },
    { key: 'api',      label: 'API',           desc: 'Lỗi API',         icon: 'globe' },
    { key: 'import',   label: 'Import/Export', desc: 'Nhập xuất',       icon: 'upload' },
    { key: 'security', label: 'Security',      desc: 'Vi phạm quyền',  icon: 'flag' },
    { key: 'system',   label: 'System',        desc: 'Lỗi hệ thống',   icon: 'settings' },
];

const HEALTH_CONFIG = {
    healthy:  { dot: 'bg-emerald-500',            card: 'border-emerald-100 bg-emerald-50/40', text: 'text-emerald-700', label: 'Bình thường' },
    warning:  { dot: 'bg-amber-400',              card: 'border-amber-100 bg-amber-50/40',     text: 'text-amber-700',   label: 'Cảnh báo' },
    critical: { dot: 'bg-rose-500 animate-pulse', card: 'border-rose-200 bg-rose-50/50',       text: 'text-rose-700',    label: 'Có lỗi' },
};

const PRIORITY_CONFIG = {
    critical: { label: 'Critical', cls: 'bg-rose-100 text-rose-700' },
    high:     { label: 'High',     cls: 'bg-amber-100 text-amber-700' },
    medium:   { label: 'Medium',   cls: 'bg-sky-100 text-sky-700' },
    low:      { label: 'Low',      cls: 'bg-slate-100 text-slate-600' },
};

const ERROR_TYPE_LABELS = {
    admin_api_error:        'API Error',
    admin_job_failed:       'Job Failed',
    admin_queue_failed:     'Queue Failed',
    admin_import_failed:    'Import Failed',
    system_error:           'System Error',
    admin_permission_error: 'Permission Error',
};

const DATE_GROUP_LABELS = { today: 'Hôm nay', yesterday: 'Hôm qua', week: 'Tuần này' };

// ─── Computed ─────────────────────────────────────────────────────────────────
const maxTrend = computed(() => Math.max(1, ...props.trend.map((t) => t.count)));

const overallHealth = computed(() => {
    const statuses = Object.values(props.health).map((h) => h.status);
    if (statuses.includes('critical')) return 'critical';
    if (statuses.includes('warning')) return 'warning';
    return 'healthy';
});

const maxCategoryCount = computed(() => props.byCategory[0]?.count ?? 1);

const feedCategoryCounts = computed(() => {
    const counts = {};
    for (const g of props.aggregatedFeed) counts[g.category] = (counts[g.category] ?? 0) + 1;
    return counts;
});

const activeFeedCategories = computed(() =>
    CATEGORY_ORDER.filter((cat) => feedCategoryCounts.value[cat]),
);

const filteredFeed = computed(() =>
    feedCategory.value === 'all'
        ? props.aggregatedFeed
        : props.aggregatedFeed.filter((g) => g.category === feedCategory.value),
);

const groupedFeed = computed(() => {
    const buckets = {};
    for (const item of filteredFeed.value) {
        const key = dateGroupKey(item.latest_at);
        if (!buckets[key]) buckets[key] = { label: DATE_GROUP_LABELS[key] ?? key, items: [] };
        buckets[key].items.push(item);
    }
    return Object.values(buckets);
});

const filteredErrors = computed(() => {
    const q = errorsSearch.value.trim().toLowerCase();
    if (!q) return props.recentErrors;
    return props.recentErrors.filter(
        (e) => (e.title ?? '').toLowerCase().includes(q) || (e.body ?? '').toLowerCase().includes(q),
    );
});

// ─── Helpers ──────────────────────────────────────────────────────────────────
function catCfg(cat) {
    return CATEGORY_CONFIG[cat] ?? CATEGORY_CONFIG.system;
}

function healthOf(key) {
    return HEALTH_CONFIG[props.health[key]?.status ?? 'healthy'];
}

function healthCount(key) {
    return props.health[key]?.count ?? 0;
}

function dateGroupKey(iso) {
    if (!iso) return 'unknown';
    const d = new Date(iso);
    const now = new Date();
    const yest = new Date(now);
    yest.setDate(now.getDate() - 1);
    const toStr = (x) => `${x.getFullYear()}-${x.getMonth()}-${x.getDate()}`;
    if (toStr(d) === toStr(now)) return 'today';
    if (toStr(d) === toStr(yest)) return 'yesterday';
    const days = Math.floor((now - d) / 86400000);
    if (days < 7) return 'week';
    return d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function formatRelative(iso) {
    if (!iso) return '';
    const diff = Date.now() - new Date(iso).getTime();
    const mins = Math.floor(diff / 60000);
    const hrs = Math.floor(diff / 3600000);
    const days = Math.floor(diff / 86400000);
    if (mins < 1) return 'vừa xong';
    if (mins < 60) return `${mins} phút trước`;
    if (hrs < 24) return `${hrs} giờ trước`;
    if (days < 7) return `${days} ngày trước`;
    return new Date(iso).toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit' });
}

function formatTime(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
}

function groupKey(group) {
    return `${group.actor_name}|${group.category}|${group.latest_at}`;
}

function toggleGroup(group) {
    const k = groupKey(group);
    expandedGroups.value[k] = !expandedGroups.value[k];
}

function isExpanded(group) {
    return !!expandedGroups.value[groupKey(group)];
}
</script>

<template>
  <Head title="Trung tâm vận hành" />

  <AppLayout>
    <template #header>
      <PageHeader
        title="Trung tâm vận hành"
        subtitle="Activity Center · Operational Monitoring · Admin Feed"
        icon="notifications"
        icon-color="brand"
      />
    </template>

    <!-- Tab navigation -->
    <div class="flex gap-0.5 border-b border-slate-200 mb-6">
      <button
        v-for="tab in TABS"
        :key="tab.key"
        class="flex items-center gap-1.5 px-4 py-2.5 text-[13px] font-medium transition-colors border-b-2"
        :class="activeTab === tab.key
          ? 'border-brand text-brand -mb-px bg-white'
          : 'border-transparent text-slate-500 hover:text-slate-700'"
        @click="activeTab = tab.key"
      >
        <AppIcon
          :name="tab.icon"
          :size="14"
        />
        {{ tab.label }}
        <span
          v-if="tab.key === 'feed' && aggregatedFeed.length"
          class="ml-1 rounded-full bg-slate-100 text-slate-600 text-[10px] px-1.5 py-0.5"
        >
          {{ aggregatedFeed.length }}
        </span>
      </button>
    </div>

    <!-- ══════════════════════════════════════════════════════════════ -->
    <!-- TAB: Tổng quan                                                -->
    <!-- ══════════════════════════════════════════════════════════════ -->
    <template v-if="activeTab === 'overview'">
      <NotificationOpsSummaryBar :stats="stats" />

      <!-- Operational Health -->
      <div class="card p-5 mb-5">
        <div class="flex items-center justify-between mb-4">
          <h2 class="font-display font-semibold text-slate-800 flex items-center gap-2">
            <span
              class="inline-block w-2.5 h-2.5 rounded-full"
              :class="HEALTH_CONFIG[overallHealth].dot"
            />
            Trạng thái vận hành
            <span class="text-[12px] font-normal text-slate-400">(24 giờ qua)</span>
          </h2>
          <span
            class="text-[12px] font-medium px-2.5 py-1 rounded-full"
            :class="{
              'bg-emerald-100 text-emerald-700': overallHealth === 'healthy',
              'bg-amber-100 text-amber-700': overallHealth === 'warning',
              'bg-rose-100 text-rose-700': overallHealth === 'critical',
            }"
          >
            {{ HEALTH_CONFIG[overallHealth].label }}
          </span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
          <div
            v-for="svc in HEALTH_SERVICES"
            :key="svc.key"
            class="rounded-xl border p-3 transition-colors"
            :class="healthOf(svc.key).card"
          >
            <div class="flex items-center justify-between mb-2">
              <AppIcon
                :name="svc.icon"
                :size="13"
                class="text-slate-400"
              />
              <span
                class="w-2 h-2 rounded-full inline-block"
                :class="healthOf(svc.key).dot"
              />
            </div>
            <p class="text-[12px] font-semibold text-slate-700 leading-tight">
              {{ svc.label }}
            </p>
            <p class="text-[10px] text-slate-400 mb-1.5">
              {{ svc.desc }}
            </p>
            <p
              class="text-[11px] font-medium"
              :class="healthOf(svc.key).text"
            >
              {{ healthCount(svc.key) }} lỗi / 24h
            </p>
          </div>
        </div>
      </div>

      <!-- Trend + Category -->
      <div class="grid lg:grid-cols-2 gap-5">
        <!-- Trend chart -->
        <div class="card p-5">
          <h2 class="font-display font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <AppIcon
              name="overview"
              :size="15"
              class="text-brand"
            />
            Xu hướng 14 ngày
          </h2>
          <div class="flex items-end gap-1 h-28">
            <template v-if="trend.length">
              <div
                v-for="point in trend"
                :key="point.date"
                class="flex-1 flex flex-col items-center gap-1 min-w-0"
              >
                <div
                  class="w-full rounded-t bg-brand/70 min-h-[4px] transition-all hover:bg-brand cursor-default"
                  :style="{ height: `${(point.count / maxTrend) * 100}%` }"
                  :title="`${point.date}: ${point.count}`"
                />
                <span class="text-[9px] text-slate-400 truncate w-full text-center">
                  {{ point.date?.slice(5) }}
                </span>
              </div>
            </template>
            <p
              v-else
              class="text-sm text-slate-400 w-full text-center py-8"
            >
              Chưa có dữ liệu
            </p>
          </div>
        </div>

        <!-- Category breakdown -->
        <div class="card p-5">
          <h2 class="font-display font-semibold text-slate-800 mb-4">
            Phân loại sự kiện
          </h2>
          <div
            v-if="byCategory.length"
            class="space-y-3"
          >
            <div
              v-for="item in byCategory"
              :key="item.category"
              class="flex items-center gap-3"
            >
              <span
                class="w-2 h-2 rounded-full shrink-0"
                :class="catCfg(item.category).dot"
              />
              <span class="text-[13px] text-slate-600 w-24 shrink-0">
                {{ catCfg(item.category).label }}
              </span>
              <div class="flex-1 bg-slate-100 rounded-full h-1.5">
                <div
                  class="h-1.5 rounded-full bg-brand/60"
                  :style="{ width: `${(item.count / maxCategoryCount) * 100}%` }"
                />
              </div>
              <span class="text-[12px] font-semibold text-slate-700 w-8 text-right shrink-0">
                {{ item.count }}
              </span>
            </div>
          </div>
          <p
            v-else
            class="text-sm text-slate-400"
          >
            Chưa có dữ liệu
          </p>
        </div>
      </div>
    </template>

    <!-- ══════════════════════════════════════════════════════════════ -->
    <!-- TAB: Hoạt động (Activity Feed)                                -->
    <!-- ══════════════════════════════════════════════════════════════ -->
    <template v-if="activeTab === 'feed'">
      <!-- Category filter chips -->
      <div class="flex items-center gap-2 flex-wrap mb-5">
        <button
          class="px-3 py-1.5 rounded-full text-[12px] font-medium transition-colors"
          :class="feedCategory === 'all'
            ? 'bg-brand text-white shadow-sm'
            : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
          @click="feedCategory = 'all'"
        >
          Tất cả
          <span class="ml-1 opacity-80">{{ aggregatedFeed.length }}</span>
        </button>

        <button
          v-for="cat in activeFeedCategories"
          :key="cat"
          class="px-3 py-1.5 rounded-full text-[12px] font-medium transition-colors"
          :class="feedCategory === cat
            ? 'bg-brand text-white shadow-sm'
            : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
          @click="feedCategory = cat"
        >
          {{ catCfg(cat).label }}
          <span class="ml-1 opacity-70">{{ feedCategoryCounts[cat] }}</span>
        </button>
      </div>

      <!-- Feed empty state -->
      <div
        v-if="!filteredFeed.length"
        class="card p-10 text-center"
      >
        <AppIcon
          name="notifications"
          :size="32"
          class="text-slate-200 mx-auto mb-3"
        />
        <p class="text-slate-400 text-sm">
          Chưa có hoạt động trong 7 ngày gần đây.
        </p>
      </div>

      <!-- Feed groups by date -->
      <div
        v-else
        class="space-y-6"
      >
        <div
          v-for="dateGroup in groupedFeed"
          :key="dateGroup.label"
        >
          <!-- Date header -->
          <div class="flex items-center gap-3 mb-3">
            <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 shrink-0">
              {{ dateGroup.label }}
            </span>
            <div class="flex-1 h-px bg-slate-100" />
          </div>

          <!-- Activity items -->
          <div class="space-y-2">
            <div
              v-for="(group, gIdx) in dateGroup.items"
              :key="gIdx"
              class="card p-4 transition-colors"
              :class="group.count > 0 && group.items.some(i => !i.is_read) ? 'border-l-2 border-l-brand/40' : ''"
            >
              <div class="flex items-start gap-3">
                <!-- Category icon bubble -->
                <div
                  class="shrink-0 w-8 h-8 rounded-lg flex items-center justify-center mt-0.5"
                  :class="catCfg(group.category).iconBg"
                >
                  <AppIcon
                    :name="catCfg(group.category).icon"
                    :size="14"
                    :class="catCfg(group.category).iconColor"
                  />
                </div>

                <!-- Content -->
                <div class="flex-1 min-w-0">
                  <p class="text-[13px] text-slate-800 leading-snug">
                    <span
                      v-if="group.actor_name"
                      class="font-semibold"
                    >{{ group.actor_name }}</span>
                    <span
                      v-else
                      class="font-semibold text-slate-500"
                    >Hệ thống</span>

                    <span
                      v-if="group.count === 1"
                      class="text-slate-600"
                    > — {{ group.items[0]?.title }}</span>
                    <span
                      v-else
                      class="text-slate-600"
                    >
                      đã {{ catCfg(group.category).verb }}
                      <span class="font-semibold text-slate-800">{{ group.count }}</span>
                      {{ catCfg(group.category).plural }}
                    </span>
                  </p>

                  <div class="flex items-center gap-2 mt-1.5">
                    <span
                      class="inline-block px-1.5 py-0.5 rounded text-[10px] font-medium"
                      :class="catCfg(group.category).badge"
                    >
                      {{ catCfg(group.category).label }}
                    </span>
                    <span class="text-[11px] text-slate-400">
                      {{ formatRelative(group.latest_at) }}
                    </span>
                    <span
                      v-if="group.count > 1"
                      class="text-[11px] text-slate-400"
                    >
                      · trong ~30 phút
                    </span>
                  </div>
                </div>

                <!-- Expand button -->
                <button
                  v-if="group.count > 1"
                  class="shrink-0 flex items-center gap-1 text-[11px] text-brand hover:text-brand/80 transition-colors mt-0.5"
                  @click="toggleGroup(group)"
                >
                  {{ isExpanded(group) ? 'Thu gọn' : 'Xem chi tiết' }}
                  <AppIcon
                    name="chevron-down"
                    :size="11"
                    class="transition-transform duration-150"
                    :class="isExpanded(group) ? 'rotate-180' : ''"
                  />
                </button>
              </div>

              <!-- Expanded sub-items -->
              <div
                v-if="group.count > 1 && isExpanded(group)"
                class="mt-3 ml-11 space-y-1.5 border-t border-slate-100 pt-3"
              >
                <div
                  v-for="item in group.items"
                  :key="item.id"
                  class="flex items-start gap-2 text-[12px]"
                >
                  <span class="w-1.5 h-1.5 rounded-full bg-slate-300 mt-1.5 shrink-0" />
                  <span
                    class="flex-1 truncate"
                    :class="item.is_read ? 'text-slate-500' : 'text-slate-700 font-medium'"
                  >
                    {{ item.title }}
                  </span>
                  <span class="text-slate-400 shrink-0">
                    {{ formatTime(item.created_at) }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- ══════════════════════════════════════════════════════════════ -->
    <!-- TAB: Giám sát                                                 -->
    <!-- ══════════════════════════════════════════════════════════════ -->
    <template v-if="activeTab === 'monitoring'">
      <div class="space-y-5">
        <!-- Health detail grid -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
          <div
            v-for="svc in HEALTH_SERVICES"
            :key="svc.key"
            class="rounded-xl border p-4 transition-colors"
            :class="healthOf(svc.key).card"
          >
            <div class="flex items-center justify-between mb-3">
              <AppIcon
                :name="svc.icon"
                :size="16"
                class="text-slate-400"
              />
              <span
                class="w-2 h-2 rounded-full"
                :class="healthOf(svc.key).dot"
              />
            </div>
            <p class="text-[13px] font-semibold text-slate-700">
              {{ svc.label }}
            </p>
            <p class="text-[11px] text-slate-400 mb-2">
              {{ svc.desc }}
            </p>
            <p
              class="text-[22px] font-display font-bold"
              :class="healthOf(svc.key).text"
            >
              {{ healthCount(svc.key) }}
            </p>
            <p class="text-[10px] text-slate-400">
              lỗi trong 24h
            </p>
            <p
              class="mt-1.5 text-[11px] font-medium"
              :class="healthOf(svc.key).text"
            >
              {{ healthOf(svc.key).label }}
            </p>
          </div>
        </div>

        <!-- Recent system errors -->
        <div class="card p-5">
          <div class="flex items-center justify-between mb-4">
            <h2 class="font-display font-semibold text-slate-800 flex items-center gap-2">
              <AppIcon
                name="blockers"
                :size="15"
                class="text-rose-500"
              />
              Lỗi hệ thống gần đây
            </h2>
            <span class="text-[12px] text-slate-400">{{ recentErrors.length }} bản ghi</span>
          </div>

          <!-- Search -->
          <div class="relative mb-4">
            <AppIcon
              name="search"
              :size="14"
              class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"
            />
            <input
              v-model="errorsSearch"
              type="text"
              placeholder="Tìm kiếm lỗi…"
              class="w-full pl-8 pr-4 py-2 text-[13px] border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-brand/40 focus:border-brand/50"
            >
          </div>

          <div
            v-if="!recentErrors.length"
            class="text-sm text-slate-400 py-6 text-center"
          >
            <AppIcon
              name="done"
              :size="28"
              class="text-emerald-300 mx-auto mb-2"
            />
            Không có lỗi hệ thống ghi nhận.
          </div>
          <div
            v-else-if="!filteredErrors.length"
            class="text-sm text-slate-400 py-4"
          >
            Không khớp từ khoá tìm kiếm.
          </div>
          <ul
            v-else
            class="divide-y divide-slate-100"
          >
            <li
              v-for="err in filteredErrors"
              :key="err.id"
              class="py-3 flex items-start gap-3"
            >
              <span
                class="shrink-0 rounded px-1.5 py-0.5 text-[10px] font-bold uppercase mt-0.5"
                :class="PRIORITY_CONFIG[err.priority]?.cls ?? 'bg-slate-100 text-slate-600'"
              >
                {{ err.priority }}
              </span>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <p class="text-[13px] font-semibold text-slate-800">
                    {{ err.title }}
                  </p>
                  <span class="shrink-0 text-[10px] text-slate-500 bg-slate-50 border border-slate-100 px-1.5 py-0.5 rounded">
                    {{ ERROR_TYPE_LABELS[err.type] ?? err.type }}
                  </span>
                </div>
                <p
                  v-if="err.body"
                  class="text-[11px] text-slate-500 truncate mt-0.5"
                >
                  {{ err.body }}
                </p>
                <p class="text-[11px] text-slate-400 mt-0.5">
                  {{ formatRelative(err.created_at) }}
                </p>
              </div>
              <span
                v-if="!err.is_read"
                class="shrink-0 w-2 h-2 rounded-full bg-brand mt-1.5"
              />
            </li>
          </ul>
        </div>

        <!-- Top actors -->
        <div class="card p-5">
          <h2 class="font-display font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <AppIcon
              name="members"
              :size="15"
              class="text-brand"
            />
            Hoạt động người dùng
            <span class="text-[12px] font-normal text-slate-400">(7 ngày)</span>
          </h2>
          <div
            v-if="!topActors.length"
            class="text-sm text-slate-400 py-4"
          >
            Chưa có hoạt động.
          </div>
          <div
            v-else
            class="space-y-2.5"
          >
            <div
              v-for="actor in topActors"
              :key="actor.actor_name"
              class="flex items-center gap-3"
            >
              <div class="w-7 h-7 rounded-full bg-brand/10 text-brand flex items-center justify-center text-[11px] font-bold shrink-0">
                {{ (actor.actor_name ?? '?')[0].toUpperCase() }}
              </div>
              <span class="flex-1 text-[13px] text-slate-700 truncate">
                {{ actor.actor_name }}
              </span>
              <div class="w-24 bg-slate-100 rounded-full h-1.5 shrink-0">
                <div
                  class="h-1.5 rounded-full bg-brand/70"
                  :style="{ width: `${(actor.count / topActors[0].count) * 100}%` }"
                />
              </div>
              <span class="text-[12px] font-bold text-brand w-8 text-right shrink-0">
                {{ actor.count }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </template>
  </AppLayout>
</template>
