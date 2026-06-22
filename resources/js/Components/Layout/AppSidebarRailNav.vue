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
    showTip: { type: Function, required: true },
    hideTip: { type: Function, required: true },
    openFlyout: { type: Function, required: true },
    scheduleFlyout: { type: Function, required: true },
    closeFlyout: { type: Function, required: true },
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

const onGroupEnter = (group, e) => {
    if (group.items.length <= 1) {
        props.showTip(e, group.heading);
        return;
    }
    props.scheduleFlyout(group, e.currentTarget);
};

const onGroupClick = (group, e) => {
    if (group.items.length <= 1) {
        const only = soleItem(group);
        if (only && !props.isPlanned(only) && only.href !== '#') {
            window.location.href = only.href;
        }
        return;
    }
    props.openFlyout(group, e.currentTarget);
};

const groupHasBadge = (group) => group.items.some((item) => item.badge);
</script>

<template>
  <nav
    :ref="bindNavRef"
    class="sidebar-nav-scroll flex min-h-0 flex-1 flex-col items-center gap-1.5 overflow-y-auto px-2 py-2"
    aria-label="Điều hướng chính"
    @scroll="emit('scroll', $event)"
  >
    <template
      v-for="(group, gi) in nav"
      :key="groupKey(group)"
    >
      <div
        v-if="gi > 0 && sectionStarts[gi]"
        class="my-1.5 h-px w-9 bg-white/20"
        aria-hidden="true"
      />
      <div
        v-else-if="gi > 0"
        class="my-0.5 h-px w-5 bg-white/[0.07]"
        :class="isUpcomingGroup(group) && 'bg-amber-300/30'"
        aria-hidden="true"
      />

      <component
        :is="soleItem(group) && !isPlanned(soleItem(group)) ? Link : 'button'"
        :href="soleItem(group) && !isPlanned(soleItem(group)) ? soleItem(group).href : undefined"
        :type="soleItem(group) && !isPlanned(soleItem(group)) ? undefined : 'button'"
        class="sidebar-rail-group relative grid h-11 w-11 place-items-center rounded-xl transition-all duration-200"
        :class="[
          groupContainsActive(group)
            ? 'sidebar-nav-item--active bg-white/[0.14] text-white shadow-sm shadow-black/10'
            : isUpcomingGroup(group)
              ? 'text-amber-200/80 hover:bg-amber-400/12 hover:text-amber-50'
              : 'text-white/70 hover:bg-white/[0.08] hover:text-white/95',
        ]"
        :aria-label="group.heading"
        :aria-haspopup="group.items.length > 1 ? 'menu' : undefined"
        @mouseenter="onGroupEnter(group, $event)"
        @mouseleave="hideTip(); closeFlyout()"
        @click="onGroupClick(group, $event)"
      >
        <span
          v-if="groupHasBadge(group)"
          class="absolute -right-0.5 -top-0.5 h-2.5 w-2.5 rounded-full bg-rose-500 ring-2 ring-brand"
          aria-hidden="true"
        />
        <AppIcon
          :name="group.icon"
          :size="22"
          :stroke-width="1.65"
          class="sidebar-nav-icon"
        />
      </component>
    </template>
  </nav>
</template>
