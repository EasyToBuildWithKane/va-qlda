<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import ProfileInfoPanel from './ProfileInfoPanel.vue';

defineProps({
    insights: { type: Array, default: () => [] },
});

const toneClass = {
    emerald: 'bg-emerald-50 text-emerald-600 ring-emerald-100',
    sky: 'bg-sky-50 text-sky-600 ring-sky-100',
    violet: 'bg-violet-50 text-violet-600 ring-violet-100',
    amber: 'bg-amber-50 text-amber-600 ring-amber-100',
    brand: 'bg-brand/5 text-brand ring-brand/10',
};
</script>

<template>
  <ProfileInfoPanel
    title="Gợi ý phát triển"
    icon="sparkles"
    subtitle="Phân tích từ dữ liệu kỹ năng của bạn"
    section-key="profile-skill-insights"
    :collapsed-badge="insights.length ? `${insights.length} gợi ý` : null"
  >
    <div class="p-5">
      <ul
        v-if="insights.length"
        class="space-y-3"
      >
        <li
          v-for="(it, i) in insights"
          :key="i"
          class="flex items-start gap-3"
        >
          <span
            class="mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-lg ring-1 ring-inset"
            :class="toneClass[it.tone] || toneClass.brand"
          >
            <AppIcon
              :name="it.icon"
              :size="14"
            />
          </span>
          <p class="text-[13px] leading-relaxed text-slate-600">
            {{ it.text }}
          </p>
        </li>
      </ul>
      <p
        v-else
        class="text-[13px] text-slate-400"
      >
        Chưa có gợi ý phát triển từ dữ liệu kỹ năng hiện có.
      </p>
    </div>
  </ProfileInfoPanel>
</template>
