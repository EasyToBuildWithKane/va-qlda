<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import AppSidebarBrand from '@/Components/Layout/AppSidebarBrand.vue';
import AppSidebarExpandedNav from '@/Components/Layout/AppSidebarExpandedNav.vue';
import AppSidebarRailNav from '@/Components/Layout/AppSidebarRailNav.vue';
import AppSidebarRailFlyout from '@/Components/Layout/AppSidebarRailFlyout.vue';
import AppSidebarRailTooltip from '@/Components/Layout/AppSidebarRailTooltip.vue';

defineProps({
    rail: { type: Boolean, default: false },
    nav: { type: Array, default: () => [] },
    appShortName: { type: String, default: 'VA' },
    appName: { type: String, default: '' },
    groupKey: { type: Function, required: true },
    isOpen: { type: Function, required: true },
    toggleGroup: { type: Function, required: true },
    isActive: { type: Function, required: true },
    isUpcomingGroup: { type: Function, required: true },
    isPlanned: { type: Function, required: true },
    showBadge: { type: Function, required: true },
    statusOf: { type: Function, required: true },
    showRailStatus: { type: Function, required: true },
    railTone: { type: Function, required: true },
    groupContainsActive: { type: Function, required: true },
    tip: { type: Object, required: true },
    showTip: { type: Function, required: true },
    hideTip: { type: Function, required: true },
    flyout: { type: Object, required: true },
    openFlyout: { type: Function, required: true },
    scheduleFlyout: { type: Function, required: true },
    closeFlyout: { type: Function, required: true },
    onFlyoutPointerLeave: { type: Function, required: true },
    cancelFlyoutClose: { type: Function, required: true },
    registerNavEl: { type: Function, required: true },
    sidebarScrollEdges: { type: Object, required: true },
    onSidebarNavScroll: { type: Function, required: true },
});

const emit = defineEmits(['collapse', 'expand']);
</script>

<template>
  <aside
    class="sidebar-surface hidden h-full min-h-0 shrink-0 flex-col text-white transition-[width] duration-200 ease-out lg:flex"
    :class="rail ? 'w-sidebar-rail' : 'w-sidebar-expanded'"
    :aria-label="rail ? 'Thanh điều hướng thu gọn' : 'Thanh điều hướng'"
  >
    <AppSidebarBrand
      :rail="rail"
      :app-short-name="appShortName"
      :app-name="appName"
      @collapse="emit('collapse')"
      @expand="emit('expand')"
    />

    <button
      v-if="rail"
      type="button"
      class="mx-auto mt-1.5 grid h-9 w-9 place-items-center rounded-lg bg-white/10 text-white ring-1 ring-white/15 transition-colors hover:bg-white/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent/40"
      title="Mở rộng thanh bên"
      aria-label="Mở rộng thanh bên"
      @click="emit('expand')"
    >
      <AppIcon
        name="expand-left"
        :size="17"
      />
    </button>

    <div class="relative flex min-h-0 flex-1 flex-col">
      <div
        v-show="sidebarScrollEdges.top"
        class="pointer-events-none absolute inset-x-0 top-0 z-10 h-7 bg-gradient-to-b from-[var(--color-sidebar)] via-[color-mix(in_srgb,var(--color-sidebar)_90%,transparent)] to-transparent"
        aria-hidden="true"
      />
      <div
        v-show="sidebarScrollEdges.bottom"
        class="pointer-events-none absolute inset-x-0 bottom-0 z-10 h-7 bg-gradient-to-t from-[var(--color-sidebar)] via-[color-mix(in_srgb,var(--color-sidebar)_90%,transparent)] to-transparent"
        aria-hidden="true"
      />

      <AppSidebarRailNav
        v-if="rail"
        :register-nav-el="registerNavEl"
        :nav="nav"
        :group-key="groupKey"
        :is-active="isActive"
        :is-upcoming-group="isUpcomingGroup"
        :is-planned="isPlanned"
        :show-rail-status="showRailStatus"
        :status-of="statusOf"
        :group-contains-active="groupContainsActive"
        :show-tip="showTip"
        :hide-tip="hideTip"
        :rail-tone="railTone"
        :open-flyout="openFlyout"
        :schedule-flyout="scheduleFlyout"
        :on-flyout-pointer-leave="onFlyoutPointerLeave"
        @scroll="onSidebarNavScroll"
      />

      <AppSidebarExpandedNav
        v-else
        :register-nav-el="registerNavEl"
        :nav="nav"
        :group-key="groupKey"
        :is-open="isOpen"
        :toggle-group="toggleGroup"
        :is-active="isActive"
        :is-upcoming-group="isUpcomingGroup"
        :is-planned="isPlanned"
        :show-badge="showBadge"
        :status-of="statusOf"
        :group-contains-active="groupContainsActive"
        @scroll="onSidebarNavScroll"
      />
    </div>

    <AppSidebarRailTooltip
      :show="rail"
      :tip="tip"
    />

    <AppSidebarRailFlyout
      :flyout="flyout"
      :is-active="isActive"
      :is-planned="isPlanned"
      :is-upcoming-group="isUpcomingGroup"
      :show-badge="showBadge"
      :status-of="statusOf"
      @close="closeFlyout"
      @pointer-enter="cancelFlyoutClose"
      @pointer-leave="onFlyoutPointerLeave"
    />
  </aside>
</template>
