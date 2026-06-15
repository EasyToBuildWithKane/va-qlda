<script setup>
import SectionHeading from './SectionHeading.vue';
import { useInView } from './motion.js';

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
    class="relative border-t border-white/5 py-24"
  >
    <div class="mx-auto max-w-7xl px-5 sm:px-8">
      <SectionHeading
        eyebrow="Giới thiệu"
        title="Chúng tôi là Phòng Công Nghệ"
        subtitle="Đơn vị kiến tạo hạ tầng số, sản phẩm phần mềm và năng lực AI cho toàn bộ hệ thống Vietnam America Schools."
      />

      <div class="mt-14 grid gap-6 md:grid-cols-3">
        <article
          v-for="(p, i) in pillars"
          :key="p.key"
          class="group relative overflow-hidden rounded-2xl border border-white/10 bg-white/[0.03] p-7 transition-all duration-700 hover:border-brand/40 hover:bg-white/[0.05]"
          :class="shown ? 'translate-y-0 opacity-100' : 'translate-y-6 opacity-0'"
          :style="{ transitionDelay: `${i * 120}ms` }"
        >
          <div class="absolute -right-10 -top-10 h-28 w-28 rounded-full bg-brand/10 blur-2xl transition-opacity group-hover:opacity-100" />
          <div class="relative grid h-12 w-12 place-items-center rounded-xl bg-gradient-to-br from-brand/80 to-[#ff4d8d]/80 text-white shadow-lg shadow-brand/20">
            <svg
              width="22"
              height="22"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.8"
              stroke-linejoin="round"
            ><path :d="p.icon" /></svg>
          </div>
          <p class="relative mt-5 text-xs font-semibold uppercase tracking-wider text-brand">
            {{ p.tag }}
          </p>
          <h3 class="relative mt-2 font-display text-lg font-bold text-white">
            {{ p.title }}
          </h3>
          <p class="relative mt-3 text-sm leading-relaxed text-white/55">
            {{ p.body }}
          </p>
        </article>
      </div>
    </div>
  </section>
</template>
