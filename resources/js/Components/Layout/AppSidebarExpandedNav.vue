<script setup>
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    registerNavEl: { type: Function, default: () => {} },
    nav: { type: Array, default: () => [] },
    groupKey: { type: Function, required: true },
    isOpen: { type: Function, required: true },
    toggleGroup: { type: Function, required: true },
    isActive: { type: Function, required: true },
    isUpcomingGroup: { type: Function, required: true },
    isPlanned: { type: Function, required: true },
    showBadge: { type: Function, required: true },
    statusOf: { type: Function, required: true },
});

const emit = defineEmits(['scroll']);

function bindNavRef(el) {
    props.registerNavEl(el);
}
</script>

<template>
  <nav
    :ref="bindNavRef"
    class="sidebar-nav-scroll min-h-0 flex-1 overflow-y-auto px-2 py-2 pr-1.5"
    aria-label="Điều hướng chính"
    @scroll="emit('scroll', $event)"
  >
    <div
      v-for="(group, gi) in nav"
      :key="groupKey(group)"
      :data-tour="`nav-${group.key}`"
      :class="[
        gi > 0 ? 'mt-1 border-t border-white/[0.07] pt-1' : '',
        isUpcomingGroup(group) && 'mt-2 border-t border-amber-300/25 pt-2',
      ]"
    >
      <button
        type="button"
        class="group/head flex w-full min-h-8 items-center gap-1.5 rounded-lg px-2 py-1.5 transition-all duration-150 select-none"
        :class="isUpcomingGroup(group)
          ? 'border border-amber-300/25 bg-amber-400/12 text-[10.5px] font-bold uppercase tracking-[0.13em] text-amber-100/95 hover:bg-amber-400/18 hover:text-amber-50'
          : 'text-[10.5px] font-bold uppercase tracking-[0.13em] text-brand-100/75 hover:bg-white/[0.06] hover:text-white'"
        :aria-expanded="isOpen(group)"
        @click="toggleGroup(group)"
      >
        <span
          class="sidebar-nav-icon-shell grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-white/[0.06] group-hover/head:bg-white/[0.1]"
          :class="isUpcomingGroup(group) ? 'ring-1 ring-inset ring-amber-300/20' : ''"
        >
          <AppIcon
            :name="group.icon"
            :size="18"
            class="sidebar-nav-icon shrink-0"
            :class="isUpcomingGroup(group) ? 'text-amber-200/95' : 'text-brand-50/90'"
          />
        </span>
        <span class="min-w-0 flex-1 truncate text-left">{{ group.heading }}</span>
        <span
          v-if="isUpcomingGroup(group)"
          class="shrink-0 rounded-full border border-amber-300/40 bg-amber-400/25 px-1.5 py-0.5 text-[9px] font-bold tabular-nums leading-none text-amber-50"
        >
          {{ group.items.length }}
        </span>
        <span
          class="grid h-5 w-5 shrink-0 place-items-center rounded-md text-brand-100/40 transition-colors group-hover/head:text-brand-100/65"
          :class="isUpcomingGroup(group) && 'text-amber-100/50 group-hover/head:text-amber-50/80'"
          aria-hidden="true"
        >
          <AppIcon
            name="chevron-down"
            :size="12"
            class="transition-transform duration-200 ease-out"
            :class="isOpen(group) ? 'rotate-0' : '-rotate-90'"
          />
        </span>
      </button>

      <div
        class="grid transition-[grid-template-rows] duration-200 ease-out"
        :class="isOpen(group) ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'"
      >
        <div class="min-h-0 overflow-hidden">
          <ul
            class="mb-0.5 mt-0.5 space-y-0.5"
            :class="isUpcomingGroup(group) && 'rounded-lg border border-amber-300/15 bg-amber-950/20 p-0.5'"
          >
            <li
              v-for="item in group.items"
              :key="item.label"
            >
              <component
                :is="isPlanned(item) ? 'div' : Link"
                :href="isPlanned(item) ? undefined : item.href"
                :title="isPlanned(item) ? 'Sắp ra mắt — chưa khả dụng' : undefined"
                class="sidebar-nav-link group/item flex min-h-[44px] items-center gap-2 rounded-lg px-2 py-1.5 text-[13px] font-medium leading-snug transition-all duration-200"
                :class="[
                  isActive(item.href)
                    ? 'sidebar-nav-item--active bg-white/[0.16] font-semibold text-white shadow-sm ring-1 ring-inset ring-white/15'
                    : isUpcomingGroup(group)
                      ? 'text-amber-100/75 hover:bg-amber-400/10 hover:text-amber-50'
                      : 'text-brand-50/90 hover:bg-white/[0.08] hover:text-white',
                  isPlanned(item) && 'cursor-not-allowed',
                ]"
              >
                <span
                  class="sidebar-nav-icon-shell sidebar-nav-icon-shell--item h-9 w-9 shrink-0"
                  :class="isActive(item.href) ? '' : 'group-hover/item:bg-white/[0.08]'"
                >
                  <AppIcon
                    :name="item.icon"
                    :size="22"
                    :stroke-width="1.65"
                    class="sidebar-nav-icon shrink-0"
                    :class="isActive(item.href) ? 'text-white' : 'text-brand-50/85 group-hover/item:text-white'"
                  />
                </span>

                <span class="flex-1 truncate">{{ item.label }}</span>

                <span
                  v-if="item.badge"
                  class="ml-auto inline-flex min-w-[1.25rem] shrink-0 items-center justify-center rounded-full bg-rose-500 px-1.5 py-0.5 text-[10px] font-bold leading-none text-white tabular-nums shadow-sm"
                  :aria-label="`${item.badge} mục mới`"
                >{{ item.badge > 99 ? '99+' : item.badge }}</span>

                <span
                  v-else-if="showBadge(item, group)"
                  class="ml-auto inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold leading-none"
                  :class="statusOf(item).pill"
                >
                  <span
                    class="h-1.5 w-1.5 rounded-full"
                    :class="statusOf(item).dot"
                  />
                  {{ statusOf(item).label }}
                </span>

                <AppIcon
                  v-else-if="isPlanned(item)"
                  name="clock"
                  :size="14"
                  class="ml-auto shrink-0 text-amber-300/70"
                />

                <span
                  v-else-if="isActive(item.href)"
                  class="ml-auto h-1.5 w-1.5 shrink-0 rounded-full bg-accent"
                />
              </component>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </nav>
</template>
