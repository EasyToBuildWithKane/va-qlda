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
        <!-- Nhóm một mục: đi thẳng -->
        <component
          :is="isPlanned(soleItem(group)) ? 'div' : Link"
          v-if="soleItem(group)"
          :href="isPlanned(soleItem(group)) ? undefined : soleItem(group).href"
          :title="isPlanned(soleItem(group)) ? 'Sắp ra mắt — chưa khả dụng' : undefined"
          :class="[
            rowBase,
            groupContainsActive(group)
              ? 'sidebar-nav-item--active bg-white/[0.14] text-white shadow-sm shadow-black/10'
              : isUpcomingGroup(group)
                ? 'text-[15px] font-semibold text-amber-100/95 hover:bg-amber-400/12 hover:text-amber-50'
                : 'text-[15px] font-semibold text-white/88 hover:bg-white/[0.08] hover:text-white',
            isPlanned(soleItem(group)) && 'cursor-not-allowed opacity-75',
          ]"
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
            v-if="groupContainsActive(group)"
            class="ml-auto h-2 w-2 shrink-0 rounded-full bg-accent"
            aria-hidden="true"
          />
        </component>

        <!-- Nhóm nhiều mục: collapse trong sidebar -->
        <template v-else>
          <button
            type="button"
            :class="[
              rowBase,
              'w-full text-left',
              groupContainsActive(group)
                ? 'sidebar-nav-item--active bg-white/[0.14] text-white shadow-sm shadow-black/10'
                : isUpcomingGroup(group)
                  ? 'text-[15px] font-semibold text-amber-100/95 hover:bg-amber-400/12 hover:text-amber-50'
                  : 'text-[15px] font-semibold text-white/88 hover:bg-white/[0.08] hover:text-white',
            ]"
            :aria-expanded="isOpen(group)"
            @click="toggleGroup(group)"
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

            <span class="sidebar-nav-group-title min-w-0 flex-1 truncate leading-snug">
              {{ group.heading }}
            </span>

            <span
              v-if="isUpcomingGroup(group)"
              class="shrink-0 rounded-full border border-amber-300/40 bg-amber-400/25 px-2 py-0.5 text-[10px] font-bold tabular-nums leading-none text-amber-50"
            >
              {{ group.items.length }}
            </span>

            <span
              class="grid h-7 w-7 shrink-0 place-items-center rounded-md text-white/45 transition-colors group-hover/row:text-white/70"
              :class="isUpcomingGroup(group) && 'text-amber-100/50 group-hover/row:text-amber-50/80'"
              aria-hidden="true"
            >
              <AppIcon
                name="chevron-down"
                :size="14"
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
              <ul class="sidebar-nav-group-items mb-0.5 mt-0.5 space-y-0.5 pl-1">
                <li
                  v-for="item in group.items"
                  :key="item.label"
                >
                  <component
                    :is="isPlanned(item) ? 'div' : Link"
                    :href="isPlanned(item) ? undefined : item.href"
                    :title="isPlanned(item) ? 'Sắp ra mắt — chưa khả dụng' : undefined"
                    class="sidebar-nav-link group/item flex min-h-[38px] items-center gap-2.5 rounded-lg py-1.5 pl-3 pr-2 text-[14px] font-medium leading-snug transition-all duration-200"
                    :class="[
                      isActive(item.href)
                        ? 'sidebar-nav-item--active bg-white/[0.12] text-white'
                        : isUpcomingGroup(group)
                          ? 'text-amber-100/70 hover:bg-amber-400/8 hover:text-amber-50/95'
                          : 'text-white/70 hover:bg-white/[0.06] hover:text-white/95',
                      isPlanned(item) && 'cursor-not-allowed opacity-75',
                    ]"
                  >
                    <span
                      class="sidebar-nav-sub-marker shrink-0 select-none text-base font-semibold leading-none transition-colors"
                      :class="
                        isActive(item.href)
                          ? 'text-accent'
                          : isUpcomingGroup(group)
                            ? 'text-amber-300/55 group-hover/item:text-amber-200/80'
                            : 'text-white/40 group-hover/item:text-white/65'
                      "
                      aria-hidden="true"
                    >–</span>
                    <span class="min-w-0 flex-1 truncate">{{ item.label }}</span>

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
        </template>
      </div>
    </template>
  </nav>
</template>
