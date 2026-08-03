<script setup>
import WorkspaceProfileCard from '@/modules/workspace-config/components/WorkspaceProfileCard.vue';

defineProps({
    workspaces: { type: Array, default: () => [] },
    selectable: { type: Boolean, default: false },
    selectedCodes: { type: Array, default: () => [] },
    visibleFields: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['preview', 'toggle-select']);

function isSelected(code, selectedCodes) {
    return selectedCodes.includes(code);
}
</script>

<template>
  <div class="grid grid-cols-1 gap-4 md:grid-cols-2 md:gap-5 xl:grid-cols-3">
    <WorkspaceProfileCard
      v-for="ws in workspaces"
      :key="ws.department_code"
      :workspace="ws"
      :selectable="selectable"
      :selected="isSelected(ws.department_code, selectedCodes)"
      :visible-fields="visibleFields"
      @preview="emit('preview', $event)"
      @toggle-select="emit('toggle-select', $event)"
    />
  </div>
</template>
