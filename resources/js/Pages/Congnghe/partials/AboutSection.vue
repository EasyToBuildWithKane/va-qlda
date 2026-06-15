<script setup>
import SectionHeading from './SectionHeading.vue';
import GlassCard from './GlassCard.vue';
import CongngheMascotAnimated from './CongngheMascotAnimated.vue';
import { useInView } from './motion.js';
import { congngheBrand } from './congngheBrand.js';
import CongngheBrandBackdrop from './CongngheBrandBackdrop.vue';

const { target, shown } = useInView();

const pillars = [
    {
        key: 'mission',
        tag: 'Sứ mệnh',
        title: 'Đưa công nghệ vào mọi quy trình giáo dục',
        body: 'Số hoá và tự động hoá các nghiệp vụ cốt lõi, để giáo viên và đội ngũ vận hành tập trung vào điều quan trọng nhất: người học.',
        icon: 'M12 2 2 7l10 5 10-5-10-5ZM2 17l10 5 10-5M2 12l10 5 10-5',
    },
    {
        key: 'vision',
        tag: 'Tầm nhìn',
        title: 'Nền tảng dữ liệu hợp nhất cho toàn hệ thống',
        body: 'Trở thành trung tâm công nghệ dẫn dắt chuyển đổi số của nhà trường, nơi mọi quyết định đều dựa trên dữ liệu thật, tức thời.',
        icon: 'M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z M12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z',
    },
    {
        key: 'values',
        tag: 'Giá trị',
        title: 'Sản phẩm thật · Đo lường được · Tử tế',
        body: 'Chúng tôi làm ra thứ dùng được mỗi ngày, đo bằng kết quả chứ không bằng lời hứa, và luôn đặt trải nghiệm con người làm trọng tâm.',
        icon: 'M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 1 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78Z',
    },
];
</script>

<template>
  <section
    id="gioi-thieu"
    ref="target"
    class="relative scroll-mt-24 py-16 sm:scroll-mt-28 sm:py-20 md:py-24"
  >
    <CongngheBrandBackdrop
      variant="badge"
      align="left"
      opacity-class="opacity-[0.05]"
    />
    <div
      class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-brand/40 to-transparent"
      aria-hidden="true"
    />

    <div class="relative mx-auto max-w-7xl px-4 sm:px-6 md:px-8">
      <div class="grid gap-10 lg:grid-cols-12 lg:items-start lg:gap-10 xl:gap-12">
        <div
          class="lg:col-span-5 xl:col-span-4"
          :class="shown ? 'translate-y-0 opacity-100' : 'translate-y-6 opacity-0'"
          style="transition: opacity 0.7s ease, transform 0.7s ease"
        >
          <SectionHeading
            eyebrow="Giới thiệu"
            title="Kim chỉ nam cho mọi hoạt động"
            subtitle="Đơn vị kiến tạo hạ tầng số, sản phẩm phần mềm và năng lực AI cho toàn bộ hệ thống Vietnam America Schools."
          />

          <div
            class="mt-8 hidden rounded-2xl border border-white/10 bg-gradient-to-br from-white/[0.06] to-brand/[0.08] p-5 backdrop-blur-md lg:block lg:mt-10 lg:p-6"
          >
            <div class="flex flex-col items-center gap-4 sm:flex-row sm:items-center md:flex-col md:items-center lg:flex-row lg:items-center">
              <CongngheMascotAnimated
                :src="congngheBrand.mascotVaJacket"
                alt="Linh vật VAS"
                variant="inline"
              />
              <p class="text-center text-sm leading-relaxed text-white/60 sm:text-left md:text-center lg:text-left">
                Trợ lý góc màn hình sẽ gợi ý theo từng mục bạn đang xem — thử cuộn trang hoặc bấm vào linh vật nhé.
              </p>
            </div>
          </div>
        </div>

        <div class="grid gap-5 sm:gap-6 md:grid-cols-2 lg:col-span-7 lg:grid-cols-1 xl:col-span-8 xl:grid-cols-3 xl:gap-6">
          <GlassCard
            v-for="(p, i) in pillars"
            :key="p.key"
            tilt
            :padded="false"
            class="p-6 transition-all duration-700 sm:p-7 md:col-span-2 md:last:col-span-2 xl:col-span-1 xl:last:col-span-1"
            :class="shown ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0'"
            :style="{ transitionDelay: `${120 + i * 100}ms` }"
          >
            <span class="font-mono text-[11px] font-semibold uppercase tracking-[0.2em] text-brand-300">0{{ i + 1 }}</span>
            <div class="mt-3 grid h-12 w-12 place-items-center rounded-2xl bg-gradient-to-br from-brand to-[#ff4d8d] p-3 text-white shadow-lg shadow-brand/30">
              <svg
                width="24"
                height="24"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.7"
                stroke-linejoin="round"
              ><path :d="p.icon" /></svg>
            </div>
            <p class="mt-5 font-mono text-[11px] font-semibold uppercase tracking-[0.18em] text-white/45">
              {{ p.tag }}
            </p>
            <h3 class="mt-1.5 font-display text-lg font-bold text-white sm:text-xl">
              {{ p.title }}
            </h3>
            <p class="mt-3 text-sm leading-relaxed text-white/55">
              {{ p.body }}
            </p>
          </GlassCard>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
@media (prefers-reduced-motion: reduce) {
    section [style*='transition'] {
        transition: none !important;
    }
}
</style>
