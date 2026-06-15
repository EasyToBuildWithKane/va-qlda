<script setup>
import { computed } from 'vue';
import OrgTeamTeamNode from '@/modules/people/components/OrgTeamTeamNode.vue';
import OrgTeamPeopleBranch from '@/modules/people/components/OrgTeamPeopleBranch.vue';
import { toIterableList } from '@/modules/people/composables/useOrgTeamPeople.js';
import '@/modules/people/styles/org-team-tree.css';
import '@/modules/people/styles/org-team-chart-canvas.css';
import '@/modules/people/styles/org-team-flow-cards.css';

const props = defineProps({
    node: { type: Object, required: true },
    editMode: { type: Boolean, default: false },
    canManage: { type: Boolean, default: false },
    depth: { type: Number, default: 0 },
});

const emit = defineEmits(['edit', 'add-child', 'delete', 'select-person']);

const childTeams = computed(() => toIterableList(props.node.children));
const hasChildren = computed(() => childTeams.value.length > 0);

const branchStyle = computed(() => ({
    '--org-depth': String(props.depth),
}));
</script>

<template>
  <li
    class="org-tree__branch"
    :style="branchStyle"
  >
    <div class="org-tree__stack org-tree__stack--flow">
      <OrgTeamTeamNode
        :node="node"
        :edit-mode="editMode"
        :can-manage="canManage"
        @edit="emit('edit', $event)"
        @add-child="emit('add-child', $event)"
        @delete="emit('delete', $event)"
      />

      <OrgTeamPeopleBranch
        :team-name="node.name"
        :leader="node.leader"
        :members="node.members"
        :sections="node.sections"
        @select-person="emit('select-person', $event)"
      />
    </div>

    <ul
      v-if="hasChildren"
      class="org-tree__children"
    >
      <OrgTeamChart
        v-for="child in childTeams"
        :key="child.id"
        :node="child"
        :edit-mode="editMode"
        :can-manage="canManage"
        :depth="depth + 1"
        @edit="emit('edit', $event)"
        @add-child="emit('add-child', $event)"
        @delete="emit('delete', $event)"
        @select-person="emit('select-person', $event)"
      />
    </ul>
  </li>
</template>
