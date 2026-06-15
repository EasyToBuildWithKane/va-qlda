<script setup>
import { congngheBrand } from './congngheBrand.js';
import CongngheBrandImage from './CongngheBrandImage.vue';

const year = new Date().getFullYear();

const cols = [
    {
        heading: 'Khám phá',
        links: [
            { href: '#gioi-thieu', label: 'Giới thiệu' },
            { href: '#lo-trinh', label: 'Lộ trình 2026–2027' },
            { href: '#san-pham', label: 'Hệ sinh thái sản phẩm' },
            { href: '#to-chuc', label: 'Sơ đồ tổ chức' },
            { href: '#du-an', label: 'Dự án triển khai' },
        ],
    },
    {
        heading: 'Liên lạc',
        links: [
            { href: 'mailto:phongcongnghe@vaschools.edu.vn', label: 'phongcongnghe@vaschools.edu.vn' },
            { href: '/members', label: 'Danh bạ nội bộ' },
            { href: '#to-chuc', label: 'Liên hệ theo sơ đồ tổ chức' },
        ],
    },
];
</script>

<template>
  <footer class="relative overflow-hidden border-t border-brand/40 bg-gradient-to-br from-[#120818] via-brand/25 to-[#05060c]">
    <div
      class="pointer-events-none absolute inset-0 opacity-40"
      aria-hidden="true"
      style="background-image: radial-gradient(circle at 20% 0%, rgba(154,0,54,0.35), transparent 45%), radial-gradient(circle at 80% 100%, rgba(56,189,248,0.12), transparent 40%);"
    />
    <div class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-cyan-400/30 via-brand to-violet-500/30" />

    <div class="relative mx-auto max-w-7xl px-5 py-8 sm:px-8 sm:py-10">
      <div class="grid gap-8 lg:grid-cols-[1.5fr_1fr_1fr_minmax(140px,0.85fr)] lg:items-start lg:gap-8 xl:gap-10">
        <!-- Brand -->
        <div class="min-w-0 text-center lg:text-left">
          <div class="flex flex-col items-center gap-3 sm:flex-row sm:items-start sm:justify-center lg:justify-start">
            <CongngheBrandImage
              :src="congngheBrand.logoVertical"
              alt="Vietnam America Schools"
              class="h-16 w-auto sm:h-[4.5rem]"
              loading="lazy"
            />
            <div class="min-w-0">
              <p class="font-display text-lg font-bold text-white sm:text-xl">
                Phòng Công Nghệ
              </p>
              <p class="mt-1 text-sm text-white/65">
                Đơn vị kiến tạo nền tảng số &amp; AI cho toàn hệ thống.
              </p>
            </div>
          </div>
          <p class="mt-4 text-sm leading-relaxed text-white/55">
            Kiến tạo hạ tầng dữ liệu, sản phẩm phần mềm và năng lực trí tuệ nhân tạo —
            đồng hành cùng đội ngũ bằng những giải pháp thật, đo lường được.
          </p>
          <a
            href="mailto:phongcongnghe@vaschools.edu.vn"
            class="mt-4 inline-flex items-center justify-center gap-2 text-sm text-cyan-100/80 transition hover:text-white lg:justify-start"
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
          class="text-center lg:text-left"
        >
          <p class="font-mono text-[11px] font-semibold uppercase tracking-[0.18em] text-cyan-200/70">
            {{ col.heading }}
          </p>
          <ul class="mt-4 space-y-2.5">
            <li
              v-for="link in col.links"
              :key="link.label"
            >
              <a
                :href="link.href"
                class="text-sm text-white/65 transition hover:text-white"
              >{{ link.label }}</a>
            </li>
          </ul>
        </div>

        <!-- Mascot bên phải -->
        <div class="flex justify-center lg:justify-end">
          <CongngheBrandImage
            :src="congngheBrand.mascotHoodie"
            alt="Linh vật VAS — Phòng Công Nghệ"
            class="h-44 w-auto sm:h-52 lg:h-56 xl:h-64"
            loading="lazy"
          />
        </div>
      </div>

      <div class="mt-10 flex flex-col items-center justify-between gap-3 border-t border-white/15 pt-6 sm:flex-row">
        <div class="flex flex-wrap items-center justify-center gap-3 sm:justify-start">
          <CongngheBrandImage
            :src="congngheBrand.badgeCircle"
            alt=""
            class="h-9 w-9 opacity-90"
            loading="lazy"
          />
          <p class="text-center text-[12.5px] text-white/45 sm:text-left">
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
