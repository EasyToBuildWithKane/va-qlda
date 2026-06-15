<script setup>
import SectionHeading from './SectionHeading.vue';
import GlassCard from './GlassCard.vue';
import { useInView } from './motion.js';

const { target, shown } = useInView({ threshold: 0.1 });

const milestones = [
    {
        period: 'Quý III · 2026',
        title: 'Hợp nhất nền tảng dữ liệu',
        body: 'Đồng bộ dữ liệu nhân sự, dự án và vận hành về một nguồn duy nhất.',
        state: 'Đang triển khai',
        live: true,
    },
    {
        period: 'Quý IV · 2026',
        title: 'Trợ lý AI nội bộ giai đoạn 1',
        body: 'Tích hợp AI vào báo cáo ngày, tri thức và hỗ trợ tra cứu.',
        state: 'Sắp tới',
        live: false,
    },
    {
        period: 'Quý I · 2027',
        title: 'Cổng dịch vụ số toàn trường',
        body: 'Mở rộng nền tảng phục vụ giáo viên và các phòng ban khác.',
        state: 'Kế hoạch',
        live: false,
    },
    {
        period: 'Quý II · 2027',
        title: 'Phân tích dự đoán',
        body: 'Mô hình dự báo hỗ trợ ra quyết định vận hành dựa trên dữ liệu.',
        state: 'Kế hoạch',
        live: false,
    },
];
</script>

<template>
  <section
    id="lo-trinh"
    ref="target"
    class="relative py-20"
  >
    <div class="mx-auto max-w-7xl px-5 sm:px-8">
      <SectionHeading
        eyebrow="Lộ trình 2026 — 2027"
        title="Chặng đường phía trước"
        subtitle="Định hướng phát triển sản phẩm và năng lực công nghệ trong 18 tháng tới."
      />

      <ol class="relative mt-14 space-y-7 before:absolute before:left-[18px] before:top-3 before:h-[calc(100%-1.5rem)] before:w-px before:bg-gradient-to-b before:from-brand before:via-brand/30 before:to-transparent sm:before:left-[22px]">
        <li
          v-for="(m, i) in milestones"
          :key="m.title"
          class="relative pl-14 transition-all duration-700 sm:pl-16"
          :class="shown ? 'translate-x-0 opacity-100' : '-translate-x-5 opacity-0'"
          :style="{ transitionDelay: `${i * 120}ms` }"
        >
          <span class="absolute left-0 top-1 grid h-9 w-9 place-items-center rounded-full border border-brand/40 bg-[#0a0b14] font-mono text-[12px] font-bold text-brand-300 sm:h-11 sm:w-11">
            {{ i + 1 }}
            <span
              v-if="m.live"
              class="absolute inset-0 rounded-full ring-2 ring-brand/50 animate-cn-ping-ring"
            />
          </span>
          <GlassCard
            tilt
            :padded="false"
            class="p-6"
          >
            <div class="flex flex-wrap items-center justify-between gap-2">
              <p class="font-mono text-[12px] font-semibold uppercase tracking-wider text-brand-300">
                {{ m.period }}
              </p>
              <span
                class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-medium"
                :class="m.live ? 'border-emerald-400/20 bg-emerald-400/10 text-emerald-300' : 'border-white/10 bg-white/5 text-white/55'"
              >
                <span
                  v-if="m.live"
                  class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-cn-glow"
                />
                {{ m.state }}
              </span>
            </div>
            <h3 class="mt-2 font-display text-lg font-bold text-white">
              {{ m.title }}
            </h3>
            <p class="mt-1.5 text-sm leading-relaxed text-white/55">
              {{ m.body }}
            </p>
          </GlassCard>
        </li>
      </ol>
    </div>
  </section>
</template>
