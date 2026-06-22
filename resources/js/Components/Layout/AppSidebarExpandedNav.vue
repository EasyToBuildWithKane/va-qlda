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
    openFlyout: { type: Function, required: true },
});

const emit = defineEmits(['scroll']);

const sectionStarts = computed(() =>
    props.nav.map((group, i) => i === 0 || props.nav[i - 1].section !== group.section),
);

function bindNavRef(el) {
    props.registerNavEl(el);
}

function groupBadgeTotal(group) {
    return group.items.reduce((sum, item) => sum + (item.badge ? Number(item.badge) : 0), 0);
}

function soleItem(group) {
    if (group.items.length !== 1) return null;
    return group.items[0];
}

function onGroupClick(group, e) {
    if (group.items.length <= 1) return;
    props.openFlyout(group, e.currentTarget);
}

const rowBase =
    'sidebar-nav-group-row group/row flex w-full min-h-11 items-center gap-2.5 rounded-lg px-2.5 py-2 transition-all duration-200';
</script>

<template>
  <nav
    :ref="bindNavRef"
    class="sidebar-nav-scroll min-h-0 flex-1 overflow-y-auto px-2 py-2 pr-1.5"
    aria-label="Điều hướng chính"
    @scroll="emit('scroll', $event)"
  >
    <template
      v-for="(group, gi) in nav"
      :key="groupKey(group)"
    >
      <p
        v-if="sectionStarts[gi] && group.section"
        class="select-none px-3 pb-2 text-[11px] font-bold uppercase tracking-[0.18em] text-white/50"
        :class="gi === 0 ? 'pt-1' : 'mt-3 border-t border-white/[0.09] pt-3'"
      >
        {{ group.section }}
      </p>

      <div
        :data-tour="`nav-${group.key}`"
        :class="sectionStarts[gi] ? 'mt-1' : 'mt-1.5'"
      >
        <component
          :is="soleItem(group) && !isPlanned(soleItem(group)) ? Link : group.items.length > 1 ? 'button' : 'div'"
          :href="soleItem(group) && !isPlanned(soleItem(group)) ? soleItem(group).href : undefined"
          :type="group.items.length > 1 ? 'button' : undefined"
          :title="soleItem(group) && isPlanned(soleItem(group)) ? 'Sắp ra mắt — chưa khả dụng' : undefined"
          :aria-haspopup="group.items.length > 1 ? 'menu' : undefined"
          :class="[
            rowBase,
            groupContainsActive(group)
              ? 'sidebar-nav-item--active bg-white/[0.14] text-white shadow-sm shadow-black/10'
              : isUpcomingGroup(group)
                ? 'text-[15px] font-semibold text-amber-100/95 hover:bg-amber-400/12 hover:text-amber-50'
                : 'text-[15px] font-semibold text-white/88 hover:bg-white/[0.08] hover:text-white',
            soleItem(group) && isPlanned(soleItem(group)) && 'cursor-not-allowed opacity-75',
          ]"
          @click="onGroupClick(group, $event)"
        >
          <span
            class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-white/[0.06] text-white/85 ring-1 ring-white/10 group-hover/row:bg-white/[0.1] group-hover/row:text-white"
            :class="[
              groupContainsActive(group) && 'bg-white/[0.12] ring-white/20',
              isUpcomingGroup(group) && 'text-amber-100/90 ring-amber-300/25',
            ]"
          >
            <AppIcon
              :name="group.icon"
              :size="20"
              :stroke-width="1.65"
              class="sidebar-nav-icon shrink-0"
            />
          </span>

          <span class="sidebar-nav-group-title min-w-0 flex-1 truncate text-left leading-snug">
            {{ group.heading }}
          </span>

          <span
            v-if="isUpcomingGroup(group)"
            class="shrink-0 rounded-full border border-amber-300/40 bg-amber-400/25 px-2 py-0.5 text-[10px] font-bold tabular-nums leading-none text-amber-50"
          >
            {{ group.items.length }}
          </span>

          <span
            v-else-if="groupBadgeTotal(group) > 0"
            class="ml-auto inline-flex min-w-[1.35rem] shrink-0 items-center justify-center rounded-full bg-rose-500 px-1.5 py-0.5 text-[10px] font-bold leading-none text-white tabular-nums shadow-sm"
            :aria-label="`${groupBadgeTotal(group)} mục mới`"
          >{{ groupBadgeTotal(group) > 99 ? '99+' : groupBadgeTotal(group) }}</span>

          <AppIcon
            v-else-if="group.items.length > 1"
            name="chevron-right"
            :size="14"
            class="shrink-0 text-white/40 group-hover/row:text-white/70"
            aria-hidden="true"
          />

          <span
            v-else-if="groupContainsActive(group)"
            class="ml-auto h-2 w-2 shrink-0 rounded-full bg-accent"
            aria-hidden="true"
          />
        </component>
      </div>
    </template>
  </nav>
</template>
