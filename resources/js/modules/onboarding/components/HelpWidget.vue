<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import { ROLE_TOUR, tourTitle } from '@/modules/onboarding/tours';

const STORAGE_KEY = 'va_qlda_help_widget_hidden';

defineProps({
    role: { type: String, default: 'member' },
});

const emit = defineEmits(['replay']);

const open = ref(false);
const hidden = ref(false);
const root = ref(null);

function readHidden() {
    try {
        return localStorage.getItem(STORAGE_KEY) === '1';
    } catch {
        return false;
    }
}

function hideWidget() {
    hidden.value = true;
    open.value = false;
    try {
        localStorage.setItem(STORAGE_KEY, '1');
    } catch {
        /* ignore */
    }
}

function showWidget() {
    hidden.value = false;
    try {
        localStorage.removeItem(STORAGE_KEY);
    } catch {
        /* ignore */
    }
}

function replay(tourKey) {
    open.value = false;
    emit('replay', tourKey);
}

function onDocClick(e) {
    if (open.value && root.value && !root.value.contains(e.target)) {
        open.value = false;
    }
}

let stopNav;
onMounted(() => {
    hidden.value = readHidden();
    document.addEventListener('click', onDocClick);
    stopNav = router.on('start', () => { open.value = false; });
});
onUnmounted(() => {
    document.removeEventListener('click', onDocClick);
    stopNav?.();
});

const links = [
    { icon: 'knowledge', label: 'FAQ & Hướng dẫn sử dụng', href: '/knowledge-base' },
    { icon: 'documents', label: 'Thuật ngữ hệ thống', href: '/knowledge-base' },
    { icon: 'feedback', label: 'Gửi yêu cầu hỗ trợ / Báo lỗi', href: '/feedback' },
];
</script>

<template>
  <!-- Thu gọn: chỉ tab khôi phục -->
  <div
    v-if="hidden"
    class="fixed bottom-5 right-5 z-[56]"
    data-tour="help-widget"
  >
    <button
      type="button"
      class="inline-flex h-9 items-center gap-1.5 rounded-full border border-slate-200/90 bg-white/95 px-3 text-xs font-medium text-slate-600 shadow-elevation-2 backdrop-blur-sm transition-colors hover:border-brand/30 hover:text-brand focus:outline-none focus-visible:ring-4 focus-visible:ring-brand/30"
      aria-label="Hiện lại trợ giúp"
      @click="showWidget"
    >
      <AppIcon
        name="help"
        :size="15"
      />
      Trợ giúp
    </button>
  </div>

  <div
    v-else
    ref="root"
    class="fixed bottom-5 right-5 z-[56] flex flex-col items-end gap-3"
    data-tour="help-widget"
  >
    <!-- Menu -->
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0 translate-y-2 scale-95"
      leave-active-class="transition duration-150 ease-in"
      leave-to-class="opacity-0 translate-y-2 scale-95"
    >
      <div
        v-if="open"
        class="w-72 origin-bottom-right overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-elevation-3"
      >
        <div class="bg-brand px-4 py-3 text-white">
          <p class="text-sm font-semibold">
            Trung tâm trợ giúp
          </p>
          <p class="text-[11.5px] text-white/75">
            Hướng dẫn & hỗ trợ mọi lúc
          </p>
        </div>
        <div class="p-1.5">
          <p class="px-2.5 pb-1 pt-2 text-[10.5px] font-bold uppercase tracking-wide text-slate-400">
            Hướng dẫn tương tác
          </p>
          <button
            type="button"
            class="flex w-full items-center gap-3 rounded-lg px-2.5 py-2 text-left text-sm text-slate-700 transition-colors hover:bg-slate-50"
            @click="replay('system_overview')"
          >
            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-brand/10 text-brand">
              <AppIcon
                name="rocket"
                :size="16"
              />
            </span>
            <span class="flex-1">Xem lại Tour tổng quan</span>
          </button>
          <button
            type="button"
            class="flex w-full items-center gap-3 rounded-lg px-2.5 py-2 text-left text-sm text-slate-700 transition-colors hover:bg-slate-50"
            @click="replay(ROLE_TOUR[role] || 'role_member')"
          >
            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-brand/10 text-brand">
              <AppIcon
                name="target"
                :size="16"
              />
            </span>
            <span class="flex-1">{{ tourTitle(ROLE_TOUR[role] || 'role_member') }}</span>
          </button>

          <p class="px-2.5 pb-1 pt-2 text-[10.5px] font-bold uppercase tracking-wide text-slate-400">
            Tài nguyên
          </p>
          <Link
            v-for="l in links"
            :key="l.label"
            :href="l.href"
            class="flex w-full items-center gap-3 rounded-lg px-2.5 py-2 text-left text-sm text-slate-700 transition-colors hover:bg-slate-50"
            @click="open = false"
          >
            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-slate-100 text-slate-500">
              <AppIcon
                :name="l.icon"
                :size="16"
              />
            </span>
            <span class="flex-1">{{ l.label }}</span>
          </Link>

          <div class="mt-1 border-t border-slate-100 px-1.5 pt-1">
            <button
              type="button"
              class="flex w-full items-center gap-3 rounded-lg px-2.5 py-2 text-left text-sm text-slate-500 transition-colors hover:bg-slate-50 hover:text-slate-700"
              @click="hideWidget"
            >
              <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-slate-100 text-slate-400">
                <AppIcon
                  name="eye-off"
                  :size="16"
                />
              </span>
              <span class="flex-1">Ẩn nút trợ giúp</span>
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- FAB -->
    <button
      type="button"
      class="grid h-14 w-14 place-items-center rounded-full bg-brand text-white shadow-elevation-3 transition-transform hover:scale-105 focus:outline-none focus-visible:ring-4 focus-visible:ring-brand/30"
      :aria-expanded="open"
      aria-label="Trợ giúp"
      @click="open = !open"
    >
      <AppIcon
        :name="open ? 'close' : 'help'"
        :size="22"
      />
    </button>
  </div>
</template>
