<script setup>
import CongngheOrgChartBranch from './CongngheOrgChartBranch.vue';

defineProps({
    trees: { type: Array, default: () => [] },
    revealed: { type: Boolean, default: false },
});

const emit = defineEmits(['select-person']);

function onSelectPerson(payload) {
    emit('select-person', payload);
}
</script>

<template>
  <div
    class="cn-org-chart w-full max-w-none rounded-2xl px-1 py-5 sm:px-3 sm:py-6 lg:px-4 lg:py-7"
    :class="revealed ? 'cn-org-chart--revealed' : ''"
    role="tree"
    aria-label="Sơ đồ tổ chức Phòng Công nghệ"
  >
    <CongngheOrgChartBranch
      v-for="root in trees"
      :key="root.id"
      :team="root"
      :depth="0"
      :revealed="revealed"
      @select-person="onSelectPerson"
    />
  </div>
</template>

<style scoped>
.cn-org-chart {
    background:
        radial-gradient(120% 80% at 50% 0%, rgba(154, 0, 54, 0.14), transparent 55%),
        linear-gradient(180deg, rgba(15, 15, 22, 0.55) 0%, rgba(8, 8, 14, 0.82) 100%);
    box-shadow:
        0 24px 80px -32px rgba(154, 0, 54, 0.35),
        inset 0 1px 0 rgba(255, 255, 255, 0.06);
}

.cn-org-chart--revealed {
    animation: cn-org-chart-in 0.7s cubic-bezier(0.22, 1, 0.36, 1) backwards;
}

@keyframes cn-org-chart-in {
    from {
        opacity: 0;
        transform: translateY(20px) scale(0.98);
    }

    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@media (prefers-reduced-motion: reduce) {
    .cn-org-chart--revealed {
        animation: none;
    }
}
</style>
