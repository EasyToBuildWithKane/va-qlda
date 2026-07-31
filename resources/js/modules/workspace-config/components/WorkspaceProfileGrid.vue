<script setup>
import WorkspaceProfileCard from '@/modules/workspace-config/components/WorkspaceProfileCard.vue';

defineProps({
    workspaces: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
    /** grid | list */
    layout: { type: String, default: 'grid' },
    /** comfortable | compact */
    density: { type: String, default: 'comfortable' },
    selectable: { type: Boolean, default: false },
    selectedCodes: { type: Array, default: () => [] },
});

const emit = defineEmits(['preview', 'toggle-select']);

function isSelected(code, selectedCodes) {
    return selectedCodes.includes(code);
}
</script>

<template>
  <div
    :class="layout === 'list'
      ? 'flex flex-col gap-3'
      : (density === 'compact'
        ? 'grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4'
        : 'grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3')"
  >
    <WorkspaceProfileCard
      v-for="ws in workspaces"
      :key="ws.department_code"
      :workspace="ws"
      :can-manage="canManage"
      :layout="layout"
      :density="density"
      :selectable="selectable"
      :selected="isSelected(ws.department_code, selectedCodes)"
      @preview="emit('preview', $event)"
      @toggle-select="emit('toggle-select', $event)"
    />
  </div>
</template>
