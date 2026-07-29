<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    registerNavEl: { type: Function, default: () => {} },
    nav: { type: Array, default: () => [] },
    groupKey: { type: Function, required: true },
    isActive: { type: Function, required: true },
    isUpcomingGroup: { type: Function, required: true },
    isPlanned: { type: Function, required: true },
    groupContainsActive: { type: Function, required: true },
    showRailStatus: { type: Function, required: true },
    statusOf: { type: Function, required: true },
    railTone: { type: Function, required: true },
    showTip: { type: Function, required: true },
    hideTip: { type: Function, required: true },
    openFlyout: { type: Function, required: true },
    scheduleFlyout: { type: Function, required: true },
    onFlyoutPointerLeave: { type: Function, required: true },
});

const emit = defineEmits(['scroll']);

const sectionStarts = computed(() =>
    props.nav.map((group, i) => i === 0 || props.nav[i - 1].section !== group.section),
);

function bindNavRef(el) {
    props.registerNavEl(el);
}

function soleItem(group) {
    if (group.items.length !== 1) return null;
    return group.items[0];
}

const tipForSole = (group, e) => {
    const item = soleItem(group);
    if (!item) {
        props.showTip(e, group.heading);
        return;
    }
    const sub = props.showRailStatus(item) ? props.statusOf(item).label : '';
    const tone = props.railTone(item);
    props.showTip(e, group.heading, sub, tone);
};

const onGroupEnter = (group, e) => {
    if (group.items.length <= 1) {
        tipForSole(group, e);
        return;
    }
    props.showTip(e, group.heading, `${group.items.length} mục`);
    props.scheduleFlyout(group, e.currentTarget);
};

const onGroupLeave = () => {
    props.hideTip();
    props.onFlyoutPointerLeave();
};

const onGroupClick = (group, e) => {
    if (group.items.length <= 1) {
        return;
    }
    props.openFlyout(group, e.currentTarget);
};

const groupHasBadge = (group) => group.items.some((item) => item.badge);
</script>

<template>
  <nav
    :ref="bindNavRef"
    class="sidebar-nav-scroll flex min-h-0 flex-1 flex-col items-center gap-1 overflow-y-auto px-1.5 py-2"
    aria-label="Điều hướng chính"
    @scroll="emit('scroll', $event)"
  >
    <template
      v-for="(group, gi) in nav"
      :key="groupKey(group)"
    >
      <div
        v-if="gi > 0 && sectionStarts[gi]"
        class="my-1 h-px w-7 bg-white/20"
        aria-hidden="true"
      />
      <div
        v-else-if="gi > 0"
        class="my-0.5 h-px w-4 bg-white/[0.08]"
        :class="isUpcomingGroup(group) && 'bg-amber-300/30'"
        aria-hidden="true"
      />

      <component
        :is="soleItem(group) && !isPlanned(soleItem(group)) ? Link : 'button'"
        :href="soleItem(group) && !isPlanned(soleItem(group)) ? soleItem(group).href : undefined"
        :type="soleItem(group) && !isPlanned(soleItem(group)) ? undefined : 'button'"
        preserve-scroll
        class="sidebar-nav-item sidebar-rail-group relative grid h-10 w-10 place-items-center rounded-lg"
        :class="[
          groupContainsActive(group)
            ? 'sidebar-nav-item--active bg-sidebar-active text-white'
            : isUpcomingGroup(group)
              ? 'text-amber-200/80 hover:bg-amber-400/12 hover:text-amber-50'
              : 'text-white/70 hover:bg-white/[0.08] hover:text-white',
        ]"
        :aria-label="group.heading"
        :aria-current="groupContainsActive(group) && soleItem(group) ? 'page' : undefined"
        :aria-haspopup="group.items.length > 1 ? 'menu' : undefined"
        @mouseenter="onGroupEnter(group, $event)"
        @mouseleave="onGroupLeave"
        @click="onGroupClick(group, $event)"
      >
        <span
          v-if="groupHasBadge(group)"
          class="absolute -right-0.5 -top-0.5 h-2 w-2 rounded-full bg-rose-500 ring-2 ring-[var(--color-sidebar)]"
          aria-hidden="true"
        />
        <span
          v-else-if="soleItem(group) && showRailStatus(soleItem(group))"
          class="absolute -right-0.5 -top-0.5 h-1.5 w-1.5 rounded-full ring-2 ring-[var(--color-sidebar)]"
          :class="statusOf(soleItem(group)).dot"
          aria-hidden="true"
        />
        <AppIcon
          :name="group.icon"
          :size="18"
          :stroke-width="1.75"
          class="sidebar-nav-icon"
        />
      </component>
    </template>
  </nav>
</template>
