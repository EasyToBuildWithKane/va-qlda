<script setup>
import SectionHeading from './SectionHeading.vue';
import { useInView } from './motion.js';

const { target, shown } = useInView({ threshold: 0.1 });

const milestones = [
    {
        period: 'Quý III · 2026',
        title: 'Hợp nhất nền tảng dữ liệu',
        body: 'Đồng bộ dữ liệu nhân sự, dự án và vận hành về một nguồn duy nhất.',
        state: 'Đang triển khai',
    },
    {
        period: 'Quý IV · 2026',
        title: 'Trợ lý AI nội bộ giai đoạn 1',
        body: 'Tích hợp AI vào báo cáo ngày, tri thức và hỗ trợ tra cứu.',
        state: 'Sắp tới',
    },
    {
        period: 'Quý I · 2027',
        title: 'Cổng dịch vụ số toàn trường',
        body: 'Mở rộng nền tảng phục vụ giáo viên và các phòng ban khác.',
        state: 'Kế hoạch',
    },
    {
        period: 'Quý II · 2027',
        title: 'Phân tích dự đoán',
        body: 'Mô hình dự báo hỗ trợ ra quyết định vận hành dựa trên dữ liệu.',
        state: 'Kế hoạch',
    },
];
</script>

<template>
  <section
    id="lo-trinh"
    ref="target"
    class="relative border-t border-white/5 py-24"
  >
    <div class="mx-auto max-w-7xl px-5 sm:px-8">
      <SectionHeading
        eyebrow="Lộ trình 2026 — 2027"
        title="Chặng đường phía trước"
        subtitle="Định hướng phát triển sản phẩm và năng lực công nghệ trong 18 tháng tới."
      />

      <ol class="relative mt-14 space-y-8 before:absolute before:left-[15px] before:top-2 before:h-[calc(100%-1rem)] before:w-px before:bg-gradient-to-b before:from-brand before:via-white/15 before:to-transparent sm:before:left-[19px]">
        <li
          v-for="(m, i) in milestones"
          :key="m.title"
          class="relative pl-12 transition-all duration-700 sm:pl-14"
          :class="shown ? 'translate-x-0 opacity-100' : '-translate-x-4 opacity-0'"
          :style="{ transitionDelay: `${i * 120}ms` }"
        >
          <span class="absolute left-0 top-0 grid h-8 w-8 place-items-center rounded-full border border-brand/40 bg-[#0b0b12] text-[11px] font-bold text-brand sm:h-10 sm:w-10 sm:text-sm">
            {{ i + 1 }}
          </span>
          <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-6">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <p class="text-[12px] font-semibold uppercase tracking-wider text-brand">
                {{ m.period }}
              </p>
              <span class="rounded-full border border-white/10 bg-white/5 px-2.5 py-1 text-[11px] font-medium text-white/55">{{ m.state }}</span>
            </div>
            <h3 class="mt-2 font-display text-lg font-bold text-white">
              {{ m.title }}
            </h3>
            <p class="mt-1.5 text-sm leading-relaxed text-white/55">
              {{ m.body }}
            </p>
          </div>
        </li>
      </ol>
    </div>
  </section>
</template>
