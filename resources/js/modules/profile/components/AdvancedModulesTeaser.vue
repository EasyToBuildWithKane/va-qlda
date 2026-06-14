<script setup>
import AppIcon from '@/Components/AppIcon.vue';

// The deeper Talent Management modules from the product vision. They need data
// models that don't exist yet, so they are surfaced as a roadmap rather than
// faked — consistent with how the app marks "planned" features elsewhere.
const modules = [
    { icon: 'talent-score', label: 'Talent Score', desc: 'Điểm năng lực tổng hợp' },
    { icon: 'target', label: 'Phân tích khoảng cách kỹ năng', desc: 'Hiện có vs. yêu cầu' },
    { icon: 'performance', label: 'KPI & Hiệu suất', desc: 'Tháng · Quý · Năm' },
    { icon: 'career', label: 'Lộ trình phát triển', desc: 'Junior → Manager' },
    { icon: 'award', label: 'Chứng chỉ', desc: 'Văn bằng & hiệu lực' },
    { icon: 'learning', label: 'Trung tâm học tập', desc: 'Khoá học & mục tiêu' },
    { icon: 'message', label: 'Đánh giá 360°', desc: 'Self · Manager · Peer' },
    { icon: 'leaderboard', label: 'Kế hoạch kế nhiệm', desc: 'Sẵn sàng thăng tiến' },
];
</script>

<template>
  <section class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/60 shadow-sm">
    <header class="flex items-center gap-2.5 px-5 py-3.5">
      <div class="grid h-8 w-8 place-items-center rounded-lg bg-brand/10 text-brand">
        <AppIcon
          name="sparkles"
          :size="16"
        />
      </div>
      <div>
        <h2 class="text-sm font-semibold text-slate-800">
          Năng lực nâng cao
        </h2>
        <p class="text-[12px] text-slate-400">
          Các phân hệ Talent đang được phát triển
        </p>
      </div>
    </header>

    <ul class="grid grid-cols-1 gap-1.5 p-3 sm:grid-cols-2">
      <li
        v-for="m in modules"
        :key="m.label"
        class="flex items-center gap-2.5 rounded-xl bg-white/70 px-3 py-2.5 ring-1 ring-slate-100"
      >
        <div class="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-slate-100 text-slate-400">
          <AppIcon
            :name="m.icon"
            :size="14"
          />
        </div>
        <div class="min-w-0 flex-1">
          <p class="truncate text-[12.5px] font-medium text-slate-600">
            {{ m.label }}
          </p>
          <p class="truncate text-[11px] text-slate-400">
            {{ m.desc }}
          </p>
        </div>
        <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-400">Sắp ra mắt</span>
      </li>
    </ul>
  </section>
</template>
