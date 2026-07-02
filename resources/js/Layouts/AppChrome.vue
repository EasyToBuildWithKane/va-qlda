<script setup>
import { computed, onMounted, onUnmounted, provide } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppSidebar from '@/Components/Layout/AppSidebar.vue';
import AppSidebarMobileDrawer from '@/Components/Layout/AppSidebarMobileDrawer.vue';
import { APP_SIDEBAR_KEY, useAppSidebar } from '@/composables/useAppSidebar';

const page = usePage();
const showSidebar = computed(() => Boolean(page.props.auth?.user));

const sidebar = useAppSidebar();
provide(APP_SIDEBAR_KEY, sidebar);

const {
    nav,
    roleLabel,
    rail,
    mobileOpen,
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
    appShortName,
    appName,
} = sidebar;

function registerSidebarNavEl(el) {
    sidebarNavRef.value = el;
}

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
  <div
    class="flex min-h-0 overflow-hidden bg-slate-50"
    :class="showSidebar ? 'h-screen' : 'min-h-screen'"
  >
    <template v-if="showSidebar">
      <AppSidebar
        data-tour="sidebar"
        :rail="rail"
        :nav="nav"
        :app-short-name="appShortName"
        :app-name="appName"
        :role-label="roleLabel"
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

    <div
      class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden"
      :class="!showSidebar && 'min-h-screen w-full'"
    >
      <slot />
    </div>
  </div>
</template>
