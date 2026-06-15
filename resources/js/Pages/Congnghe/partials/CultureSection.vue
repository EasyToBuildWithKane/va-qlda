<script setup>
import SectionHeading from './SectionHeading.vue';
import GlassCard from './GlassCard.vue';
import { useInView } from './motion.js';

const { target, shown } = useInView();

const values = [
    { title: 'Ship thật', body: 'Ưu tiên thứ chạy được trong tay người dùng hơn bản trình chiếu hoàn hảo.', icon: 'M5 12h14M13 6l6 6-6 6' },
    { title: 'Học liên tục', body: 'Mỗi sprint là một cơ hội để giỏi hơn hôm qua.', icon: 'M12 2 2 7l10 5 10-5-10-5ZM2 17l10 5 10-5' },
    { title: 'Minh bạch', body: 'Dữ liệu mở, quyết định rõ ràng, phản hồi thẳng thắn và tử tế.', icon: 'M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z' },
    { title: 'Lấy người dùng làm gốc', body: 'Mọi tính năng bắt đầu từ một nhu cầu thật của đồng nghiệp.', icon: 'M16 11a4 4 0 1 0-8 0 M3 21a7 7 0 0 1 18 0' },
    { title: 'Tự động hoá', body: 'Việc lặp lại thì để máy làm, con người dành sức cho việc khó.', icon: 'M12 2v4M12 18v4M4.9 4.9l2.8 2.8M16.3 16.3l2.8 2.8M2 12h4M18 12h4' },
    { title: 'Đồng đội', body: 'Thành công của sản phẩm là thành công của cả nhóm.', icon: 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 7a4 4 0 1 0 0 .01' },
];
</script>

<template>
  <section
    id="van-hoa"
    ref="target"
    class="relative py-28"
  >
    <div class="mx-auto max-w-7xl px-5 sm:px-8">
      <SectionHeading
        center
        eyebrow="Văn hoá"
        title="Cách chúng tôi làm việc cùng nhau"
        subtitle="Sáu nguyên tắc định hình tinh thần của Phòng Công Nghệ."
      />

      <div class="mt-14 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <GlassCard
          v-for="(v, i) in values"
          :key="v.title"
          tilt
          :padded="false"
          class="p-6 transition-all duration-700"
          :class="shown ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0'"
          :style="{ transitionDelay: `${i * 80}ms` }"
        >
          <div class="flex items-center justify-between">
            <span class="grid h-11 w-11 place-items-center rounded-xl bg-gradient-to-br from-brand/80 to-[#ff4d8d]/80 text-white shadow-lg shadow-brand/20">
              <svg
                width="22"
                height="22"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
              ><path :d="v.icon" /></svg>
            </span>
            <span class="font-mono text-[11px] uppercase tracking-wider text-white/25">0{{ i + 1 }}</span>
          </div>
          <h3 class="mt-4 font-display text-base font-bold text-white">
            {{ v.title }}
          </h3>
          <p class="mt-2 text-sm leading-relaxed text-white/55">
            {{ v.body }}
          </p>
        </GlassCard>
      </div>
    </div>
  </section>
</template>
