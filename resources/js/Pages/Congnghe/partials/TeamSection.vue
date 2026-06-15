<script setup>
import SectionHeading from './SectionHeading.vue';
import PersonAvatar from './PersonAvatar.vue';
import { useInView } from './motion.js';

defineProps({
    team: { type: Array, default: () => [] },
});

const { target, shown } = useInView({ threshold: 0.1 });
</script>

<template>
  <section
    id="doi-ngu"
    ref="target"
    class="relative border-t border-white/5 py-20"
  >
    <div class="mx-auto max-w-7xl px-5 sm:px-8">
      <SectionHeading
        eyebrow="Thành viên"
        title="Những con người đứng sau sản phẩm"
        subtitle="Đội ngũ kỹ sư, chuyên viên dữ liệu và vận hành sản phẩm của Phòng Công Nghệ."
      />

      <div
        v-if="team.length"
        class="mt-12 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6"
      >
        <div
          v-for="(member, i) in team"
          :key="member.id"
          class="group flex flex-col items-center rounded-2xl border border-white/10 bg-white/[0.03] p-5 text-center transition-all duration-500 hover:border-brand/40 hover:bg-white/[0.05]"
          :class="shown ? 'translate-y-0 opacity-100' : 'translate-y-5 opacity-0'"
          :style="{ transitionDelay: `${i * 50}ms` }"
        >
          <PersonAvatar
            :name="member.name"
            :src="member.avatar"
            size="lg"
            class="transition-transform group-hover:scale-105"
          />
          <p class="mt-3 line-clamp-1 text-sm font-semibold text-white">
            {{ member.name }}
          </p>
          <p class="mt-0.5 line-clamp-2 text-[12px] leading-snug text-white/45">
            {{ member.role || 'Thành viên' }}
          </p>
        </div>
      </div>

      <p
        v-else
        class="mt-12 rounded-2xl border border-dashed border-white/10 bg-white/[0.02] px-6 py-12 text-center text-sm text-white/45"
      >
        Chưa có thành viên nào được ghi nhận.
      </p>
    </div>
  </section>
</template>
