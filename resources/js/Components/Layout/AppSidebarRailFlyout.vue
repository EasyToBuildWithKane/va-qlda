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
        class="fixed z-[70] w-[min(17rem,calc(100vw-5rem))]"
        :style="panelStyle"
        role="menu"
        :aria-label="group.heading"
        @mouseenter="emit('pointer-enter')"
        @mouseleave="emit('pointer-leave')"
      >
        <div
          class="overflow-hidden rounded-xl border border-white/10 bg-slate-900/98 shadow-elevation-3 ring-1 ring-black/20 backdrop-blur-md"
        >
          <div
            class="border-b border-white/10 px-3 py-2.5"
            :class="isUpcomingGroup(group) ? 'bg-amber-950/40' : 'bg-white/[0.04]'"
          >
            <p class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">
              {{ group.heading }}
            </p>
          </div>
          <ul class="max-h-[min(18rem,50vh)] overflow-y-auto p-1.5">
            <li
              v-for="item in group.items"
              :key="item.label"
            >
              <component
                :is="isPlanned(item) ? 'div' : Link"
                :href="isPlanned(item) ? undefined : item.href"
                role="menuitem"
                class="flex min-h-[40px] items-center gap-2.5 rounded-lg px-2.5 py-2 text-[13px] transition-colors"
                :class="[
                  isActive(item.href)
                    ? 'sidebar-nav-item--active bg-white/10 font-semibold text-white'
                    : 'text-slate-200 hover:bg-white/[0.08] hover:text-white',
                  isPlanned(item) && 'cursor-not-allowed opacity-60',
                ]"
                @click="!isPlanned(item) && emit('close')"
              >
                <AppIcon
                  :name="item.icon"
                  :size="22"
                  :stroke-width="1.65"
                  class="sidebar-nav-icon shrink-0 opacity-90"
                />
                <span class="min-w-0 flex-1 truncate">{{ item.label }}</span>
                <span
                  v-if="item.badge"
                  class="inline-flex min-w-[1.25rem] shrink-0 items-center justify-center rounded-full bg-rose-500 px-1.5 py-0.5 text-[10px] font-bold leading-none text-white tabular-nums"
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
                  :size="13"
                  class="shrink-0 text-amber-300/80"
                />
              </component>
            </li>
          </ul>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
