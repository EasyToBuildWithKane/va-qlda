<script setup>
import {
    computed, onMounted, onBeforeUnmount, reactive, ref, watch,
} from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import NotificationItem from '@/Components/Notifications/NotificationItem.vue';
import NotificationInboxSummaryBar from '@/modules/notifications/components/NotificationInboxSummaryBar.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import DatagridToolbarActionButton from '@/shared/ui/DatagridToolbarActionButton.vue';
import DatagridSegmentedControl from '@/shared/ui/DatagridSegmentedControl.vue';
import DatagridFilterField from '@/shared/ui/DatagridFilterField.vue';
import FilterVisibilityDropdown from '@/shared/ui/FilterVisibilityDropdown.vue';
import FilterDatePicker from '@/shared/ui/FilterDatePicker.vue';
import DatagridPaginationFooter from '@/shared/ui/DatagridPaginationFooter.vue';
import { NOTIFICATION_TABS, groupNotificationsByDate } from '@/composables/notificationMeta';
import { buildClientPaginationLinks } from '@/shared/composables/useClientPagination';
import { useVisibleFilterControls } from '@/shared/composables/useVisibleFilterControls';
import { httpGet, httpPost } from '@/shared/services/http';

const props = defineProps({
    stats: { type: Object, required: true },
    options: { type: Object, required: true },
});

const PER_PAGE = 20;

const TAB_ITEMS = NOTIFICATION_TABS.map((t) => ({
    key: t.id,
    label: t.label,
    icon: t.id === 'unread' ? 'message' : t.id === 'read' ? 'check' : 'notifications',
}));

const FILTER_CONTROLS = [
    { key: 'category', label: 'Loại', default: false },
    { key: 'priority', label: 'Mức ưu tiên', default: false },
    { key: 'actor_account_id', label: 'Người gửi', default: false },
    { key: 'date_range', label: 'Thời gian', default: false },
];

const FILTER_CONTROL_CLASS = 'input h-10 w-full text-sm';

const items = ref([]);
const loading = ref(false);
const meta = ref(null);
const page = ref(1);
const unreadCount = ref(props.stats.unread ?? 0);
const selectedIds = ref([]);
const stats = reactive({ ...props.stats });

const filters = reactive({
    tab: 'all',
    category: '',
    priority: '',
    actor_account_id: '',
    search: '',
    from: '',
    to: '',
});

const filterPanelDdRef = ref(null);

const {
    visibleFilters,
    showFilterPanelDd,
    enabledFilterControlCount,
    hasFilterRow,
    persistVisibleFilters,
    openFilterPanel,
    FILTER_CONTROLS: filterControlDefs,
} = useVisibleFilterControls(FILTER_CONTROLS, 'va-qlda.notifications-inbox.visible-filters.v1');

function openFilterPanelSafe() {
    openFilterPanel();
}

function onToolbarClickOutside(e) {
    if (e.target.closest?.('[data-filter-visibility-panel]')) return;
    if (filterPanelDdRef.value && !filterPanelDdRef.value.contains(e.target)) {
        showFilterPanelDd.value = false;
    }
}

onMounted(() => {
    document.addEventListener('mousedown', onToolbarClickOutside);
    fetchList();
});

onBeforeUnmount(() => document.removeEventListener('mousedown', onToolbarClickOutside));

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
        }, 350);
    },
    { deep: true },
);

function goToPage(p) {
    if (!meta.value || p < 1 || p > meta.value.last_page || p === page.value) return;
    page.value = p;
    fetchList();
}

const grouped = computed(() => groupNotificationsByDate(items.value));
const isEmpty = computed(() => !loading.value && items.value.length === 0);

const todayIso = new Date().toISOString().slice(0, 10);

const activeKpi = computed(() => {
    if (filters.from === todayIso && filters.to === todayIso && !filters.category && !filters.actor_account_id) {
        return 'today';
    }
    if (filters.priority === 'critical' && filters.tab === 'unread') return 'critical';
    if (filters.tab === 'unread' && !filters.priority && !filters.category) return 'unread';
    if (filters.tab === 'all' && !filters.category && !filters.priority && !filters.search && !filters.from) {
        return 'total';
    }
    return '';
});

const paginationMeta = computed(() => {
    if (!meta.value) return null;
    return {
        ...meta.value,
        links: buildClientPaginationLinks(meta.value.current_page, meta.value.last_page),
    };
});

