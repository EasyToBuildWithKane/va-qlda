<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import NotificationItem from '@/Components/Notifications/NotificationItem.vue';
import { NOTIFICATION_TABS, groupNotificationsByDate } from '@/composables/notificationMeta';
import { httpGet, httpPost } from '@/shared/services/http';

const props = defineProps({
    stats: { type: Object, required: true },
    options: { type: Object, required: true },
});

const PER_PAGE = 20;

// ─── State ──────────────────────────────────────────────────────────────────
const items = ref([]);
const loading = ref(false);
const meta = ref(null);
const page = ref(1);
const unreadCount = ref(props.stats.unread ?? 0);
const selectedIds = ref([]);

const filters = reactive({
    tab: 'all',
    category: '',
    priority: '',
    actor_account_id: '',
    search: '',
    from: '',
    to: '',
});

const stats = reactive({ ...props.stats });

// ─── Fetch ──────────────────────────────────────────────────────────────────
async function fetchList() {
    loading.value = true;
    try {
        const params = {
            tab: filters.tab,
            per_page: PER_PAGE,
            page: page.value,
        };
        if (filters.category) params.category = filters.category;
        if (filters.priority) params.priority = filters.priority;
        if (filters.actor_account_id) params.actor_account_id = filters.actor_account_id;
        if (filters.search) params.search = filters.search;
        if (filters.from) params.from = filters.from;
        if (filters.to) params.to = filters.to;

        const data = await httpGet(route('notifications.list'), { params });
        items.value = data.data ?? [];
        meta.value = data.meta ?? null;
        selectedIds.value = [];
    } finally {
        loading.value = false;
    }
}

let searchTimer = null;
watch(
    () => ({ ...filters }),
    () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            page.value = 1;
            fetchList();
        }, 300);
    },
    { deep: true },
);

onMounted(fetchList);

// ─── Derived ────────────────────────────────────────────────────────────────
const grouped = computed(() => groupNotificationsByDate(items.value));
const isEmpty = computed(() => !loading.value && items.value.length === 0);

const statCards = computed(() => [
    { key: 'total', label: 'Tổng thông báo', value: stats.total, icon: 'notifications', color: 'text-brand bg-brand/10' },
    { key: 'unread', label: 'Chưa đọc', value: unreadCount.value, icon: 'message', color: 'text-sky-600 bg-sky-100' },
    { key: 'critical', label: 'Quan trọng chưa đọc', value: stats.critical, icon: 'flag', color: 'text-rose-600 bg-rose-100' },
    { key: 'today', label: 'Hôm nay', value: stats.today, icon: 'calendar', color: 'text-violet-600 bg-violet-100' },
]);

const hasFilters = computed(() =>
    !!(filters.category || filters.priority || filters.actor_account_id || filters.search || filters.from || filters.to),
);

// ─── Actions ────────────────────────────────────────────────────────────────
async function onClick(notification) {
    if (!notification.is_read) await markRead(notification);
    if (notification.action_url) {
        router.visit(notification.action_url, { preserveScroll: true });
    }
}

async function markRead(notification) {
    try {
        const data = await httpPost(route('notifications.read', notification.id));
        const idx = items.value.findIndex((n) => n.id === notification.id);
        if (idx >= 0) items.value[idx] = data.notification;
        unreadCount.value = data.unread_count ?? unreadCount.value;
    } catch {
        /* */
    }
}

async function acknowledge(notification) {
    try {
        await httpPost(route('notifications.acknowledge', notification.id));
        await fetchList();
    } catch {
        /* */
    }
}

async function markAllRead() {
    try {
        await httpPost(route('notifications.read-all'));
        unreadCount.value = 0;
        await fetchList();
    } catch {
        /* */
    }
}

function toggleSelect(id) {
    const i = selectedIds.value.indexOf(id);
    if (i >= 0) selectedIds.value.splice(i, 1);
    else selectedIds.value.push(id);
}

async function bulkRead() {
    if (!selectedIds.value.length) return;
    try {
        const data = await httpPost(route('notifications.bulk'), {
            ids: selectedIds.value,
            action: 'read',
        });
        unreadCount.value = data.unread_count ?? unreadCount.value;
        await fetchList();
    } catch {
        /* */
    }
}

function resetFilters() {
    filters.category = '';
    filters.priority = '';
    filters.actor_account_id = '';
    filters.search = '';
    filters.from = '';
    filters.to = '';
}

function goToPage(p) {
    if (!meta.value || p < 1 || p > meta.value.last_page || p === page.value) return;
    page.value = p;
    fetchList();
}
</script>

