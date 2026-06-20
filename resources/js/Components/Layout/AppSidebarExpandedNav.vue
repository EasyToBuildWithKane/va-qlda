<script setup>
import { computed } from 'vue';
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

// A group starts a new super-section when its section label differs from the
// previous (post-RBAC-filter) group's — that's where we render a section title.
const sectionStarts = computed(() =>
    props.nav.map((group, i) => i === 0 || props.nav[i - 1].section !== group.section),
);

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
    <template
      v-for="(group, gi) in nav"
      :key="groupKey(group)"
    >
      <!-- Tier-1 super-section title — clear functional grouping -->
      <p
        v-if="sectionStarts[gi] && group.section"
        class="select-none px-3 pb-1.5 text-[10px] font-bold uppercase tracking-[0.2em] text-white/45"
        :class="gi === 0 ? 'pt-1' : 'mt-3 border-t border-white/[0.09] pt-3'"
      >
        {{ group.section }}
      </p>

      <div
        :data-tour="`nav-${group.key}`"
        :class="sectionStarts[gi] ? '' : 'mt-0.5'"
      >
        <button
          type="button"
          class="sidebar-nav-group-head group/head flex w-full min-h-7 items-center gap-1.5 rounded-md px-2 py-1 transition-all duration-150 select-none"
          :class="isUpcomingGroup(group)
            ? 'text-[12px] font-semibold text-amber-100/90 hover:bg-amber-400/10 hover:text-amber-50'
            : 'text-[12px] font-semibold text-white/80 hover:bg-white/[0.06] hover:text-white'"
          :aria-expanded="isOpen(group)"
          @click="toggleGroup(group)"
        >
          <span
            class="grid h-6 w-6 shrink-0 place-items-center rounded-md text-white/70 group-hover/head:text-white/90"
            :class="isUpcomingGroup(group) && 'text-amber-200/80 group-hover/head:text-amber-100'"
          >
            <AppIcon
              :name="group.icon"
              :size="14"
              class="sidebar-nav-icon shrink-0"
            />
          </span>
          <span class="sidebar-nav-group-title min-w-0 flex-1 truncate text-left">{{ group.heading }}</span>
          <span
            v-if="isUpcomingGroup(group)"
            class="shrink-0 rounded-full border border-amber-300/40 bg-amber-400/25 px-1.5 py-0.5 text-[9px] font-bold tabular-nums leading-none text-amber-50"
          >
            {{ group.items.length }}
          </span>
          <span
            class="grid h-5 w-5 shrink-0 place-items-center rounded-md text-white/45 transition-colors group-hover/head:text-white/70"
            :class="isUpcomingGroup(group) && 'text-amber-100/50 group-hover/head:text-amber-50/80'"
            aria-hidden="true"
          >
            <AppIcon
              name="chevron-down"
              :size="11"
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
              class="sidebar-nav-group-items mb-0.5 mt-0.5 space-y-0.5"
            >
              <li
                v-for="item in group.items"
                :key="item.label"
              >
                <component
                  :is="isPlanned(item) ? 'div' : Link"
                  :href="isPlanned(item) ? undefined : item.href"
                  :title="isPlanned(item) ? 'Sắp ra mắt — chưa khả dụng' : undefined"
                  class="sidebar-nav-link group/item flex min-h-[38px] items-center gap-2 rounded-lg px-2 py-1 text-[13px] font-normal leading-snug transition-all duration-200"
                  :class="[
                    isActive(item.href)
                      ? 'sidebar-nav-item--active bg-white/[0.12] font-medium text-white'
                      : isUpcomingGroup(group)
                        ? 'text-amber-100/65 hover:bg-amber-400/8 hover:text-amber-50/95'
                        : 'text-white/60 hover:bg-white/[0.06] hover:text-white/90',
                    isPlanned(item) && 'cursor-not-allowed',
                  ]"
                >
                  <span
                    class="sidebar-nav-icon-shell sidebar-nav-icon-shell--item h-7 w-7 shrink-0 rounded-lg"
                    :class="isActive(item.href) ? '' : 'group-hover/item:bg-white/[0.06]'"
                  >
                    <AppIcon
                      :name="item.icon"
                      :size="18"
                      :stroke-width="1.65"
                      class="sidebar-nav-icon shrink-0"
                      :class="isActive(item.href) ? 'text-white' : 'text-white/55 group-hover/item:text-white/90'"
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
                    :size="12"
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
    </template>
  </nav>
</template>
