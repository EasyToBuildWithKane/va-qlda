<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const portal = computed(() => page.props.portal ?? { canEnterQlda: false, qldaHome: '/dashboard' });

const links = [
    { href: '#gioi-thieu', label: 'Giới thiệu' },
    { href: '#thanh-tuu', label: 'Thành tựu' },
    { href: '#san-pham', label: 'Sản phẩm' },
    { href: '#to-chuc', label: 'Tổ chức' },
    { href: '#du-an', label: 'Dự án' },
    { href: '#lo-trinh', label: 'Lộ trình' },
];

const scrolled = ref(false);
const open = ref(false);

function onScroll() {
    scrolled.value = window.scrollY > 24;
}

onMounted(() => {
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
});
onUnmounted(() => window.removeEventListener('scroll', onScroll));
</script>

<template>
  <header
    class="fixed inset-x-0 top-0 z-50 transition-all duration-300"
    :class="scrolled ? 'border-b border-white/10 bg-[#0b0b12]/85 backdrop-blur-xl' : 'border-b border-transparent'"
  >
    <nav class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-5 sm:px-8">
      <a
        href="#top"
        class="flex items-center gap-2.5"
      >
        <span class="grid h-9 w-9 place-items-center rounded-xl bg-gradient-to-br from-brand to-[#ff4d8d] text-sm font-bold text-white shadow-lg shadow-brand/30">
          CN
        </span>
        <span class="hidden flex-col leading-none sm:flex">
          <span class="font-display text-sm font-bold text-white">Phòng Công Nghệ</span>
          <span class="text-[10.5px] tracking-wide text-white/45">Vietnam America Schools</span>
        </span>
      </a>

      <div class="hidden items-center gap-1 lg:flex">
        <a
          v-for="link in links"
          :key="link.href"
          :href="link.href"
          class="rounded-full px-3.5 py-2 text-[13px] font-medium text-white/65 transition-colors hover:bg-white/5 hover:text-white"
        >{{ link.label }}</a>
      </div>

      <div class="flex items-center gap-2">
        <a
          v-if="portal.canEnterQlda"
          :href="portal.qldaHome"
          class="hidden rounded-full bg-gradient-to-r from-brand to-[#d4145a] px-4 py-2 text-[13px] font-semibold text-white shadow-lg shadow-brand/30 transition hover:shadow-brand/50 sm:inline-flex"
        >
          Vào hệ thống
        </a>
        <button
          type="button"
          class="grid h-10 w-10 place-items-center rounded-lg text-white/80 hover:bg-white/5 lg:hidden"
          aria-label="Mở menu"
          @click="open = !open"
        >
          <svg
            width="22"
            height="22"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
          >
            <path :d="open ? 'M6 6l12 12M6 18L18 6' : 'M4 7h16M4 12h16M4 17h16'" />
          </svg>
        </button>
      </div>
    </nav>

    <transition
      enter-active-class="transition duration-200"
      enter-from-class="-translate-y-2 opacity-0"
      leave-active-class="transition duration-150"
      leave-to-class="-translate-y-2 opacity-0"
    >
      <div
        v-if="open"
        class="border-t border-white/10 bg-[#0b0b12]/95 px-5 py-4 backdrop-blur-xl lg:hidden"
      >
        <div class="grid gap-1">
          <a
            v-for="link in links"
            :key="link.href"
            :href="link.href"
            class="rounded-lg px-3 py-2.5 text-sm font-medium text-white/75 hover:bg-white/5 hover:text-white"
            @click="open = false"
          >{{ link.label }}</a>
          <a
            v-if="portal.canEnterQlda"
            :href="portal.qldaHome"
            class="mt-1 rounded-lg bg-gradient-to-r from-brand to-[#d4145a] px-3 py-2.5 text-center text-sm font-semibold text-white"
          >
            Vào hệ thống
          </a>
        </div>
      </div>
    </transition>
  </header>
</template>
