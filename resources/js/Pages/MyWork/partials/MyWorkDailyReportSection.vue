<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';

const props = defineProps({
    daily: { type: Object, default: null },
});

const open = defineModel('open', { type: Boolean, default: true });

const statusBadge = computed(() => {
    const d = props.daily;
    if (!d) return null;
    if (!d.hasReport) {
        return { label: 'Chưa soạn', color: 'amber' };
    }
    return {
        label: d.statusLabel ?? 'Nháp',
        color: d.statusColor ?? 'slate',
    };
});

const hint = computed(() => {
    const d = props.daily;
    if (!d) return '';
    if (!d.hasReport) {
        return 'Chưa có báo cáo cho ngày làm việc hôm nay — bấm để soạn và gắn công việc.';
    }
    if (d.needsAttention) {
        return d.reportTaskCount
            ? `${d.reportTaskCount} công việc đã gắn · hoàn thiện và nộp báo cáo.`
            : 'Đang soạn nháp — thêm dự án / task và nộp khi xong.';
    }
    return d.reportTaskCount
        ? `Đã nộp · ${d.reportTaskCount} công việc trong báo cáo.`
        : 'Đã nộp báo cáo hôm nay.';
});
</script>

<template>
  <section
    v-if="daily"
    class="mb-4 last:mb-0"
    aria-label="Báo cáo công việc hằng ngày"
  >
    <button
      type="button"
      class="flex w-full items-center gap-2 py-1.5 text-left"
      @click="open = !open"
    >
      <AppIcon
        :name="open ? 'chevron-down' : 'chevron-right'"
        :size="15"
        class="text-slate-400"
      />
      <AppIcon
        name="report-today"
        :size="15"
        :class="daily.needsAttention ? 'text-brand' : 'text-emerald-500'"
      />
      <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">
        Báo cáo công việc hằng ngày
      </span>
      <Badge
        v-if="statusBadge"
        :label="statusBadge.label"
        :color="statusBadge.color"
      />
      <span
        v-if="daily.isLate"
        class="rounded-full bg-rose-100 px-1.5 py-0.5 text-[10px] font-semibold text-rose-700 dark:bg-rose-950/50 dark:text-rose-300"
      >
        Nộp trễ
      </span>
    </button>

    <div
      v-show="open"
      class="mt-1.5"
    >
      <div class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-3 sm:flex-row sm:items-center sm:justify-between dark:border-slate-700 dark:bg-slate-900">
        <div class="min-w-0">
          <p class="text-sm text-slate-600 dark:text-slate-300">
            {{ hint }}
          </p>
          <p class="mt-0.5 text-[11px] text-slate-400">
            Ngày báo cáo: {{ daily.date }}
          </p>
        </div>
        <Link
          :href="daily.href"
          class="btn-primary inline-flex h-9 shrink-0 items-center justify-center gap-1.5 px-3 text-xs font-medium"
        >
          <AppIcon
            name="report-today"
            :size="15"
          />
          {{ daily.needsAttention ? 'Soạn / nộp báo cáo' : 'Xem báo cáo hôm nay' }}
        </Link>
      </div>
    </div>
  </section>
</template>
