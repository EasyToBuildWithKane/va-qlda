<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import EmptyState from '@/shared/ui/EmptyState.vue';
import PerformanceFilterBar from '@/modules/performance/components/PerformanceFilterBar.vue';
import ProgressRing from '@/modules/performance/components/ProgressRing.vue';
import Sparkline from '@/modules/performance/components/Sparkline.vue';
import AuditTimeline from '@/modules/performance/components/AuditTimeline.vue';
import { usePerformanceExport } from '@/modules/performance/composables/usePerformanceExport.js';
import { tailwindToHex } from '@/modules/performance/composables/useChartTheme.js';

const props = defineProps({
    filter: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({}) },
    audit: { type: Object, default: null },
});

const { exportAudit, printReport } = usePerformanceExport();

const gradeTone = {
    S: 'bg-brand/10 text-brand',
    A: 'bg-emerald-50 text-emerald-600',
    B: 'bg-sky-50 text-sky-600',
    C: 'bg-amber-50 text-amber-600',
    D: 'bg-rose-50 text-rose-600',
};

const scoreColor = computed(() => {
    const s = props.audit?.summary?.avgScore ?? 0;
    if (s >= 80) return tailwindToHex('emerald');
    if (s >= 50) return tailwindToHex('brand');
    return tailwindToHex('rose');
});

// Trend chronological (builder trả newest-first) → đảo cho sparkline trái→phải.
const sparkValues = computed(() => [...(props.audit?.trend ?? [])].reverse().map((t) => t.commitmentRate));

const processing = ref(false);
let offStart;
let offFinish;
onMounted(() => {
    offStart = router.on('start', () => { processing.value = true; });
    offFinish = router.on('finish', () => { processing.value = false; });
});
onUnmounted(() => { offStart?.(); offFinish?.(); });
</script>

<template>
  <Head title="Audit nhân sự" />

  <AppLayout>
    <template #header>
      <PageHeader
        title="Audit nhân sự"
        :subtitle="`Timeline cam kết & kết quả theo tuần — ${filter.label || ''}`"
        icon="leaderboard"
        icon-color="brand"
      />
    </template>

    <PerformanceFilterBar
      :filter="filter"
      :options="options"
      :require-member="true"
      :processing="processing"
    >
      <template #actions>
        <button
          v-if="audit"
          type="button"
          class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50"
          @click="exportAudit(audit)"
        >
          <AppIcon
            name="export"
            :size="14"
          />
          Excel
        </button>
        <button
          v-if="audit"
          type="button"
          class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50"
          @click="printReport"
        >
          <AppIcon
            name="documents"
            :size="14"
          />
          In
        </button>
      </template>
    </PerformanceFilterBar>

    <template v-if="audit">
      <!-- Member summary -->
      <section
        class="card mb-4 flex flex-wrap items-center gap-5 p-5 transition-opacity"
        :class="processing ? 'opacity-60' : 'opacity-100'"
      >
        <Avatar
          :name="audit.member.name"
          :src="audit.member.avatar"
          :size="56"
        />
        <div class="min-w-0">
          <h2 class="font-display text-lg font-semibold text-slate-900">
            {{ audit.member.name }}
          </h2>
          <p
            v-if="audit.member.role"
            class="text-[13px] text-slate-500"
          >
            {{ audit.member.role }}
          </p>
        </div>

        <div class="mx-2 hidden h-12 w-px bg-slate-200 sm:block" />

        <div class="flex items-center gap-3">
          <ProgressRing
            :value="audit.summary.avgScore"
            :size="64"
            :stroke="6"
            :color="scoreColor"
          />
          <div>
            <p class="text-[11px] uppercase tracking-wide text-slate-400">
              Hiệu suất TB
            </p>
            <p class="flex items-center gap-2">
              <span class="font-display text-xl font-bold text-slate-900">{{ audit.summary.avgScore }}%</span>
              <span
                class="rounded-md px-1.5 py-0.5 text-[11px] font-bold"
                :class="gradeTone[audit.summary.grade]"
              >{{ audit.summary.grade }}</span>
            </p>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-x-6 gap-y-1 text-[13px]">
          <span class="text-slate-400">Cam kết</span>
          <span class="text-right font-semibold tabular-nums text-slate-700">{{ audit.summary.committed }}</span>
          <span class="text-slate-400">Hoàn thành</span>
          <span class="text-right font-semibold tabular-nums text-slate-700">{{ audit.summary.done }}</span>
          <span class="text-slate-400">Tỷ lệ cam kết</span>
          <span class="text-right font-semibold tabular-nums text-emerald-600">{{ audit.summary.commitmentRate }}%</span>
        </div>

        <div class="ml-auto hidden flex-col items-end gap-1 lg:flex">
          <p class="text-[11px] uppercase tracking-wide text-slate-400">
            Xu hướng cam kết
          </p>
          <Sparkline
            :values="sparkValues"
            :width="160"
            :height="40"
          />
        </div>
      </section>

      <!-- Timeline -->
      <div
        class="transition-opacity"
        :class="processing ? 'opacity-60' : 'opacity-100'"
      >
        <AuditTimeline :weeks="audit.weeks" />
      </div>
    </template>

    <EmptyState
      v-else
      icon="members"
      title="Chọn thành viên để xem audit"
      description="Dùng bộ lọc phía trên để chọn một thành viên. Hệ thống sẽ dựng timeline cam kết và kết quả theo từng tuần."
    />
  </AppLayout>
</template>
