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
    groupContainsActive: { type: Function, required: true },
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

function panelId(group) {
    return `sidebar-panel-${props.groupKey(group)}`;
}

/**
 * VA-HRM NavSection header — text-caption (12px) uppercase
 * VA-HRM NavItemLink — text-body-sm (13px) font-medium
 */
const sectionHeadClass =
    'group/row mb-1 flex w-full items-center gap-2 rounded-md px-1.5 py-1 text-left transition duration-200';

const leafClass =
    'sidebar-nav-item sidebar-nav-link group/item relative flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-[13px] leading-[1.5] font-medium text-white/75 transition-all duration-200 ease-out';
</script>

<template>
  <nav
    :ref="bindNavRef"
    class="sidebar-nav-scroll min-h-0 flex-1 space-y-3 overflow-y-auto px-2 py-2.5"
    aria-label="Điều hướng chính"
    @scroll="emit('scroll', $event)"
  >
    <template
      v-for="(group, gi) in nav"
      :key="groupKey(group)"
    >
      <p
        v-if="sectionStarts[gi] && group.section"
        class="select-none px-1.5 pb-1 text-[11px] font-bold uppercase leading-[1.2] tracking-[0.08em] text-white/40"
        :class="gi === 0 ? 'pt-0.5' : 'mt-1 border-t border-white/10 pt-3'"
      >
        {{ group.section }}
      </p>

      <div
        :data-tour="`nav-${group.key}`"
        class="sidebar-section"
      >
        <!-- Nhóm một mục: như NavItemLink (13px) — không uppercase section -->
        <component
          :is="isPlanned(soleItem(group)) ? 'div' : Link"
          v-if="soleItem(group)"
          :href="isPlanned(soleItem(group)) ? undefined : soleItem(group).href"
          preserve-scroll
          :title="isPlanned(soleItem(group)) ? 'Sắp ra mắt — chưa khả dụng' : undefined"
          :aria-current="groupContainsActive(group) ? 'page' : undefined"
          :class="[
            leafClass,
            groupContainsActive(group)
              ? 'sidebar-nav-item--active bg-sidebar-active font-semibold text-white shadow-sm'
              : isUpcomingGroup(group)
                ? 'text-amber-100/90 hover:bg-amber-400/12 hover:text-amber-50'
                : 'hover:bg-white/[0.08] hover:text-white',
            isPlanned(soleItem(group)) && 'cursor-not-allowed opacity-75',
          ]"
        >
          <span
            v-if="groupContainsActive(group)"
            class="absolute inset-y-1 left-0 w-0.5 rounded-r-full bg-white"
            aria-hidden="true"
          />
          <AppIcon
            :name="group.icon"
            :size="16"
            :stroke-width="1.75"
            class="sidebar-nav-icon shrink-0"
          />
          <span class="min-w-0 flex-1 truncate text-left leading-snug">
            {{ group.heading }}
          </span>
        </component>

        <!-- Nhóm nhiều mục: section caption 12px + leaf 13px -->
        <template v-else>
          <button
            type="button"
            :class="[
              sectionHeadClass,
              groupContainsActive(group) ? 'bg-white/[0.05]' : 'hover:bg-white/[0.06]',
              isUpcomingGroup(group) && 'text-amber-100/90',
            ]"
            :aria-expanded="isOpen(group)"
            :aria-controls="panelId(group)"
            @click="toggleGroup(group)"
          >
            <span
              class="flex h-5 w-5 shrink-0 items-center justify-center rounded bg-white/[0.06] text-white/80"
              :class="groupContainsActive(group) && 'bg-white/[0.1] text-white'"
            >
              <AppIcon
                :name="group.icon"
                :size="14"
                :stroke-width="2"
                class="sidebar-nav-icon shrink-0"
              />
            </span>

            <span
              class="min-w-0 flex-1 truncate text-[12px] font-semibold uppercase leading-[1.4] tracking-[0.05em] text-white/90"
              :class="isUpcomingGroup(group) && 'text-amber-100/90'"
            >
              {{ group.heading }}
            </span>

            <span
              v-if="isUpcomingGroup(group)"
              class="shrink-0 rounded-full border border-amber-300/40 bg-amber-400/25 px-1.5 py-0.5 text-[11px] font-bold tabular-nums leading-none text-amber-50"
            >
              {{ group.items.length }}
            </span>

            <AppIcon
              name="chevron-right"
              :size="14"
              :stroke-width="2"
              class="shrink-0 text-white/50 transition-transform duration-200"
              :class="isOpen(group) ? 'rotate-90 text-white/80' : ''"
              aria-hidden="true"
            />
          </button>

          <div
            :id="panelId(group)"
            role="region"
            :aria-label="group.heading"
            class="grid transition-[grid-template-rows] duration-200 ease-out"
            :class="isOpen(group) ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'"
          >
            <div class="overflow-hidden">
              <ul class="ml-2 space-y-px border-l border-white/10 py-0.5 pl-2">
                <li
                  v-for="item in group.items"
                  :key="item.label"
                  class="list-none"
                >
                  <component
                    :is="isPlanned(item) ? 'div' : Link"
                    :href="isPlanned(item) ? undefined : item.href"
                    preserve-scroll
                    :title="isPlanned(item) ? 'Sắp ra mắt — chưa khả dụng' : undefined"
                    :aria-current="isActive(item.href) ? 'page' : undefined"
                    :class="[
                      leafClass,
                      isActive(item.href)
                        ? 'sidebar-nav-item--active bg-sidebar-active font-semibold text-white shadow-sm'
                        : isUpcomingGroup(group)
                          ? 'text-amber-100/70 hover:bg-amber-400/8 hover:text-amber-50'
                          : 'hover:bg-white/[0.08] hover:text-white',
                      isPlanned(item) && 'cursor-not-allowed opacity-75',
                    ]"
                  >
                    <span
                      v-if="isActive(item.href)"
                      class="absolute inset-y-1 left-0 w-0.5 rounded-r-full bg-white"
                      aria-hidden="true"
                    />
                    <AppIcon
                      :name="item.icon"
                      :size="16"
                      :stroke-width="1.75"
                      class="sidebar-nav-icon shrink-0"
                      :class="isActive(item.href) && 'text-white'"
                    />
                    <span class="min-w-0 flex-1 truncate text-left leading-snug">{{ item.label }}</span>

                    <span
                      v-if="item.badge"
                      class="ml-auto inline-flex min-w-[1.1rem] shrink-0 items-center justify-center rounded bg-white/15 px-1 py-0.5 text-[11px] font-bold leading-[1.2] text-white"
                      :aria-label="`${item.badge} mục mới`"
                    >{{ item.badge > 99 ? '99+' : item.badge }}</span>

                    <span
                      v-else-if="showBadge(item, group)"
                      class="ml-auto inline-flex shrink-0 items-center gap-1 rounded-full px-1.5 py-0.5 text-[11px] font-semibold leading-none"
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
                  </component>
                </li>
              </ul>
            </div>
          </div>
        </template>
      </div>
    </template>
  </nav>
</template>
