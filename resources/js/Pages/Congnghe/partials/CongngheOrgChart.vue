<script setup>
import CongngheOrgChartBranch from './CongngheOrgChartBranch.vue';

defineProps({
    trees: { type: Array, default: () => [] },
});

const emit = defineEmits(['select-person']);

function onSelectPerson(payload) {
    emit('select-person', payload);
}
</script>

<template>
  <div
    class="cn-org-chart rounded-2xl border border-white/12 bg-white/[0.03] px-3 py-6 sm:rounded-3xl sm:px-6 sm:py-8"
    role="tree"
    aria-label="Sơ đồ tổ chức Phòng Công nghệ"
  >
    <CongngheOrgChartBranch
      v-for="root in trees"
      :key="root.id"
      :team="root"
      :depth="0"
      @select-person="onSelectPerson"
    />
  </div>
</template>

<style scoped>
.cn-org-chart {
    background:
        radial-gradient(120% 80% at 50% 0%, rgba(154, 0, 54, 0.12), transparent 55%),
        linear-gradient(180deg, rgba(15, 15, 22, 0.6) 0%, rgba(8, 8, 14, 0.85) 100%);
}
</style>
