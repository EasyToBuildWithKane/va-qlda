<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { congngheBrand } from './congngheBrand.js';
import CongngheBrandBackdrop from './CongngheBrandBackdrop.vue';

const year = new Date().getFullYear();

const page = usePage();
const portal = computed(() => page.props.portal ?? { canEnterQlda: false, qldaHome: '/dashboard' });

const systemLinks = computed(() => {
    if (!portal.value.canEnterQlda) {
        return [
            { href: '/members', label: 'Danh bạ thành viên', external: false },
        ];
    }

    return [
        { href: portal.value.qldaHome, label: 'Bảng điều khiển', external: false },
        { href: '/projects', label: 'Quản lý dự án', external: false },
        { href: '/daily-reports/today', label: 'Báo cáo ngày', external: false },
        { href: '/knowledge-base', label: 'Tri thức', external: false },
    ];
});

const cols = computed(() => [
    {
        heading: 'Khám phá',
        links: [
            { href: '#gioi-thieu', label: 'Giới thiệu', external: true },
            { href: '#lo-trinh', label: 'Lộ trình 2026–2027', external: true },
            { href: '#san-pham', label: 'Hệ sinh thái sản phẩm', external: true },
            { href: '#to-chuc', label: 'Sơ đồ tổ chức', external: true },
            { href: '#du-an', label: 'Dự án triển khai', external: true },
        ],
    },
    {
        heading: 'Hệ thống',
        links: systemLinks.value,
    },
]);
</script>

<template>
  <footer class="relative overflow-hidden border-t border-brand/25 bg-gradient-to-b from-[#0a0810] via-[#070810] to-[#05060c]">
    <CongngheBrandBackdrop
      variant="dragon"
      align="left"
      opacity-class="opacity-[0.05]"
    />
    <div class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-brand/60 to-transparent" />
    <div class="pointer-events-none absolute -right-8 bottom-0 hidden w-[min(280px,40vw)] sm:block">
      <img
        :src="congngheBrand.mascotHoodie"
        alt=""
        class="h-auto w-full translate-y-[12%] object-contain opacity-90 drop-shadow-[0_24px_48px_rgba(0,0,0,0.55)]"
        loading="lazy"
        decoding="async"
      >
    </div>

    <div class="relative mx-auto max-w-7xl px-5 py-16 sm:px-8">
      <div class="grid gap-10 md:grid-cols-[1.5fr_1fr_1fr]">
        <div class="relative z-10 max-w-md">
          <div class="flex flex-wrap items-start gap-4">
            <img
              :src="congngheBrand.logoVertical"
              alt="Vietnam America Schools"
              class="h-20 w-auto object-contain sm:h-24"
              loading="lazy"
              decoding="async"
            >
            <div class="min-w-0 pt-1">
              <p class="font-display text-lg font-bold text-white">
                Phòng Công Nghệ
              </p>
              <p class="mt-1 text-sm text-white/50">
                Đơn vị kiến tạo nền tảng số &amp; AI cho toàn hệ thống.
              </p>
            </div>
          </div>
          <p class="mt-5 text-sm leading-relaxed text-white/50">
            Kiến tạo hạ tầng dữ liệu, sản phẩm phần mềm và năng lực trí tuệ nhân tạo —
            đồng hành cùng đội ngũ bằng những giải pháp thật, đo lường được.
          </p>
          <a
            href="mailto:phongcongnghe@vaschools.edu.vn"
            class="mt-4 inline-flex items-center gap-2 text-sm text-white/60 transition hover:text-white"
          >
            <svg
              width="16"
              height="16"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <rect
                x="3"
                y="5"
                width="18"
                height="14"
                rx="2"
              />
              <path d="m3 7 9 6 9-6" />
            </svg>
            phongcongnghe@vaschools.edu.vn
          </a>
        </div>

        <div
          v-for="col in cols"
          :key="col.heading"
          class="relative z-10"
        >
          <p class="font-mono text-[11px] font-semibold uppercase tracking-[0.18em] text-brand-300/80">
            {{ col.heading }}
          </p>
          <ul class="mt-4 space-y-2.5">
            <li
              v-for="link in col.links"
              :key="link.label"
            >
              <a
                :href="link.href"
                class="text-sm text-white/60 transition hover:text-white"
              >{{ link.label }}</a>
            </li>
          </ul>
        </div>
      </div>

      <div class="relative z-10 mt-12 flex flex-col items-center justify-between gap-4 border-t border-white/10 pt-6 sm:flex-row">
        <div class="flex flex-wrap items-center justify-center gap-3 sm:justify-start">
          <img
            :src="congngheBrand.badgeCircle"
            alt=""
            class="h-10 w-10 object-contain opacity-80"
            loading="lazy"
          >
          <p class="text-center text-[12.5px] text-white/40 sm:text-left">
            © {{ year }} Vietnam America Schools · Phòng Công Nghệ
          </p>
        </div>
        <p class="text-[12.5px] text-white/35">
          Laravel · Vue · Inertia — nội bộ VAS
        </p>
      </div>
    </div>
  </footer>
</template>
