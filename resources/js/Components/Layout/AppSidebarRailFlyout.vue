<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    flyout: { type: Object, required: true },
    isActive: { type: Function, required: true },
    isPlanned: { type: Function, required: true },
    isUpcomingGroup: { type: Function, required: true },
    showBadge: { type: Function, required: true },
    statusOf: { type: Function, required: true },
});

const emit = defineEmits(['close', 'pointer-enter', 'pointer-leave']);

const group = computed(() => props.flyout.group);

const panelStyle = computed(() => ({
    top: `${props.flyout.top}px`,
    left: `${props.flyout.left}px`,
}));
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="opacity-0 translate-x-1"
      enter-to-class="opacity-100 translate-x-0"
      leave-active-class="transition duration-100 ease-in"
      leave-from-class="opacity-100 translate-x-0"
      leave-to-class="opacity-0 translate-x-1"
    >
      <div
        v-if="flyout.open && group"
        class="fixed z-[70] w-56"
        :style="panelStyle"
        role="menu"
        :aria-label="group.heading"
        @mouseenter="emit('pointer-enter')"
        @mouseleave="emit('pointer-leave')"
      >
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-elevation-3">
          <div
            class="border-b border-slate-100 px-3 py-2"
            :class="isUpcomingGroup(group) ? 'bg-amber-50' : 'bg-slate-50'"
          >
            <p class="text-[11px] font-bold uppercase leading-[1.2] tracking-[0.08em] text-slate-500">
              {{ group.heading }}
            </p>
          </div>
          <ul class="max-h-[min(18rem,50vh)] space-y-0 overflow-y-auto px-1 pt-1">
            <li
              v-for="item in group.items"
              :key="item.label"
            >
              <component
                :is="isPlanned(item) ? 'div' : Link"
                :href="isPlanned(item) ? undefined : item.href"
                preserve-scroll
                role="menuitem"
                class="flex items-center gap-2 rounded-md px-2 py-1.5 text-[13px] font-semibold leading-[1.5] transition"
                :aria-current="isActive(item.href) ? 'page' : undefined"
                :class="[
                  isActive(item.href)
                    ? 'bg-[#9A0036]/10 font-bold text-[#9A0036]'
                    : 'text-slate-800 hover:bg-slate-50',
                  isPlanned(item) && 'cursor-not-allowed opacity-60',
                ]"
                @click="!isPlanned(item) && emit('close')"
              >
                <AppIcon
                  :name="item.icon"
                  :size="14"
                  :stroke-width="1.75"
                  class="sidebar-nav-icon shrink-0 opacity-90"
                />
                <span class="min-w-0 flex-1 truncate">{{ item.label }}</span>
                <span
                  v-if="item.badge"
                  class="inline-flex min-w-[1.1rem] shrink-0 items-center justify-center rounded bg-slate-100 px-1 py-0.5 text-[11px] font-bold leading-[1.2] text-slate-700 tabular-nums"
                  :aria-label="`${item.badge} mục mới`"
                >{{ item.badge > 99 ? '99+' : item.badge }}</span>
                <span
                  v-else-if="showBadge(item, group)"
                  class="h-1.5 w-1.5 shrink-0 rounded-full"
                  :class="statusOf(item).dot"
                />
                <AppIcon
                  v-else-if="isPlanned(item)"
                  name="clock"
                  :size="12"
                  class="shrink-0 text-amber-500"
                />
              </component>
            </li>
          </ul>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
