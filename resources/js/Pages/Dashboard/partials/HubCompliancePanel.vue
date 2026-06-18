<script setup>
/**
 * Tuân thủ báo cáo ngày toàn nhóm (tuần hiện tại) — gauge tròn + số liệu.
 */
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import ProgressRing from './ProgressRing.vue';

const props = defineProps({
    compliance: { type: Object, default: () => ({}) },
});

const rate = computed(() => props.compliance.teamRate ?? 0);

const ringColor = computed(() => {
    if (rate.value >= 90) return '#10b981';
    if (rate.value >= 70) return '#f59e0b';
    return '#f43f5e';
});

const toneText = computed(() => {
    if (rate.value >= 90) return 'Tuân thủ tốt';
    if (rate.value >= 70) return 'Cần nhắc nhở';
    return 'Cần cải thiện';
});
</script>

<template>
  <div class="card flex flex-col p-5">
    <div class="mb-4 flex items-center justify-between">
      <h3 class="flex items-center gap-2 font-display font-semibold text-slate-800">
        <AppIcon
          name="daily"
          :size="16"
          class="text-brand"
        />
        Tuân thủ báo cáo ngày
      </h3>
      <Link
        href="/daily-reports/review"
        class="text-xs font-medium text-brand hover:underline"
      >
        Xem →
      </Link>
    </div>

    <div class="flex flex-1 items-center gap-5">
      <ProgressRing
        :value="rate"
        :size="104"
        :stroke="10"
        :color="ringColor"
      />

      <div class="min-w-0 flex-1 space-y-2.5">
        <div>
          <p class="font-display text-sm font-semibold text-slate-800">
            {{ toneText }}
          </p>
          <p class="text-[11px] text-slate-400">
            Tuần {{ compliance.periodLabel }}
          </p>
        </div>

        <dl class="space-y-1.5 text-[12px]">
          <div class="flex items-center justify-between">
            <dt class="text-slate-500">
              Đã nộp
            </dt>
            <dd class="font-semibold tabular-nums text-slate-800">
              {{ compliance.submitted ?? 0 }}/{{ compliance.expected ?? 0 }}
            </dd>
          </div>
          <div class="flex items-center justify-between">
            <dt class="text-slate-500">
              Nhân sự
            </dt>
            <dd class="font-semibold tabular-nums text-slate-800">
              {{ compliance.people ?? 0 }} người
            </dd>
          </div>
          <div class="flex items-center justify-between">
            <dt class="text-slate-500">
              Kỳ vọng / người
            </dt>
            <dd class="font-semibold tabular-nums text-slate-800">
              {{ compliance.expectedPerPerson ?? 0 }} báo cáo
            </dd>
          </div>
        </dl>
      </div>
    </div>
  </div>
</template>
