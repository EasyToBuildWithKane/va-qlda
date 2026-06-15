<script setup>
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    registerNavEl: { type: Function, default: () => {} },
    nav: { type: Array, default: () => [] },
    groupKey: { type: Function, required: true },
    isActive: { type: Function, required: true },
    isUpcomingGroup: { type: Function, required: true },
    isPlanned: { type: Function, required: true },
    showRailStatus: { type: Function, required: true },
    statusOf: { type: Function, required: true },
    groupContainsActive: { type: Function, required: true },
    showTip: { type: Function, required: true },
    hideTip: { type: Function, required: true },
    railTone: { type: Function, required: true },
    openFlyout: { type: Function, required: true },
    scheduleFlyout: { type: Function, required: true },
    closeFlyout: { type: Function, required: true },
});

const emit = defineEmits(['scroll']);

function bindNavRef(el) {
    props.registerNavEl(el);
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
        const only = group.items[0];
        if (only && !props.isPlanned(only) && only.href !== '#') {
            window.location.href = only.href;
        }
        return;
    }
    props.openFlyout(group, e.currentTarget);
};

const itemTipSub = (item) => {
    if (props.isPlanned(item)) return 'Sắp ra mắt';
    if (props.showRailStatus(item)) return props.statusOf(item).label;
    return '';
};

const groupHasBadge = (group) => group.items.some((item) => item.badge);
</script>

<template>
  <nav
    :ref="bindNavRef"
    class="sidebar-nav-scroll flex min-h-0 flex-1 flex-col items-center gap-0.5 overflow-y-auto px-2 py-2"
    aria-label="Điều hướng chính"
    @scroll="emit('scroll', $event)"
  >
    <template
      v-for="(group, gi) in nav"
      :key="groupKey(group)"
    >
      <div
        v-if="gi > 0"
        class="my-1 h-px w-8"
        :class="isUpcomingGroup(group) ? 'bg-amber-300/30' : 'bg-white/[0.1]'"
        aria-hidden="true"
      />

      <button
        v-if="group.items.length > 1"
        type="button"
        class="sidebar-rail-group relative mb-0.5 grid h-8 w-8 place-items-center rounded-lg transition-colors"
        :class="[
          groupContainsActive(group)
            ? 'bg-white/[0.12] text-white ring-1 ring-inset ring-white/15'
            : isUpcomingGroup(group)
              ? 'text-amber-200/70 hover:bg-amber-400/15 hover:text-amber-50'
              : 'text-brand-100/40 hover:bg-white/[0.08] hover:text-brand-100/80',
        ]"
        :aria-label="group.heading"
        :aria-haspopup="true"
        @mouseenter="onGroupEnter(group, $event)"
        @mouseleave="hideTip(); closeFlyout()"
        @click="onGroupClick(group, $event)"
      >
        <span
          v-if="groupContainsActive(group)"
          class="absolute -left-1 top-1/2 h-4 w-0.5 -translate-y-1/2 rounded-r-full bg-accent"
          aria-hidden="true"
        />
        <span
          v-if="groupHasBadge(group)"
          class="absolute -right-0.5 -top-0.5 h-2 w-2 rounded-full bg-rose-500 ring-2 ring-brand"
          aria-hidden="true"
        />
        <AppIcon
          :name="group.icon"
          :size="14"
        />
      </button>

      <component
        :is="isPlanned(item) ? 'div' : Link"
        v-for="item in group.items"
        :key="item.label"
        :href="isPlanned(item) ? undefined : item.href"
        class="sidebar-rail-item relative grid h-10 w-10 place-items-center rounded-xl transition-all duration-150"
        :class="[
          isActive(item.href)
            ? 'sidebar-nav-item--active bg-white/[0.14] text-white shadow-sm ring-1 ring-inset ring-white/12'
            : 'text-brand-100/65 hover:bg-white/[0.08] hover:text-white',
          isPlanned(item) && 'cursor-not-allowed opacity-70 hover:bg-amber-400/10 hover:text-amber-100/80',
        ]"
        @mouseenter="showTip($event, item.label, itemTipSub(item), railTone(item))"
        @mouseleave="hideTip"
        @click="closeFlyout()"
      >
        <span
          v-if="isActive(item.href)"
          class="absolute -left-0.5 top-1/2 h-5 w-1 -translate-y-1/2 rounded-r-full bg-accent"
          aria-hidden="true"
        />
        <AppIcon
          :name="item.icon"
          :size="20"
        />
        <span
          v-if="item.badge"
          class="absolute -right-1 -top-1 inline-flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-rose-500 px-1 text-[9px] font-bold leading-none text-white ring-2 ring-brand tabular-nums"
          :aria-label="`${item.badge} mục mới`"
        >{{ item.badge > 9 ? '9+' : item.badge }}</span>
        <span
          v-else-if="isPlanned(item)"
          class="absolute -right-0.5 -top-0.5 grid h-3.5 w-3.5 place-items-center rounded-full bg-amber-400/90 ring-[1.5px] ring-brand"
        >
          <AppIcon
            name="clock"
            :size="8"
            class="text-brand"
          />
        </span>
        <span
          v-else-if="showRailStatus(item)"
          class="absolute right-0.5 top-0.5 h-2 w-2 rounded-full ring-2 ring-brand"
          :class="statusOf(item).dot"
        />
      </component>
    </template>
  </nav>
</template>
