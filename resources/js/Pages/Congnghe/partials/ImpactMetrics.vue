<script setup>
import SectionHeading from './SectionHeading.vue';
import CountStat from './CountStat.vue';
import { useInView } from './motion.js';

const props = defineProps({
    metrics: { type: Object, default: () => ({}) },
});

const { target, shown } = useInView({ threshold: 0.3 });

const stats = [
    { key: 'projects', label: 'Dự án đã & đang triển khai', suffix: '+' },
    { key: 'members', label: 'Nhân sự đang hoạt động', suffix: '' },
    { key: 'doneTasks', label: 'Công việc đã hoàn thành', suffix: '+' },
    { key: 'departments', label: 'Phòng ban đồng hành', suffix: '' },
    { key: 'orgTeams', label: 'Nhóm trong sơ đồ tổ chức', suffix: '' },
    { key: 'aiAccounts', label: 'Tài khoản AI được quản lý', suffix: '' },
];

function valueOf(key) {
    return Number(props.metrics?.[key] ?? 0);
}
</script>

<template>
  <section
    id="thanh-tuu"
    ref="target"
    class="relative border-t border-white/5 py-24"
  >
    <div
      class="pointer-events-none absolute inset-x-0 top-1/2 h-px -translate-y-1/2 bg-gradient-to-r from-transparent via-brand/30 to-transparent"
      aria-hidden="true"
    />
    <div class="relative mx-auto max-w-7xl px-5 sm:px-8">
      <SectionHeading
        center
        eyebrow="Thành tựu nổi bật"
        title="Những con số biết nói"
        subtitle="Tổng hợp trực tiếp từ dữ liệu vận hành của hệ thống — cập nhật theo thời gian thực."
      />

      <div class="mt-14 grid grid-cols-2 gap-4 sm:gap-5 lg:grid-cols-3">
        <div
          v-for="(s, i) in stats"
          :key="s.key"
          class="relative overflow-hidden rounded-2xl border border-white/10 bg-white/[0.03] p-6 text-center transition-all duration-700 sm:p-8"
          :class="shown ? 'translate-y-0 opacity-100' : 'translate-y-6 opacity-0'"
          :style="{ transitionDelay: `${i * 90}ms` }"
        >
          <div class="font-display text-4xl font-extrabold text-white sm:text-5xl">
            <CountStat
              :value="valueOf(s.key)"
              :active="shown"
            />
            <span class="text-brand">{{ s.suffix }}</span>
          </div>
          <p class="mt-2.5 text-[13px] leading-snug text-white/55">
            {{ s.label }}
          </p>
        </div>
      </div>
    </div>
  </section>
</template>
