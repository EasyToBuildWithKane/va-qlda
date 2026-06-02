<script setup>
import { ref } from 'vue';
import RiskIssueDataTable from '@/Components/Project/Dashboard/RiskIssueDataTable.vue';

defineProps({
    projectId: { type: Number, required: true },
    projectCode: { type: String, default: 'DA' },
    projectName: { type: String, default: '' },
    blockers: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
    severityOptions: { type: Array, default: () => [] },
    statusOptions: { type: Array, default: () => [] },
    openCount: { type: Number, default: 0 },
    canManage: { type: Boolean, default: false },
    canContribute: { type: Boolean, default: false },
});

const emit = defineEmits(['saved', 'highlight-end']);

const tableRef = ref(null);

defineExpose({
    scrollHere: () => tableRef.value?.scrollHere(),
});
</script>

<template>
    <RiskIssueDataTable
        ref="tableRef"
        :project-id="projectId"
        :project-code="projectCode"
        :project-name="projectName"
        :blockers="blockers"
        :employees="employees"
        :severity-options="severityOptions"
        :status-options="statusOptions"
        :can-manage="canManage"
        :can-contribute="canContribute"
        @saved="(p) => emit('saved', p)"
        @highlight-end="emit('highlight-end')"
    />
</template>
