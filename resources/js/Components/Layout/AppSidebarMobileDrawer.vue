<script setup>
import { onMounted, onUnmounted, ref, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import AppSidebarBrand from '@/Components/Layout/AppSidebarBrand.vue';
import AppSidebarExpandedNav from '@/Components/Layout/AppSidebarExpandedNav.vue';
import AppSidebarFooter from '@/Components/Layout/AppSidebarFooter.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    nav: { type: Array, default: () => [] },
    appShortName: { type: String, default: 'VA' },
    appName: { type: String, default: '' },
    appVersion: { type: String, default: '1.0' },
    user: { type: Object, default: null },
    roleLabel: { type: String, default: '' },
    legend: { type: Array, default: () => [] },
    userInitials: { type: String, default: 'ND' },
    userAvatarSrc: { type: String, default: null },
    userDisplayName: { type: String, default: '' },
    groupKey: { type: Function, required: true },
    isOpen: { type: Function, required: true },
    toggleGroup: { type: Function, required: true },
    isActive: { type: Function, required: true },
    isUpcomingGroup: { type: Function, required: true },
    isPlanned: { type: Function, required: true },
    showBadge: { type: Function, required: true },
    statusOf: { type: Function, required: true },
    registerNavEl: { type: Function, default: () => {} },
});

const emit = defineEmits(['close']);

const panelRef = ref(null);
const dragOffset = ref(0);
let touchStartX = 0;
let touchStartY = 0;
let dragging = false;

const onTouchStart = (e) => {
    if (!props.open) return;
    touchStartX = e.touches[0].clientX;
    touchStartY = e.touches[0].clientY;
    dragging = true;
    dragOffset.value = 0;
};

const onTouchMove = (e) => {
    if (!dragging || !props.open) return;
    const dx = e.touches[0].clientX - touchStartX;
    const dy = Math.abs(e.touches[0].clientY - touchStartY);
    if (dy > 24 && Math.abs(dx) < 12) {
        dragging = false;
        return;
    }
    if (dx < 0) dragOffset.value = dx;
};

const onTouchEnd = () => {
    if (!dragging) return;
    dragging = false;
    if (dragOffset.value < -72) emit('close');
    dragOffset.value = 0;
};

const panelStyle = () => ({
    transform: dragOffset.value ? `translateX(${dragOffset.value}px)` : undefined,
});

watch(
    () => props.open,
    (v) => {
        if (typeof document === 'undefined') return;
        document.body.style.overflow = v ? 'hidden' : '';
    },
);

function onKeydown(e) {
    if (e.key === 'Escape' && props.open) emit('close');
}

onMounted(() => {
    window.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', onKeydown);
    if (typeof document !== 'undefined') document.body.style.overflow = '';
});
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      leave-active-class="transition duration-150 ease-in"
      leave-to-class="opacity-0"
    >
      <div
        v-if="open"
        class="fixed inset-0 z-[80] lg:hidden"
        role="presentation"
      >
        <button
          type="button"
          class="absolute inset-0 bg-slate-900/50 backdrop-blur-[2px]"
          aria-label="Đóng menu"
          @click="emit('close')"
        />

        <Transition
          appear
          enter-active-class="transition duration-200 ease-out"
          enter-from-class="-translate-x-full"
          leave-active-class="transition duration-150 ease-in"
          leave-to-class="-translate-x-full"
        >
          <aside
            v-if="open"
            ref="panelRef"
            class="absolute left-0 top-0 flex h-full w-[min(20rem,calc(100vw-3rem))] max-w-full flex-col bg-brand text-brand-100 shadow-elevation-3"
            role="dialog"
            aria-modal="true"
            aria-label="Menu điều hướng"
            :style="panelStyle()"
            @touchstart.passive="onTouchStart"
            @touchmove.passive="onTouchMove"
            @touchend="onTouchEnd"
            @touchcancel="onTouchEnd"
          >
            <div class="flex items-center justify-between border-b border-white/[0.08] pl-1 pr-2">
              <AppSidebarBrand
                :rail="false"
                hide-toggle
                :app-short-name="appShortName"
                :app-name="appName"
                class="flex-1 border-0"
              />
              <button
                type="button"
                class="grid h-10 w-10 shrink-0 place-items-center rounded-lg text-brand-100/70 hover:bg-white/10 hover:text-white"
                aria-label="Đóng menu"
                @click="emit('close')"
              >
                <AppIcon
                  name="close"
                  :size="20"
                />
              </button>
            </div>

            <div class="relative min-h-0 flex-1 overflow-hidden">
              <AppSidebarExpandedNav
                :register-nav-el="registerNavEl"
                :nav="nav"
                :group-key="groupKey"
                :is-open="isOpen"
                :toggle-group="toggleGroup"
                :is-active="isActive"
                :is-upcoming-group="isUpcomingGroup"
                :is-planned="isPlanned"
                :show-badge="showBadge"
                :status-of="statusOf"
              />
            </div>

            <AppSidebarFooter
              :rail="false"
              :legend="legend"
              :user="user"
              :role-label="roleLabel"
              :user-initials="userInitials"
              :user-avatar-src="userAvatarSrc"
              :user-display-name="userDisplayName"
              :app-name="appName"
              :app-version="appVersion"
            />
          </aside>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
