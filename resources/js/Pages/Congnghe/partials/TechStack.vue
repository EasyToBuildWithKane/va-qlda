<script setup>
import SectionHeading from './SectionHeading.vue';
import { useInView } from './motion.js';

const { target, shown } = useInView();

const groups = [
    {
        title: 'Backend',
        items: ['Laravel 10', 'PHP 8', 'MySQL', 'Redis', 'REST / Inertia'],
    },
    {
        title: 'Frontend',
        items: ['Vue 3', 'Inertia.js', 'Tailwind CSS', 'Vite', 'Pinia'],
    },
    {
        title: 'Dữ liệu & AI',
        items: ['Python', 'OpenAI / Claude', 'Pandas', 'ETL Pipelines', 'BI Dashboards'],
    },
    {
        title: 'Hạ tầng & DevOps',
        items: ['Docker', 'GitHub Actions', 'Nginx', 'CI/CD', 'Cloud Hosting'],
    },
];
</script>

<template>
  <section
    id="cong-nghe"
    ref="target"
    class="relative border-t border-white/5 py-24"
  >
    <div class="mx-auto max-w-7xl px-5 sm:px-8">
      <SectionHeading
        eyebrow="Công nghệ sử dụng"
        title="Bộ công cụ vận hành sản phẩm"
        subtitle="Một nền tảng hiện đại, mã nguồn mở và có thể mở rộng — nền móng cho mọi sản phẩm chúng tôi xây dựng."
      />

      <div class="mt-14 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <div
          v-for="(group, i) in groups"
          :key="group.title"
          class="rounded-2xl border border-white/10 bg-white/[0.03] p-6 transition-all duration-700"
          :class="shown ? 'translate-y-0 opacity-100' : 'translate-y-6 opacity-0'"
          :style="{ transitionDelay: `${i * 100}ms` }"
        >
          <h3 class="font-display text-sm font-bold uppercase tracking-wider text-brand">
            {{ group.title }}
          </h3>
          <ul class="mt-4 flex flex-wrap gap-2">
            <li
              v-for="item in group.items"
              :key="item"
              class="rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 text-[12.5px] font-medium text-white/75"
            >
              {{ item }}
            </li>
          </ul>
        </div>
      </div>
    </div>
  </section>
</template>
