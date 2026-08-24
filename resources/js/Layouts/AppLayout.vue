<script setup>
import { computed, inject, provide, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import UserMenu from '@/modules/project/components/UserMenu.vue';
import NotificationBell from '@/Components/Notifications/NotificationBell.vue';
import QuickBlockerReportBell from '@/Components/Blocker/QuickBlockerReportBell.vue';
import NotificationCenterDrawer from '@/Components/Notifications/NotificationCenterDrawer.vue';
import AppDialog from '@/Components/Ui/AppDialog.vue';
import ToastContainer from '@/Components/Ui/ToastContainer.vue';
import AppSidebar from '@/Components/Layout/AppSidebar.vue';
import AppSidebarMobileDrawer from '@/Components/Layout/AppSidebarMobileDrawer.vue';
import WelcomeScreen from '@/modules/onboarding/components/WelcomeScreen.vue';
import { useToast } from '@/shared/composables/useToast';
import { useNotifications } from '@/composables/useNotifications';
import { APP_SIDEBAR_KEY, useAppSidebarContext } from '@/composables/useAppSidebar';
import { usePage, router } from '@inertiajs/vue3';

const notificationCenter = useNotifications();
provide('notifications', notificationCenter);

const { flush } = defineProps({ flush: Boolean });
const page = usePage();

const toast = useToast();

/**
 * Flash → toast. Xóa key sau khi hiện để lần submit sau với cùng nội dung
 * (vd. «Đã cập nhật…» liên tiếp trên cùng trang) vẫn kích hoạt watch —
 * so sánh !== prev sẽ nuốt toast khi chuỗi flash không đổi.
 */
function consumeFlashToast(key, show) {
    const flash = page.props.flash;
    const message = flash?.[key];
    if (!message) return;
    show(message);
    flash[key] = null;
}

watch(
    () => page.props.flash?.success,
    () => consumeFlashToast('success', toast.success),
    { immediate: true },
);
watch(
    () => page.props.flash?.error,
    () => consumeFlashToast('error', toast.error),
    { immediate: true },
);
watch(
    () => page.props.flash?.warning,
    () => consumeFlashToast('warning', toast.warning),
    { immediate: true },
);

const fromChrome = inject(APP_SIDEBAR_KEY, null);
const sidebar = useAppSidebarContext();

/** Sidebar nằm trên AppChrome; chỉ nhúng lại khi mount AppLayout đơn lẻ (test). */
const renderSidebarHere = computed(() => Boolean(page.props?.auth?.user) && !fromChrome);

const {
    roleLabel,
    mobileOpen,
    openMobile,
    nav,
    appShortName,
    appName,
    groupKey,
    isOpen,
    toggleGroup,
    isActive,
    isUpcomingGroup,
    isPlanned,
    showBadge,
    statusOf,
    showRailStatus,
    railTone,
    tip,
    showTip,
    hideTip,
    flyout,
    openFlyout,
    scheduleFlyout,
    closeFlyout,
    onFlyoutPointerLeave,
    cancelFlyoutClose,
    groupContainsActive,
    sidebarNavRef,
    sidebarScrollEdges,
    onSidebarNavScroll,
    rail,
} = sidebar;

function registerSidebarNavEl(el) {
    sidebarNavRef.value = el;
}

function collapseSidebar() {
    rail.value = true;
}

function expandSidebar() {
    rail.value = false;
}

const user = computed(() => page.props.auth?.user);
const viewAs = computed(() => page.props.viewAs);

function exitViewAs() {
    router.delete('/view-as', { preserveScroll: true });
}

const currentDate = computed(() =>
    new Date().toLocaleDateString('vi-VN', {
        weekday: 'short', day: '2-digit', month: '2-digit', year: 'numeric',
    }),
);
</script>

<template>
  <div
    class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden"
    :class="renderSidebarHere && 'h-screen min-h-0 overflow-hidden bg-slate-50 lg:flex-row'"
  >
    <template v-if="renderSidebarHere">
      <AppSidebar
        data-tour="sidebar"
        :rail="rail"
        :nav="nav"
        :app-short-name="appShortName"
        :app-name="appName"
        :group-key="groupKey"
        :is-open="isOpen"
        :toggle-group="toggleGroup"
        :is-active="isActive"
        :is-upcoming-group="isUpcomingGroup"
        :is-planned="isPlanned"
        :show-badge="showBadge"
        :status-of="statusOf"
        :show-rail-status="showRailStatus"
        :rail-tone="railTone"
        :group-contains-active="groupContainsActive"
        :tip="tip"
        :show-tip="showTip"
        :hide-tip="hideTip"
        :flyout="flyout"
        :open-flyout="openFlyout"
        :schedule-flyout="scheduleFlyout"
        :close-flyout="closeFlyout"
        :on-flyout-pointer-leave="onFlyoutPointerLeave"
        :cancel-flyout-close="cancelFlyoutClose"
        :register-nav-el="registerSidebarNavEl"
        :sidebar-scroll-edges="sidebarScrollEdges"
        :on-sidebar-nav-scroll="onSidebarNavScroll"
        @collapse="collapseSidebar"
        @expand="expandSidebar"
      />

      <AppSidebarMobileDrawer
        :open="mobileOpen"
        :nav="nav"
        :app-short-name="appShortName"
        :app-name="appName"
        :group-key="groupKey"
        :is-open="isOpen"
        :toggle-group="toggleGroup"
        :is-active="isActive"
        :is-upcoming-group="isUpcomingGroup"
        :is-planned="isPlanned"
        :group-contains-active="groupContainsActive"
        :show-badge="showBadge"
        :status-of="statusOf"
        :register-nav-el="registerSidebarNavEl"
        @close="closeMobile"
      />
    </template>

    <div class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden">
      <div
        v-if="viewAs?.active"
        class="flex h-9 shrink-0 items-center justify-center gap-2 bg-amber-400 px-3 text-center text-[12.5px] font-medium text-amber-950"
      >
        <AppIcon
          name="eye"
          :size="14"
          class="shrink-0"
        />
        <span class="truncate">
          Đang xem giao diện như vai trò: <strong>{{ viewAs.label }}</strong> — thao tác vẫn dùng quyền thật của bạn.
        </span>
        <button
          type="button"
          class="ml-1 shrink-0 rounded-md bg-amber-950/10 px-2 py-0.5 font-semibold transition-colors hover:bg-amber-950/20"
          @click="exitViewAs"
        >
          Thoát xem thử
        </button>
      </div>

      <slot name="topbar">
        <header class="h-14 shrink-0 border-b border-slate-200/80 bg-white px-3 shadow-[0_1px_4px_0_rgb(0,0,0,0.04)] sm:px-5">
          <div class="flex h-full items-center gap-3">
            <button
              type="button"
              class="grid h-10 w-10 shrink-0 place-items-center rounded-lg border border-slate-200/80 text-slate-600 transition-colors hover:bg-slate-50 lg:hidden"
              aria-label="Mở menu điều hướng"
              :aria-expanded="mobileOpen"
              @click="openMobile"
            >
              <AppIcon
                name="menu-hamburger"
                :size="20"
              />
            </button>

            <div class="min-w-0 flex-1">
              <slot name="header">
                <div class="flex items-center gap-2.5">
                  <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand/10">
                    <AppIcon
                      name="dashboard"
                      :size="15"
                      class="text-brand"
                    />
                  </div>
                  <div class="min-w-0">
                    <h1 class="truncate text-[15px] font-semibold leading-none text-slate-800">
                      Bảng điều khiển
                    </h1>
                    <p class="mt-0.5 truncate text-[11.5px] leading-none text-slate-400">
                      Tổng quan hệ thống
                    </p>
                  </div>
                </div>
              </slot>
            </div>

            <div class="flex shrink-0 items-center gap-2 sm:gap-2.5">
              <div class="hidden select-none items-center gap-1.5 rounded-lg border border-slate-100/80 bg-slate-50 px-3 py-1.5 text-[11.5px] leading-none text-slate-500 lg:flex">
                <AppIcon
                  name="daily"
                  :size="12"
                  class="shrink-0 text-slate-300"
                />
                <span>{{ currentDate }}</span>
              </div>
              <div class="hidden h-5 w-px bg-slate-200 sm:block" />
              <QuickBlockerReportBell />
              <span data-tour="topbar-notifications">
                <NotificationBell />
              </span>
              <span data-tour="topbar-user">
                <UserMenu
                  :user="user"
                  :role-label="roleLabel"
                  @open-notifications="notificationCenter.openDrawer()"
                />
              </span>
            </div>
          </div>
        </header>
      </slot>

      <main :class="flush ? 'flex min-h-0 w-full min-w-0 flex-1 flex-col overflow-hidden p-0' : 'min-h-0 flex-1 overflow-y-auto p-4 sm:p-6'">
        <slot />
      </main>
    </div>

    <AppDialog />
    <ToastContainer />
    <NotificationCenterDrawer />
    <!-- Always mount when AppLayout is used — AppChrome wraps pages so
         renderSidebarHere is false in production; gating here hid Welcome. -->
    <WelcomeScreen />
  </div>
</template>
