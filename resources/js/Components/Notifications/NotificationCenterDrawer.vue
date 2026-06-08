<script setup>
import { computed, inject, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Drawer from '@/Components/Ui/Drawer.vue';
import NotificationItem from '@/Components/Notifications/NotificationItem.vue';
import NotificationSettingsPanel from '@/Components/Notifications/NotificationSettingsPanel.vue';
import { groupNotificationsByDate, NOTIFICATION_TABS } from '@/composables/notificationMeta';

const notifications = inject('notifications');
const bulkMode = ref(false);

const grouped = computed(() => groupNotificationsByDate(notifications.items.value));

const canGoPrev = computed(() => notifications.currentPage.value > 1);
const canGoNext = computed(() => {
    const m = notifications.listMeta.value;
    if (!m?.total) return false;
    return (m.current_page ?? 1) < (m.last_page ?? 1);
});

watch(
    () => notifications.filters.value.tab,
    () => notifications.applyFilters(),
);

function toggleSelect(id) {
    const set = new Set(notifications.selectedIds.value);
    if (set.has(id)) set.delete(id);
    else set.add(id);
    notifications.selectedIds.value = [...set];
}

const iconBtn =
    'grid h-7 w-7 shrink-0 place-items-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-200';
</script>

<template>
  <Drawer
    :show="notifications.drawerOpen.value"
    flush
    width="max-w-md"
    @close="notifications.closeDrawer()"
  >
    <div
      v-if="!notifications.settingsOpen.value"
      class="flex h-full min-h-0 flex-col"
    >
      <!-- Header: compact, sticky -->
      <header class="shrink-0 border-b border-slate-200/80 bg-white px-3 py-2 dark:border-slate-800 dark:bg-slate-900">
        <div class="flex items-center gap-2">
          <div class="grid h-7 w-7 shrink-0 place-items-center rounded-md bg-brand/10">
            <AppIcon
              name="notifications"
              :size="15"
              class="text-brand"
            />
          </div>
          <h2 class="min-w-0 flex-1 truncate text-[13px] font-semibold text-slate-800 dark:text-slate-100">
            Thông báo
          </h2>
          <span
            v-if="notifications.unreadCount.value > 0"
            class="shrink-0 rounded-full bg-brand px-1.5 py-px text-[10px] font-bold leading-tight text-white"
          >
            {{ notifications.badgeLabel.value }}
          </span>
          <button
            type="button"
            :class="[iconBtn, bulkMode ? 'bg-slate-100 text-slate-700 dark:bg-slate-800' : '']"
            title="Chọn nhiều"
            @click="bulkMode = !bulkMode"
          >
            <AppIcon
              name="action-items"
              :size="14"
            />
          </button>
          <button
            type="button"
            :class="iconBtn"
            title="Cài đặt"
            @click="notifications.settingsOpen.value = true"
          >
            <AppIcon
              name="system-config"
              :size="14"
            />
          </button>
          <Link
            v-if="notifications.isAdmin.value"
            :href="route('notifications.manage')"
            :class="iconBtn"
            title="Quản lý"
            @click="notifications.closeDrawer()"
          >
            <AppIcon
              name="overview"
              :size="14"
            />
          </Link>
          <button
            type="button"
            :class="iconBtn"
            aria-label="Đóng"
            @click="notifications.closeDrawer()"
          >
            <AppIcon
              name="close"
              :size="16"
            />
          </button>
        </div>

        <!-- Tabs + actions — one row -->
        <div class="mt-2 flex items-center gap-1.5">
          <div
            class="flex flex-1 min-w-0 rounded-md bg-slate-100 p-0.5 dark:bg-slate-800"
            role="tablist"
          >
            <button
              v-for="tab in NOTIFICATION_TABS"
              :key="tab.id"
              type="button"
              role="tab"
              class="flex-1 truncate rounded px-1 py-1 text-[11px] font-medium transition-colors"
              :class="
                notifications.filters.value.tab === tab.id
                  ? 'bg-white text-slate-800 shadow-sm dark:bg-slate-700 dark:text-white'
                  : 'text-slate-500 hover:text-slate-700 dark:text-slate-400'
              "
              :aria-selected="notifications.filters.value.tab === tab.id"
              @click="notifications.filters.value.tab = tab.id"
            >
              {{ tab.label }}
            </button>
          </div>
          <button
            type="button"
            class="shrink-0 text-[11px] font-medium text-brand hover:text-brand/80 disabled:opacity-40"
            :disabled="notifications.unreadCount.value === 0"
            @click="notifications.markAllRead()"
          >
            Đọc hết
          </button>
        </div>

        <div
          v-if="bulkMode && notifications.selectedIds.value.length"
          class="mt-1.5 flex items-center justify-between gap-2 rounded-md bg-brand/5 px-2 py-1 dark:bg-brand/10"
        >
          <span class="text-[11px] text-slate-600 dark:text-slate-300">
            Đã chọn {{ notifications.selectedIds.value.length }}
          </span>
          <button
            type="button"
            class="text-[11px] font-medium text-brand"
            @click="notifications.bulkRead()"
          >
            Đánh dấu đã đọc
          </button>
        </div>
      </header>

      <!-- List -->
      <div class="min-h-0 flex-1 overflow-y-auto px-3 py-2">
        <div
          v-if="notifications.loading.value"
          class="py-8 text-center text-[12px] text-slate-400"
        >
          Đang tải…
        </div>

        <div
          v-else-if="!notifications.items.value.length"
          class="py-10 text-center"
        >
          <AppIcon
            name="done"
            :size="28"
            class="mx-auto text-slate-300"
          />
          <p class="mt-2 text-[12px] font-medium text-slate-600 dark:text-slate-300">
            Không có thông báo
          </p>
        </div>

        <template v-else>
          <section
            v-for="group in grouped"
            :key="group.label"
            class="mb-3 last:mb-0"
          >
            <h3
              class="sticky top-0 z-10 -mx-3 mb-1 bg-slate-50/95 px-3 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-400 backdrop-blur dark:bg-slate-900/95"
            >
              {{ group.label }}
            </h3>
            <div class="space-y-1">
              <NotificationItem
                v-for="n in group.items"
                :key="n.id"
                :notification="n"
                :selectable="bulkMode"
                :selected="notifications.selectedIds.value.includes(n.id)"
                @click="notifications.navigate(n)"
                @acknowledge="notifications.acknowledge(n)"
                @toggle-select="toggleSelect"
              />
            </div>
          </section>
        </template>
      </div>

      <footer
        v-if="!notifications.loading.value && notifications.items.value.length"
        class="shrink-0 border-t border-slate-200/80 bg-white px-3 py-2 dark:border-slate-800 dark:bg-slate-900"
      >
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
          <p class="text-[10px] text-slate-500 dark:text-slate-400">
            {{ notifications.paginationRangeLabel.value }}
          </p>
          <div class="flex flex-wrap items-center justify-end gap-2">
            <label class="inline-flex items-center gap-1.5 text-[10px] text-slate-600 dark:text-slate-300">
              <span class="shrink-0">Hiển thị</span>
              <select
                :value="notifications.perPage.value"
                class="h-7 min-w-[3rem] rounded-md border border-slate-200 bg-white px-1.5 text-[11px] font-medium text-slate-800 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                aria-label="Số thông báo mỗi trang"
                @change="notifications.setPerPage(Number($event.target.value))"
              >
                <option
                  v-for="n in notifications.PER_PAGE_OPTIONS"
                  :key="n"
                  :value="n"
                >
                  {{ n }}
                </option>
              </select>
            </label>
            <nav
              class="inline-flex items-center gap-0.5 rounded-md border border-slate-200 bg-white p-0.5 dark:border-slate-700 dark:bg-slate-800"
              aria-label="Phân trang thông báo"
            >
              <button
                type="button"
                class="inline-flex h-7 min-w-[2rem] items-center justify-center rounded px-1.5 text-[11px] font-medium text-slate-600 hover:bg-slate-100 disabled:opacity-40 dark:text-slate-300 dark:hover:bg-slate-700"
                :disabled="!canGoPrev"
                aria-label="Trang trước"
                @click="notifications.goToPage(notifications.currentPage.value - 1)"
              >
                ‹
              </button>
              <span class="px-1.5 text-[10px] tabular-nums text-slate-500 dark:text-slate-400">
                {{ notifications.listMeta.value?.current_page ?? 1 }}
                /
                {{ notifications.listMeta.value?.last_page ?? 1 }}
              </span>
              <button
                type="button"
                class="inline-flex h-7 min-w-[2rem] items-center justify-center rounded px-1.5 text-[11px] font-medium text-slate-600 hover:bg-slate-100 disabled:opacity-40 dark:text-slate-300 dark:hover:bg-slate-700"
                :disabled="!canGoNext"
                aria-label="Trang sau"
                @click="notifications.goToPage(notifications.currentPage.value + 1)"
              >
                ›
              </button>
            </nav>
          </div>
        </div>
      </footer>
    </div>

    <div
      v-else
      class="flex h-full min-h-0 flex-col px-3 py-2"
    >
      <div class="mb-2 flex items-center gap-2 border-b border-slate-100 pb-2 dark:border-slate-800">
        <button
          type="button"
          class="text-[12px] font-medium text-brand"
          @click="notifications.settingsOpen.value = false"
        >
          ← Quay lại
        </button>
        <span class="text-[13px] font-semibold text-slate-800 dark:text-slate-100">Cài đặt</span>
      </div>
      <div class="min-h-0 flex-1 overflow-y-auto">
        <NotificationSettingsPanel @close="notifications.settingsOpen.value = false" />
      </div>
    </div>
  </Drawer>
</template>
