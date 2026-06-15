<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { congngheBrand } from './congngheBrand.js';
import CongngheBrandImage from './CongngheBrandImage.vue';

const year = new Date().getFullYear();
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
</script>

<template>
  <footer class="relative border-t border-white/10 bg-[#070912]/95">
    <div class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-brand/50 to-transparent" />

    <div class="relative mx-auto max-w-7xl px-5 py-8 sm:px-8">
      <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between lg:gap-8">
        <!-- Brand -->
        <div class="flex min-w-0 flex-col items-center gap-3 sm:flex-row sm:items-center lg:items-center">
          <CongngheBrandImage
            :src="congngheBrand.logoVertical"
            alt="Vietnam America Schools"
            class="h-11 w-auto shrink-0 sm:h-12"
            loading="lazy"
          />
          <div class="min-w-0 text-center sm:text-left">
            <p class="font-display text-base font-bold text-white sm:text-lg">
              Phòng Công Nghệ
            </p>
            <a
              href="mailto:phongcongnghe@vaschools.edu.vn"
              class="mt-0.5 inline-block text-xs text-cyan-200/70 transition hover:text-white"
            >
              phongcongnghe@vaschools.edu.vn
            </a>
          </div>
        </div>

        <!-- Liên kết nhanh -->
        <nav
          class="flex flex-wrap items-center justify-center gap-x-4 gap-y-2 lg:flex-1 lg:justify-center"
          aria-label="Liên kết trang"
        >
          <a
            v-for="link in links"
            :key="link.href"
            :href="link.href"
            class="text-[13px] text-white/55 transition hover:text-white"
          >{{ link.label }}</a>
          <a
            v-if="portal.canEnterQlda"
            :href="portal.qldaHome"
            class="text-[13px] font-medium text-brand-200/90 transition hover:text-brand-100"
          >
            Vào QLDA
          </a>
        </nav>

        <div class="hidden shrink-0 justify-center lg:flex lg:justify-end">
          <CongngheBrandImage
            :src="congngheBrand.mascotHoodie"
            alt=""
            class="h-28 w-auto xl:h-32"
            loading="lazy"
          />
        </div>
      </div>

      <div class="mt-6 flex flex-col items-center justify-between gap-2 border-t border-white/10 pt-4 text-[11px] text-white/40 sm:flex-row">
        <p class="text-center sm:text-left">
          © {{ year }} Vietnam America Schools · Phòng Công Nghệ
        </p>
        <p class="text-white/30">
          Nội bộ VAS
        </p>
      </div>
    </div>
  </footer>
</template>
