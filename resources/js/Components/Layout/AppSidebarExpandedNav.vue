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
    class="sidebar-nav-scroll min-h-0 flex-1 overflow-y-auto px-3 py-3 pr-2"
    aria-label="Điều hướng chính"
    @scroll="emit('scroll', $event)"
  >
    <div
      v-for="(group, gi) in nav"
      :key="groupKey(group)"
      :data-tour="`nav-${group.key}`"
      :class="[
        gi > 0 ? 'mt-1.5 border-t border-white/[0.07] pt-1.5' : '',
        isUpcomingGroup(group) && 'mt-3 border-t border-amber-300/25 pt-3',
      ]"
    >
      <button
        type="button"
        class="group/head flex w-full min-h-9 items-center gap-2 rounded-lg px-2.5 py-2 transition-all duration-150 select-none"
        :class="isUpcomingGroup(group)
          ? 'border border-amber-300/25 bg-amber-400/12 text-[10px] font-bold uppercase tracking-[0.14em] text-amber-100/90 hover:bg-amber-400/18 hover:text-amber-50'
          : 'text-[10px] font-bold uppercase tracking-[0.14em] text-brand-100/50 hover:bg-white/[0.04] hover:text-brand-100/75'"
        :aria-expanded="isOpen(group)"
        @click="toggleGroup(group)"
      >
        <AppIcon
          :name="group.icon"
          :size="14"
          class="shrink-0 transition-opacity"
          :class="isUpcomingGroup(group) ? 'text-amber-200/90' : 'opacity-55 group-hover/head:opacity-80'"
        />
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
            :class="isUpcomingGroup(group) && 'rounded-lg border border-amber-300/15 bg-amber-950/20 p-1'"
          >
            <li
              v-for="item in group.items"
              :key="item.label"
            >
              <component
                :is="isPlanned(item) ? 'div' : Link"
                :href="isPlanned(item) ? undefined : item.href"
                :title="isPlanned(item) ? 'Sắp ra mắt — chưa khả dụng' : undefined"
                class="group/item flex min-h-[40px] items-center gap-2.5 rounded-lg px-2.5 py-2 text-[14px] leading-snug transition-all duration-150"
                :class="[
                  isActive(item.href)
                    ? 'sidebar-nav-item--active bg-white/[0.14] font-semibold text-white shadow-sm ring-1 ring-inset ring-white/10'
                    : isUpcomingGroup(group)
                      ? 'text-amber-100/65 hover:bg-amber-400/10 hover:text-amber-50'
                      : 'text-brand-100/80 hover:bg-white/[0.06] hover:text-white',
                  isPlanned(item) && 'cursor-not-allowed',
                ]"
              >
                <span
                  class="grid h-8 w-8 shrink-0 place-items-center rounded-lg transition-colors"
                  :class="isActive(item.href) ? 'bg-white/10' : 'bg-transparent group-hover/item:bg-white/[0.06]'"
                >
                  <AppIcon
                    :name="item.icon"
                    :size="18"
                    class="shrink-0 transition-opacity"
                    :class="isActive(item.href) ? 'opacity-100' : 'opacity-55 group-hover/item:opacity-85'"
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
