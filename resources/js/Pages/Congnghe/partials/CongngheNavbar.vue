<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import MagneticButton from './MagneticButton.vue';
import { congngheBrand } from './congngheBrand.js';

const page = usePage();
const portal = computed(() => page.props.portal ?? { canEnterQlda: false, qldaHome: '/dashboard' });

const links = [
    { href: '#gioi-thieu', id: 'gioi-thieu', label: 'Giới thiệu' },
    { href: '#thanh-tuu', id: 'thanh-tuu', label: 'Thành tựu' },
    { href: '#san-pham', id: 'san-pham', label: 'Sản phẩm' },
    { href: '#to-chuc', id: 'to-chuc', label: 'Tổ chức' },
    { href: '#du-an', id: 'du-an', label: 'Dự án' },
    { href: '#lo-trinh', id: 'lo-trinh', label: 'Lộ trình' },
];

const scrolled = ref(false);
const open = ref(false);
const activeId = ref('');

function onScroll() {
    scrolled.value = window.scrollY > 24;
}

let spy = null;

onMounted(() => {
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });

    if (typeof IntersectionObserver !== 'undefined') {
        spy = new IntersectionObserver(
            (entries) => {
                entries.forEach((e) => {
                    if (e.isIntersecting) activeId.value = e.target.id;
                });
            },
            { rootMargin: '-45% 0px -50% 0px', threshold: 0 },
        );
        links.forEach((l) => {
            const el = document.getElementById(l.id);
            if (el) spy.observe(el);
        });
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll);
    spy?.disconnect();
});
</script>

<template>
  <header
    class="fixed inset-x-0 top-0 z-50 transition-all duration-300"
    :class="scrolled ? 'py-2' : 'py-3'"
  >
    <div
      class="pointer-events-none absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-transparent via-brand to-transparent transition-opacity duration-300"
      :class="scrolled ? 'opacity-100' : 'opacity-60'"
    />
    <nav
      class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 sm:px-6"
      :class="scrolled
        ? 'rounded-2xl border border-white/10 bg-[#0a0b14]/88 py-2 shadow-[0_12px_40px_-16px_rgba(0,0,0,0.85)] backdrop-blur-xl'
        : 'py-1'"
    >
      <!-- Brand -->
      <a
        href="#top"
        class="group flex min-w-0 items-center gap-2.5 sm:gap-3"
      >
        <span class="relative shrink-0 rounded-xl bg-black/40 p-1 ring-1 ring-white/10 transition group-hover:ring-brand/40">
          <img
            :src="congngheBrand.badgeCircle"
            alt="Vietnam America Schools"
            class="h-9 w-9 object-contain sm:h-10 sm:w-10"
            width="40"
            height="40"
            decoding="async"
          >
        </span>
        <span class="hidden min-w-0 flex-col leading-tight sm:flex">
          <span class="truncate font-display text-sm font-bold text-white">Phòng Công Nghệ</span>
          <span class="mt-0.5 flex items-center gap-2">
            <img
              :src="congngheBrand.wordmarkStacked"
              alt=""
              class="h-7 w-auto max-w-[140px] object-contain object-left opacity-90"
              loading="lazy"
              decoding="async"
            >
          </span>
        </span>
      </a>

      <!-- Center pill -->
      <div
        class="hidden items-center gap-0.5 rounded-full border border-white/10 bg-white/[0.06] p-1 backdrop-blur-xl transition-shadow lg:flex"
        :class="scrolled && 'shadow-[0_8px_30px_-12px_rgba(0,0,0,0.6)]'"
      >
        <a
          v-for="link in links"
          :key="link.href"
          :href="link.href"
          class="relative rounded-full px-3.5 py-1.5 text-[13px] font-medium transition-colors"
          :class="activeId === link.id ? 'text-white' : 'text-white/60 hover:text-white'"
        >
          <span
            v-if="activeId === link.id"
            class="absolute inset-0 rounded-full bg-gradient-to-r from-brand/90 to-[#6b0028]/90 shadow-[0_0_18px_-2px_rgba(154,0,54,0.55)]"
          />
          <span class="relative">{{ link.label }}</span>
        </a>
      </div>

      <!-- Actions -->
      <div class="flex shrink-0 items-center gap-2">
        <MagneticButton
          v-if="portal.canEnterQlda"
          :href="portal.qldaHome"
          variant="primary"
          class="hidden !px-4 !py-2 !text-[13px] sm:inline-flex"
        >
          Vào hệ thống
          <svg
            width="15"
            height="15"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2.2"
          ><path d="M5 12h14M13 6l6 6-6 6" /></svg>
        </MagneticButton>
        <button
          type="button"
          class="grid h-10 w-10 place-items-center rounded-xl border border-white/10 bg-white/5 text-white/80 backdrop-blur hover:bg-white/10 lg:hidden"
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
          ><path :d="open ? 'M6 6l12 12M6 18L18 6' : 'M4 7h16M4 12h16M4 17h16'" /></svg>
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
        class="mx-4 mt-2 overflow-hidden rounded-2xl border border-white/10 bg-[#0a0b14]/95 backdrop-blur-xl lg:hidden"
      >
        <div class="flex items-center gap-3 border-b border-white/10 bg-gradient-to-r from-brand/20 to-transparent px-4 py-3">
          <img
            :src="congngheBrand.logoVertical"
            alt="VAS"
            class="h-14 w-auto object-contain"
            loading="lazy"
          >
          <p class="text-xs text-white/55">
            Phòng Công Nghệ
          </p>
        </div>
        <div class="grid gap-1 p-3">
          <a
            v-for="link in links"
            :key="link.href"
            :href="link.href"
            class="rounded-xl px-3 py-2.5 text-sm font-medium text-white/75 hover:bg-white/5 hover:text-white"
            @click="open = false"
          >{{ link.label }}</a>
          <a
            v-if="portal.canEnterQlda"
            :href="portal.qldaHome"
            class="mt-1 rounded-xl bg-gradient-to-r from-brand to-[#6b0028] px-3 py-2.5 text-center text-sm font-semibold text-white"
          >
            Vào hệ thống
          </a>
        </div>
      </div>
    </transition>
  </header>
</template>
