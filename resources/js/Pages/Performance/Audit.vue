<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import EmptyState from '@/shared/ui/EmptyState.vue';
import PerformanceFilterBar from '@/modules/performance/components/PerformanceFilterBar.vue';
import PerformanceAuditSummaryBar from '@/modules/performance/components/PerformanceAuditSummaryBar.vue';
import AuditTimeline from '@/modules/performance/components/AuditTimeline.vue';
import { usePerformanceExport } from '@/modules/performance/composables/usePerformanceExport.js';

const props = defineProps({
    filter: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({}) },
    audit: { type: Object, default: null },
});

const { exportAudit } = usePerformanceExport();

const processing = ref(false);
let offStart;
let offFinish;
onMounted(() => {
    offStart = router.on('start', () => { processing.value = true; });
    offFinish = router.on('finish', () => { processing.value = false; });
});
onUnmounted(() => { offStart?.(); offFinish?.(); });

function onExportAudit() {
    if (props.audit) exportAudit(props.audit, props.filter);
}

const memberSubtitle = computed(() => {
    if (!props.audit?.member) return '';
    const parts = [props.audit.member.role].filter(Boolean);
    return parts.join(' · ');
});
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
      @export-excel="onExportAudit"
    />

    <template v-if="audit">
      <div
        class="transition-opacity"
        :class="processing ? 'opacity-60' : 'opacity-100'"
      >
        <PerformanceAuditSummaryBar :summary="audit.summary" />
      </div>

      <section
        class="card mb-4 flex flex-wrap items-center gap-4 p-5 transition-opacity"
        :class="processing ? 'opacity-60' : 'opacity-100'"
      >
        <Avatar
          :name="audit.member.name"
          :src="audit.member.avatar"
          :size="52"
        />
        <div class="min-w-0">
          <h2 class="font-display text-lg font-semibold text-slate-900">
            {{ audit.member.name }}
          </h2>
          <p
            v-if="memberSubtitle"
            class="text-[13px] text-slate-500"
          >
            {{ memberSubtitle }}
          </p>
        </div>
      </section>

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
