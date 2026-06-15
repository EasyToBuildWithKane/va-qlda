<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Avatar from '@/shared/ui/Avatar.vue';
import { congngheBrand } from './congngheBrand.js';

const page = usePage();

const authUser = computed(() => page.props.auth?.user ?? null);
const userName = computed(
    () => authUser.value?.employee?.full_name
        ?? authUser.value?.display_name
        ?? authUser.value?.username
        ?? 'Thành viên',
);
const userEmail = computed(() => authUser.value?.email ?? '');
const userAvatar = computed(() => authUser.value?.employee?.avatar_path ?? null);
const userRole = computed(() => {
    const role = authUser.value?.role;
    if (role === 'admin') return 'Quản trị';
    if (role === 'lead') return 'Trưởng nhóm';
    if (role === 'member') return 'Thành viên';
    if (role === 'viewer') return 'Xem';
    return role ?? '';
});

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
    scrolled.value = window.scrollY > 16;
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
    class="fixed inset-x-0 top-0 z-50 border-b border-cyan-500/15 bg-[#070912]/92 shadow-[0_8px_32px_-12px_rgba(0,0,0,0.65)] backdrop-blur-xl transition-shadow duration-300"
    :class="scrolled && 'shadow-[0_12px_40px_-16px_rgba(154,0,54,0.35)]'"
  >
    <div
      class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-cyan-400/40 via-brand to-violet-500/40"
      aria-hidden="true"
    />
    <div
      class="pointer-events-none absolute inset-0 opacity-[0.35]"
      aria-hidden="true"
      style="background-image: linear-gradient(rgba(56,189,248,0.04) 1px, transparent 1px), linear-gradient(90deg, rgba(56,189,248,0.04) 1px, transparent 1px); background-size: 32px 32px;"
    />

    <nav class="relative mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-2.5 sm:px-6">
      <a
        href="#top"
        class="group flex min-w-0 items-center gap-2 sm:gap-2.5"
      >
        <span class="relative shrink-0 rounded-lg bg-black/50 p-0.5 ring-1 ring-cyan-500/20 transition group-hover:ring-brand/50">
          <img
            :src="congngheBrand.badgeCircle"
            alt="Vietnam America Schools"
            class="h-8 w-8 object-contain sm:h-9 sm:w-9"
            width="36"
            height="36"
            decoding="async"
          >
        </span>
        <span class="hidden min-w-0 flex-col leading-tight sm:flex">
          <span class="truncate font-display text-sm font-bold text-white">Phòng Công Nghệ</span>
          <span class="font-mono text-[10px] tracking-wider text-cyan-200/50">VAS · TECH PORTAL</span>
        </span>
      </a>

      <div class="hidden items-center gap-0.5 rounded-full border border-white/10 bg-[#0c0e18]/80 p-1 lg:flex">
        <a
          v-for="link in links"
          :key="link.href"
          :href="link.href"
          class="relative rounded-full px-3 py-1.5 text-[13px] font-medium transition-colors"
          :class="activeId === link.id ? 'text-white' : 'text-white/55 hover:text-white'"
        >
          <span
            v-if="activeId === link.id"
            class="absolute inset-0 rounded-full bg-gradient-to-r from-brand/90 to-[#4a1030] ring-1 ring-cyan-400/20"
          />
          <span class="relative">{{ link.label }}</span>
        </a>
      </div>

      <div class="flex shrink-0 items-center gap-2">
        <div
          v-if="authUser"
          class="hidden max-w-[220px] items-center gap-2.5 rounded-full border border-white/10 bg-white/[0.06] py-1 pl-1 pr-3 sm:flex"
        >
          <Avatar
            :name="userName"
            :src="userAvatar"
            :size="32"
          />
          <div class="min-w-0 leading-tight">
            <p class="truncate text-xs font-semibold text-white">
              {{ userName }}
            </p>
            <p
              v-if="userEmail"
              class="truncate font-mono text-[10px] text-cyan-200/45"
            >
              {{ userEmail }}
            </p>
            <p
              v-else-if="userRole"
              class="font-mono text-[10px] text-white/40"
            >
              {{ userRole }}
            </p>
          </div>
        </div>
        <button
          type="button"
          class="grid h-9 w-9 place-items-center rounded-lg border border-white/10 bg-white/5 text-white/80 hover:bg-white/10 lg:hidden"
          aria-label="Mở menu"
          @click="open = !open"
        >
          <svg
            width="20"
            height="20"
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
        class="relative border-t border-white/10 bg-[#0a0b14]/98 px-4 py-3 backdrop-blur-xl lg:hidden"
      >
        <div
          v-if="authUser"
          class="mb-3 flex items-center gap-3 rounded-xl border border-white/10 bg-white/[0.04] px-3 py-2.5"
        >
          <Avatar
            :name="userName"
            :src="userAvatar"
            :size="36"
          />
          <div class="min-w-0">
            <p class="truncate text-sm font-semibold text-white">
              {{ userName }}
            </p>
            <p class="truncate text-xs text-white/50">
              {{ userEmail || userRole }}
            </p>
          </div>
        </div>
        <div class="grid gap-1">
          <a
            v-for="link in links"
            :key="link.href"
            :href="link.href"
            class="rounded-xl px-3 py-2.5 text-sm font-medium text-white/75 hover:bg-white/5 hover:text-white"
            @click="open = false"
          >{{ link.label }}</a>
        </div>
      </div>
    </transition>
  </header>
</template>
