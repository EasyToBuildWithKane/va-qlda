<script setup>
import SectionHeading from './SectionHeading.vue';
import GlassCard from './GlassCard.vue';
import { useInView } from './motion.js';
import { congngheBrand } from './congngheBrand.js';
import CongngheBrandBackdrop from './CongngheBrandBackdrop.vue';

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
    class="relative overflow-hidden py-20"
  >
    <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-brand/[0.08] via-[#05060c] to-brand/[0.06]" />
    <CongngheBrandBackdrop
      variant="dragon"
      align="left"
      opacity-class="opacity-[0.04]"
    />

    <div class="relative mx-auto max-w-7xl px-5 sm:px-8">
      <div class="lg:grid lg:grid-cols-[minmax(0,1fr)_min(200px,22%)] lg:items-start lg:gap-10 xl:gap-14">
        <!-- Trái: tiêu đề + lộ trình -->
        <div class="min-w-0">
          <SectionHeading
            eyebrow="Lộ trình 2026 — 2027"
            title="Chặng đường phía trước"
            subtitle="Định hướng phát triển sản phẩm và năng lực công nghệ trong 18 tháng tới — đồng hành cùng hệ sinh thái VAS."
          />

          <ol class="relative mt-10 space-y-7 before:absolute before:left-[18px] before:top-3 before:h-[calc(100%-1.5rem)] before:w-px before:bg-gradient-to-b before:from-brand before:via-brand/30 before:to-transparent sm:before:left-[22px] lg:mt-12">
            <li
              v-for="(m, i) in milestones"
              :key="m.title"
              class="relative pl-14 transition-all duration-700 sm:pl-16"
              :class="shown ? 'translate-x-0 opacity-100' : '-translate-x-5 opacity-0'"
              :style="{ transitionDelay: `${i * 120}ms` }"
            >
              <span class="absolute left-0 top-1 grid h-9 w-9 place-items-center overflow-hidden rounded-full border border-brand/40 bg-[#0a0b14] sm:h-11 sm:w-11">
                <img
                  v-if="i === 0"
                  :src="congngheBrand.badgeCircle"
                  alt=""
                  class="h-full w-full object-cover p-0.5"
                >
                <span
                  v-else
                  class="font-mono text-[12px] font-bold text-brand-300"
                >{{ i + 1 }}</span>
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

        <!-- Phải: mascot -->
        <aside
          class="relative mx-auto mt-10 hidden max-w-[180px] lg:sticky lg:top-28 lg:mt-16 lg:block"
          :class="shown ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0'"
          style="transition: opacity 700ms, transform 700ms"
        >
          <div class="rounded-2xl border border-brand/20 bg-gradient-to-b from-brand/15 to-white/[0.03] p-3 backdrop-blur-sm">
            <p class="text-center font-mono text-[10px] font-semibold uppercase tracking-[0.2em] text-brand-300">
              Người dẫn đường
            </p>
            <img
              :src="congngheBrand.mascotVaJacket"
              alt="Linh vật VAS đồng hành lộ trình công nghệ"
              class="mx-auto mt-2 h-auto max-h-[200px] w-full object-contain drop-shadow-[0_16px_32px_rgba(0,0,0,0.45)]"
              loading="lazy"
              decoding="async"
            >
          </div>
        </aside>
      </div>

      <div
        class="mt-10 flex items-center gap-4 rounded-2xl border border-white/10 bg-white/[0.03] px-4 py-5 sm:px-6 lg:hidden"
        :class="shown ? 'opacity-100' : 'opacity-0'"
        style="transition: opacity 700ms 400ms"
      >
        <img
          :src="congngheBrand.mascotVaJacket"
          alt=""
          class="h-20 w-auto shrink-0 object-contain"
          loading="lazy"
        >
        <p class="text-sm leading-relaxed text-white/55">
          Linh vật VAS đồng hành cùng từng mốc lộ trình.
        </p>
      </div>
    </div>
  </section>
</template>