function onQuickFilter({ kpi }) {
    filters.category = '';
    filters.actor_account_id = '';
    filters.search = '';
    filters.from = '';
    filters.to = '';
    filters.priority = '';

    if (kpi === 'unread') {
        filters.tab = 'unread';
    } else if (kpi === 'critical') {
        filters.tab = 'unread';
        filters.priority = 'critical';
    } else if (kpi === 'today') {
        filters.tab = 'all';
        filters.from = todayIso;
        filters.to = todayIso;
    } else {
        filters.tab = 'all';
    }
}

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
          class="btn-primary inline-flex h-9 shrink-0 items-center gap-1.5 px-3 text-xs disabled:opacity-40"
          :disabled="unreadCount === 0"
          @click="markAllRead"
        >
          <AppIcon
            name="check"
            :size="15"
          />
          Đọc tất cả
        </button>
      </PageHeader>
    </template>

    <NotificationInboxSummaryBar
      :stats="stats"
      :unread-count="unreadCount"
      :active-kpi="activeKpi"
      @quick-filter="onQuickFilter"
    />

    <div class="card overflow-visible">
      <div class="relative z-20 border-b border-slate-100 px-5 py-4">
        <div class="flex w-full min-w-0 flex-wrap items-center gap-2 lg:flex-nowrap">
          <div class="min-w-0 w-full basis-full lg:flex-1 lg:basis-auto">
            <DatagridToolbarSearch
              v-model="filters.search"
              input-id="notifications-inbox-search"
              hide-label
              stretch
              inline-actions
              input-height="h-10"
              placeholder="Tìm thông báo…"
              aria-label="Tìm thông báo"
            />
          </div>
          <div class="flex shrink-0 flex-wrap items-center gap-2">
            <div
              ref="filterPanelDdRef"
              class="relative shrink-0"
            >
              <DatagridToolbarActionButton
                icon="filter"
                :active="showFilterPanelDd"
                :title="`Hiển thị bộ lọc (${enabledFilterControlCount}/${filterControlDefs.length})`"
                @click="openFilterPanelSafe"
              >
                Lọc
              </DatagridToolbarActionButton>
              <FilterVisibilityDropdown
                v-model="visibleFilters"
                :show="showFilterPanelDd"
                :anchor-ref="filterPanelDdRef"
                :controls="filterControlDefs"
                input-id-prefix="notifications-inbox-filter-vis"
                @persist="persistVisibleFilters"
              />
            </div>
            <DatagridToolbarActionButton
              v-if="selectedIds.length"
              icon="check"
              @click="bulkRead"
            >
              Đọc ({{ selectedIds.length }})
            </DatagridToolbarActionButton>
          </div>
          <div class="ml-auto flex shrink-0 items-center gap-2">
            <DatagridSegmentedControl
              v-model="filters.tab"
              :items="TAB_ITEMS"
              aria-label="Lọc trạng thái đọc"
              icon-only-below-sm
            />
          </div>
        </div>

        <Transition name="fade-slide">
          <div
            v-if="hasFilterRow"
            class="mt-3 grid grid-cols-1 gap-3 border-t border-slate-100 pt-4 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6"
          >
            <DatagridFilterField v-if="visibleFilters.category">
              <select
                v-model="filters.category"
                :class="FILTER_CONTROL_CLASS"
                aria-label="Loại"
              >
                <option value="">
                  Loại
                </option>
                <option
                  v-for="c in options.categories"
                  :key="c.value"
                  :value="c.value"
                >
                  {{ c.label }}
                </option>
              </select>
            </DatagridFilterField>

            <DatagridFilterField v-if="visibleFilters.priority">
              <select
                v-model="filters.priority"
                :class="FILTER_CONTROL_CLASS"
                aria-label="Mức ưu tiên"
              >
                <option value="">
                  Mức ưu tiên
                </option>
                <option
                  v-for="p in options.priorities"
                  :key="p.value"
                  :value="p.value"
                >
                  {{ p.label }}
                </option>
              </select>
            </DatagridFilterField>

            <DatagridFilterField v-if="visibleFilters.actor_account_id">
              <select
                v-model="filters.actor_account_id"
                :class="FILTER_CONTROL_CLASS"
                aria-label="Người gửi"
              >
                <option value="">
                  Người gửi
                </option>
                <option
                  v-for="a in options.actors"
                  :key="a.id"
                  :value="a.id"
                >
                  {{ a.display_name }}
                </option>
              </select>
            </DatagridFilterField>

            <DatagridFilterField
              v-if="visibleFilters.date_range"
              class="sm:col-span-2 xl:col-span-2"
            >
              <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                <FilterDatePicker
                  v-model="filters.from"
                  placeholder="Từ ngày"
                />
                <FilterDatePicker
                  v-model="filters.to"
                  placeholder="Đến ngày"
                />
              </div>
            </DatagridFilterField>
          </div>
        </Transition>
      </div>

      <div class="min-h-[200px] p-2">
        <div
          v-if="loading"
          class="py-16 text-center text-[13px] text-slate-400"
        >
          Đang tải…
        </div>
        <div
          v-else-if="isEmpty"
          class="py-16 text-center text-[13px] text-slate-400"
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

      <DatagridPaginationFooter
        v-if="paginationMeta && paginationMeta.total > 0"
        variant="bar"
        client
        :meta="paginationMeta"
        :per-page="PER_PAGE"
        :per-page-options="[10, 20, 30, 50]"
        @page-change="goToPage"
      />
    </div>
  </AppLayout>
</template>

<style scoped>
.fade-slide-enter-active,
.fade-slide-leave-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}
.fade-slide-enter-from,
.fade-slide-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>
