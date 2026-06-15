<script setup>
import { computed, onMounted, onUnmounted, provide, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import UserMenu from '@/modules/project/components/UserMenu.vue';
import NotificationBell from '@/Components/Notifications/NotificationBell.vue';
import QuickBlockerReportBell from '@/Components/Blocker/QuickBlockerReportBell.vue';
import NotificationCenterDrawer from '@/Components/Notifications/NotificationCenterDrawer.vue';
import AppDialog from '@/Components/Ui/AppDialog.vue';
import ToastContainer from '@/Components/Ui/ToastContainer.vue';
import AppSidebar from '@/Components/Layout/AppSidebar.vue';
import AppSidebarMobileDrawer from '@/Components/Layout/AppSidebarMobileDrawer.vue';
import { useToast } from '@/shared/composables/useToast';
import { useNotifications } from '@/composables/useNotifications';
import { useAppSidebar } from '@/composables/useAppSidebar';
import { usePage } from '@inertiajs/vue3';

const notificationCenter = useNotifications();
provide('notifications', notificationCenter);

const { flush } = defineProps({ flush: Boolean });
const page = usePage();

const toast = useToast();
watch(
    () => page.props.flash?.success,
    (success, prevSuccess) => {
        if (success && success !== prevSuccess) {
            toast.success(success);
        }
    },
);
watch(
    () => page.props.flash?.error,
    (error, prevError) => {
        if (error && error !== prevError) {
            toast.error(error);
        }
    },
);

const sidebar = useAppSidebar();
const {
    nav,
    user,
    appShortName,
    appName,
    appVersion,
    roleLabel,
    rail,
    mobileOpen,
    openMobile,
    closeMobile,
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
    legend,
    userInitials,
    userAvatarSrc,
    userDisplayName,
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
} = sidebar;

function registerSidebarNavEl(el) {
    sidebarNavRef.value = el;
}

const currentDate = computed(() =>
    new Date().toLocaleDateString('vi-VN', {
        weekday: 'short', day: '2-digit', month: '2-digit', year: 'numeric',
    }),
);

function onDocumentClick(e) {
    if (!rail.value || !flyout.open) return;
    const t = e.target;
    if (t.closest?.('.sidebar-rail-item') || t.closest?.('.sidebar-rail-group') || t.closest?.('[role="menu"]')) return;
    closeFlyout();
}

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
});

onUnmounted(() => {
    document.removeEventListener('click', onDocumentClick);
});
</script>

<template>
  <div class="flex h-screen min-h-0 overflow-hidden bg-slate-50">
    <AppSidebar
      :rail="rail"
      :nav="nav"
      :app-short-name="appShortName"
      :app-name="appName"
      :app-version="appVersion"
      :user="user"
      :role-label="roleLabel"
      :legend="legend"
      :user-initials="userInitials"
      :user-avatar-src="userAvatarSrc"
      :user-display-name="userDisplayName"
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
      @collapse="rail = true"
      @expand="rail = false"
    />

    <AppSidebarMobileDrawer
      :open="mobileOpen"
      :nav="nav"
      :app-short-name="appShortName"
      :app-name="appName"
      :app-version="appVersion"
      :user="user"
      :role-label="roleLabel"
      :legend="legend"
      :user-initials="userInitials"
      :user-avatar-src="userAvatarSrc"
      :user-display-name="userDisplayName"
      :group-key="groupKey"
      :is-open="isOpen"
      :toggle-group="toggleGroup"
      :is-active="isActive"
      :is-upcoming-group="isUpcomingGroup"
      :is-planned="isPlanned"
      :show-badge="showBadge"
      :status-of="statusOf"
      :register-nav-el="registerSidebarNavEl"
      @close="closeMobile"
    />

    <div class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden">
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
              <NotificationBell />
              <UserMenu
                :user="user"
                :role-label="roleLabel"
                @open-notifications="notificationCenter.openDrawer()"
              />
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
  </div>
</template>
