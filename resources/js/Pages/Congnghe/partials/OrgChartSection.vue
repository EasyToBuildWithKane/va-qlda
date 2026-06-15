<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import SectionHeading from './SectionHeading.vue';
import OrgGraph from '@/modules/people/components/OrgGraph.vue';

const props = defineProps({
    overview: { type: Object, default: () => ({}) },
    forest: { type: Array, default: () => [] },
});

const page = usePage();
const portal = computed(() => page.props.portal ?? { canEnterQlda: false, qldaHome: '/dashboard' });

const roots = computed(() => props.forest ?? []);

const graphFilter = { query: '', rootId: null, role: 'all', status: 'active' };

const stats = computed(() => [
    { label: 'Thành viên', value: props.overview?.people_total ?? 0 },
    { label: 'Nhóm', value: props.overview?.teams_total ?? 0 },
    { label: 'Quản lý', value: props.overview?.leaders_total ?? 0 },
    { label: 'Đang hoạt động', value: `${props.overview?.active_ratio ?? 0}%` },
]);
</script>

<template>
  <section
    id="to-chuc"
    class="relative py-28"
  >
    <div class="mx-auto max-w-7xl px-5 sm:px-8">
      <SectionHeading
        eyebrow="Đội ngũ · Sơ đồ tổ chức"
        title="Cấu trúc vận hành"
        subtitle="Nhân sự phòng theo sơ đồ tổ chức thực tế — quản lý, nhánh và thành viên."
      />

      <div class="mt-10 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <div
          v-for="s in stats"
          :key="s.label"
          class="rounded-2xl border border-white/10 bg-white/[0.03] px-5 py-4 text-center"
        >
          <p class="font-display text-2xl font-bold text-white sm:text-3xl">
            {{ s.value }}
          </p>
          <p class="mt-1 font-mono text-[10.5px] uppercase tracking-wider text-white/45">
            {{ s.label }}
          </p>
        </div>
      </div>

      <div
        v-if="roots.length"
        class="congnghe-org-graph mt-8"
      >
        <OrgGraph
          :trees="roots"
          :filter="graphFilter"
          initial-expand-all
        />
      </div>

      <p
        v-else
        class="mt-8 rounded-2xl border border-dashed border-white/10 bg-white/[0.02] px-6 py-12 text-center text-sm text-white/45"
      >
        Sơ đồ tổ chức chưa được thiết lập.
      </p>

      <div
        v-if="portal.canEnterQlda"
        class="mt-8"
      >
        <a
          href="/org-teams"
          class="inline-flex items-center gap-2 text-sm font-semibold text-brand transition hover:text-[#ff4d8d]"
        >
          Xem sơ đồ tổ chức đầy đủ
          <svg
            width="16"
            height="16"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          ><path d="M5 12h14M13 6l6 6-6 6" /></svg>
        </a>
      </div>
    </div>
  </section>
</template>

<style scoped>
.congnghe-org-graph :deep(.org-graph__btn) {
    background: rgba(255, 255, 255, 0.06);
    border-color: rgba(255, 255, 255, 0.12);
    color: rgba(255, 255, 255, 0.75);
}

.congnghe-org-graph :deep(.org-graph__btn:hover) {
    background: rgba(154, 0, 54, 0.2);
    border-color: rgba(255, 77, 141, 0.45);
    color: #fff;
}

.congnghe-org-graph :deep(.org-graph__viewport) {
    border-color: rgba(255, 255, 255, 0.12);
    background:
        radial-gradient(120% 80% at 50% -10%, rgba(30, 30, 42, 0.95) 0%, rgba(15, 15, 22, 0.98) 55%, #0b0b12 100%);
}

.congnghe-org-graph :deep(.org-graph__grid) {
    background-image:
        linear-gradient(rgba(255, 255, 255, 0.06) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255, 255, 255, 0.06) 1px, transparent 1px);
}

.congnghe-org-graph :deep(.org-graph__zoom) {
    background: rgba(15, 15, 22, 0.88);
    border-color: rgba(255, 255, 255, 0.12);
}

.congnghe-org-graph :deep(.org-graph__zoombtn) {
    color: rgba(255, 255, 255, 0.7);
}

.congnghe-org-graph :deep(.org-graph__zoombtn:hover) {
    background: rgba(154, 0, 54, 0.25);
    color: #fff;
}

.congnghe-org-graph :deep(.org-graph__zoomval) {
    color: rgba(255, 255, 255, 0.45);
}
</style>
