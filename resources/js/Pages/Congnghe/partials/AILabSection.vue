<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import SectionHeading from './SectionHeading.vue';
import GlassCard from './GlassCard.vue';
import MagneticButton from './MagneticButton.vue';
import CountStat from './CountStat.vue';
import { useInView } from './motion.js';

defineProps({
    metrics: { type: Object, default: () => ({}) },
});

const page = usePage();
const portal = computed(() => page.props.portal ?? { canEnterQlda: false, qldaHome: '/dashboard' });

const { target, shown } = useInView({ threshold: 0.2 });

const initiatives = [
    {
        title: 'Trợ lý AI nội bộ',
        body: 'Tự động hoá soạn thảo, tổng hợp báo cáo và hỗ trợ tra cứu tri thức cho đội ngũ.',
        icon: 'M12 2a5 5 0 0 1 5 5v1a5 5 0 0 1-1 9H8a5 5 0 0 1-1-9V7a5 5 0 0 1 5-5Z M9 21h6',
    },
    {
        title: 'Phân tích dữ liệu',
        body: 'Khai thác dữ liệu vận hành để phát hiện điểm nghẽn và đề xuất cải tiến.',
        icon: 'M3 3v18h18 M7 14l4-4 3 3 5-6',
    },
    {
        title: 'Quản trị tài khoản AI',
        body: 'Cấp phát, theo dõi chi phí và tối ưu việc sử dụng các công cụ AI trong toàn hệ thống.',
        icon: 'M20 7 9 18l-5-5',
    },
];
</script>

<template>
  <section
    id="ai-lab"
    ref="target"
    class="relative overflow-hidden py-28"
  >
    <div class="relative mx-auto max-w-7xl px-5 sm:px-8">
      <div class="grid gap-12 lg:grid-cols-[0.95fr_1.05fr] lg:items-center">
        <div>
          <SectionHeading
            eyebrow="AI · Innovation Lab"
            title="Đặt AI vào trung tâm vận hành"
            subtitle="Chúng tôi không chạy theo xu hướng — chúng tôi đưa AI vào những bài toán thật, tạo ra giá trị đo lường được mỗi ngày."
          />

          <GlassCard
            tilt
            :padded="false"
            class="mt-8 inline-flex items-center gap-4 px-6 py-5"
          >
            <span class="relative grid h-14 w-14 place-items-center rounded-2xl bg-gradient-to-br from-brand to-[#ff4d8d] text-white shadow-lg shadow-brand/30">
              <svg
                width="26"
                height="26"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
              ><path d="M12 2a5 5 0 0 1 5 5v1a5 5 0 0 1-1 9H8a5 5 0 0 1-1-9V7a5 5 0 0 1 5-5Z" /><path d="M9 21h6" /></svg>
              <span class="absolute inset-0 rounded-2xl ring-2 ring-brand/40 animate-cn-ping-ring" />
            </span>
            <div>
              <p class="font-display text-3xl font-extrabold text-white">
                <CountStat
                  :value="Number(metrics.aiAccounts ?? 0)"
                  :active="shown"
                />
              </p>
              <p class="text-[12.5px] text-white/55">
                tài khoản AI đang được quản lý &amp; tối ưu chi phí
              </p>
            </div>
          </GlassCard>

          <div
            v-if="portal.canEnterQlda"
            class="mt-6"
          >
            <MagneticButton
              href="/ai-accounts/dashboard"
              inertia
              variant="ghost"
              class="!px-5 !py-2.5 !text-[13px]"
            >
              Xem bảng điều khiển AI
              <svg
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              ><path d="M5 12h14M13 6l6 6-6 6" /></svg>
            </MagneticButton>
          </div>
        </div>

        <div class="grid gap-4">
          <GlassCard
            v-for="(item, i) in initiatives"
            :key="item.title"
            tilt
            :padded="false"
            class="flex items-start gap-4 p-6 transition-all duration-700"
            :class="shown ? 'translate-x-0 opacity-100' : 'translate-x-6 opacity-0'"
            :style="{ transitionDelay: `${i * 120}ms` }"
          >
            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-white/5 text-brand-300 ring-1 ring-white/10">
              <svg
                width="22"
                height="22"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
              ><path :d="item.icon" /></svg>
            </span>
            <div>
              <h3 class="font-display text-base font-bold text-white">
                {{ item.title }}
              </h3>
              <p class="mt-1.5 text-sm leading-relaxed text-white/55">
                {{ item.body }}
              </p>
            </div>
          </GlassCard>
        </div>
      </div>
    </div>
  </section>
</template>
