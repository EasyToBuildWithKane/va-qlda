<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

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
  <footer class="relative border-t border-white/10 bg-[#070810]">
    <div class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-brand/50 to-transparent" />
    <div class="mx-auto max-w-7xl px-5 py-16 sm:px-8">
      <div class="grid gap-10 md:grid-cols-[1.4fr_1fr_1fr]">
        <div>
          <div class="flex items-center gap-2.5">
            <span class="grid h-10 w-10 place-items-center rounded-xl bg-gradient-to-br from-brand to-[#ff4d8d] text-sm font-bold text-white">
              CN
            </span>
            <div class="leading-none">
              <p class="font-display text-base font-bold text-white">
                Phòng Công Nghệ
              </p>
              <p class="mt-1 text-[11px] tracking-wide text-white/45">
                Vietnam America Schools
              </p>
            </div>
          </div>
          <p class="mt-4 max-w-sm text-sm leading-relaxed text-white/50">
            Kiến tạo nền tảng công nghệ và dữ liệu cho hệ thống giáo dục —
            đồng hành cùng đội ngũ bằng những sản phẩm thật, đo lường được.
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
        >
          <p class="font-mono text-[11px] font-semibold uppercase tracking-[0.18em] text-white/40">
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

      <div class="mt-12 flex flex-col items-center justify-between gap-3 border-t border-white/10 pt-6 text-[12.5px] text-white/40 sm:flex-row">
        <p>© {{ year }} Phòng Công Nghệ — Vietnam America Schools.</p>
        <p>Được xây dựng nội bộ trên Laravel · Vue · Inertia.</p>
      </div>
    </div>
  </footer>
</template>