<template>
  <Head title="Thông báo" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Thông báo"
        subtitle="Hộp thư cá nhân — mọi cập nhật liên quan tới bạn"
        icon="notifications"
        :badge="unreadCount > 0 ? unreadCount : null"
      >
        <button
          type="button"
          class="shrink-0 inline-flex items-center gap-1.5 rounded-lg bg-brand px-3 py-1.5 text-[12px] font-medium text-white hover:bg-brand/90 disabled:opacity-40"
          :disabled="unreadCount === 0"
          @click="markAllRead"
        >
          <AppIcon
            name="check"
            :size="14"
          />
          Đọc tất cả
        </button>
      </PageHeader>
    </template>

    <div class="mx-auto max-w-5xl px-4 py-5 space-y-5">
      <!-- Stat cards -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div
          v-for="c in statCards"
          :key="c.key"
          class="rounded-xl border border-slate-200/70 bg-white p-4 flex items-center gap-3"
        >
          <div
            class="h-10 w-10 rounded-lg flex items-center justify-center shrink-0"
            :class="c.color"
          >
            <AppIcon
              :name="c.icon"
              :size="18"
            />
          </div>
          <div class="min-w-0">
            <p class="text-xl font-semibold text-slate-800 leading-none">
              {{ c.value }}
            </p>
            <p class="text-[12px] text-slate-400 mt-1 truncate">
              {{ c.label }}
            </p>
          </div>
        </div>
      </div>

      <!-- Tabs + filters -->
      <div class="rounded-xl border border-slate-200/70 bg-white p-3 space-y-3">
        <div class="flex items-center gap-2 flex-wrap">
          <div class="inline-flex rounded-lg bg-slate-100 p-0.5">
            <button
              v-for="t in NOTIFICATION_TABS"
              :key="t.id"
              type="button"
              class="rounded-md px-3 py-1.5 text-[12px] font-medium transition-colors"
              :class="filters.tab === t.id ? 'bg-white text-brand shadow-sm' : 'text-slate-500 hover:text-slate-700'"
              @click="filters.tab = t.id"
            >
              {{ t.label }}
            </button>
          </div>
          <div class="relative flex-1 min-w-[180px]">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
              <AppIcon
                name="search"
                :size="15"
              />
            </span>
            <input
              v-model="filters.search"
              type="text"
              placeholder="Tìm thông báo…"
              class="w-full rounded-lg border border-slate-200 pl-9 pr-3 py-2 text-[13px] focus:border-brand focus:ring-1 focus:ring-brand"
            >
          </div>
          <button
            v-if="selectedIds.length"
            type="button"
            class="shrink-0 inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-2 text-[12px] text-slate-600 hover:bg-slate-50"
            @click="bulkRead"
          >
            <AppIcon
              name="check"
              :size="14"
            />
            Đọc ({{ selectedIds.length }})
          </button>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
          <select
            v-model="filters.category"
            class="rounded-lg border border-slate-200 px-2 py-1.5 text-[12px] focus:border-brand focus:ring-1 focus:ring-brand"
          >
            <option value="">
              Mọi loại
            </option>
            <option
              v-for="c in options.categories"
              :key="c.value"
              :value="c.value"
            >
              {{ c.label }}
            </option>
          </select>
          <select
            v-model="filters.priority"
            class="rounded-lg border border-slate-200 px-2 py-1.5 text-[12px] focus:border-brand focus:ring-1 focus:ring-brand"
          >
            <option value="">
              Mọi mức
            </option>
            <option
              v-for="p in options.priorities"
              :key="p.value"
              :value="p.value"
            >
              {{ p.label }}
            </option>
          </select>
          <select
            v-model="filters.actor_account_id"
            class="rounded-lg border border-slate-200 px-2 py-1.5 text-[12px] focus:border-brand focus:ring-1 focus:ring-brand"
          >
            <option value="">
              Mọi người
            </option>
            <option
              v-for="a in options.actors"
              :key="a.id"
              :value="a.id"
            >
              {{ a.display_name }}
            </option>
          </select>
          <input
            v-model="filters.from"
            type="date"
            class="rounded-lg border border-slate-200 px-2 py-1.5 text-[12px] focus:border-brand focus:ring-1 focus:ring-brand"
          >
          <input
            v-model="filters.to"
            type="date"
            class="rounded-lg border border-slate-200 px-2 py-1.5 text-[12px] focus:border-brand focus:ring-1 focus:ring-brand"
          >
        </div>
        <div
          v-if="hasFilters"
          class="flex justify-end"
        >
          <button
            type="button"
            class="inline-flex items-center gap-1 text-[12px] text-slate-400 hover:text-brand"
            @click="resetFilters"
          >
            <AppIcon
              name="close"
              :size="13"
            />
            Xóa bộ lọc
          </button>
        </div>
      </div>

      <!-- List -->
      <div class="rounded-xl border border-slate-200/70 bg-white p-2 min-h-[200px]">
        <div
          v-if="loading"
          class="py-16 text-center text-[13px] text-slate-400"
        >
          Đang tải…
        </div>
        <div
          v-else-if="isEmpty"
          class="py-16 text-center text-slate-400 text-[13px]"
        >
          <AppIcon
            name="notifications"
            :size="28"
            class="mx-auto mb-2 opacity-40"
          />
          Không có thông báo.
        </div>
        <div
          v-else
          class="space-y-4"
        >
          <div
            v-for="group in grouped"
            :key="group.label"
          >
            <p class="px-2 py-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">
              {{ group.label }}
            </p>
            <div class="space-y-1">
              <NotificationItem
                v-for="n in group.items"
                :key="n.id"
                :notification="n"
                selectable
                :selected="selectedIds.includes(n.id)"
                @click="onClick"
                @acknowledge="acknowledge"
                @toggle-select="toggleSelect"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div
        v-if="meta && meta.last_page > 1"
        class="flex items-center justify-between text-[12px] text-slate-500"
      >
        <span>{{ meta.from }}–{{ meta.to }} trong {{ meta.total }}</span>
        <div class="flex items-center gap-1">
          <button
            class="rounded-lg border border-slate-200 px-2.5 py-1.5 disabled:opacity-40 hover:bg-slate-50"
            :disabled="meta.current_page <= 1"
            @click="goToPage(meta.current_page - 1)"
          >
            Trước
          </button>
          <span class="px-2">{{ meta.current_page }} / {{ meta.last_page }}</span>
          <button
            class="rounded-lg border border-slate-200 px-2.5 py-1.5 disabled:opacity-40 hover:bg-slate-50"
            :disabled="meta.current_page >= meta.last_page"
            @click="goToPage(meta.current_page + 1)"
          >
            Sau
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
