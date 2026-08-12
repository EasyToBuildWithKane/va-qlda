<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import BlockerFormModal from '@/modules/project/components/BlockerFormModal.vue';
import { useQuickBlockerReport } from '@/composables/useQuickBlockerReport';

const {
    showModal,
    initialDescription,
    pulse,
    canReport,
    projects,
    employees,
    severityOptions,
    statusOptions,
    defaultProjectId,
    lockProject,
    projectName,
    projectCode,
    canUploadAttachments,
    open,
    close,
} = useQuickBlockerReport();
</script>

<template>
  <template v-if="canReport">
    <button
      type="button"
      class="quick-blocker-report-btn relative grid h-9 w-9 shrink-0 place-items-center rounded-lg border border-rose-200 bg-rose-50 text-rose-600 shadow-sm transition hover:border-rose-300 hover:bg-rose-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-400"
      :class="{ 'quick-blocker-report-btn--pop': pulse }"
      title="Báo vướng mắc nhanh"
      aria-label="Báo vướng mắc nhanh — ghi nhận tại trang đang xem"
      @click="open"
    >
      <span
        class="pointer-events-none absolute inset-0 rounded-lg bg-rose-400/30 quick-blocker-report-ring"
        aria-hidden="true"
      />
      <AppIcon
        name="alert"
        :size="18"
        class="relative z-[1] quick-blocker-report-icon"
      />
    </button>

    <BlockerFormModal
      :show="showModal"
      :blocker="null"
      :initial-description="initialDescription"
      :projects="projects"
      :employees="employees"
      :severity-options="severityOptions"
      :status-options="statusOptions"
      :default-project-id="defaultProjectId"
      :lock-project="lockProject"
      :project-name="projectName"
      :project-code="projectCode"
      :can-upload-attachments="canUploadAttachments"
      @close="close"
      @saved="close"
    />
  </template>
</template>

<style scoped>
.quick-blocker-report-ring {
    animation: quick-blocker-ping 2s cubic-bezier(0, 0, 0.2, 1) infinite;
}

.quick-blocker-report-icon {
    animation: quick-blocker-wiggle 2.8s ease-in-out infinite;
}

.quick-blocker-report-btn--pop {
    animation: quick-blocker-pop 0.45s ease-out;
}

@keyframes quick-blocker-ping {
    0% {
        transform: scale(1);
        opacity: 0.55;
    }
    70% {
        transform: scale(1.35);
        opacity: 0;
    }
    100% {
        transform: scale(1.35);
        opacity: 0;
    }
}

@keyframes quick-blocker-wiggle {
    0%,
    88%,
    100% {
        transform: rotate(0deg);
    }
    90% {
        transform: rotate(-10deg);
    }
    94% {
        transform: rotate(10deg);
    }
    98% {
        transform: rotate(-4deg);
    }
}

@keyframes quick-blocker-pop {
    0% {
        transform: scale(1);
    }
    35% {
        transform: scale(1.12);
    }
    100% {
        transform: scale(1);
    }
}
</style>
