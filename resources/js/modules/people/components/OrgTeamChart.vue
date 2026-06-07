<script setup>
import { computed } from 'vue';
import OrgTeamTeamNode from '@/modules/people/components/OrgTeamTeamNode.vue';
import OrgTeamPeopleBranch from '@/modules/people/components/OrgTeamPeopleBranch.vue';
import { toIterableList } from '@/modules/people/composables/useOrgTeamPeople.js';
import '@/modules/people/styles/org-team-tree.css';

const props = defineProps({
    node: { type: Object, required: true },
    canManage: { type: Boolean, default: false },
});

const emit = defineEmits(['edit', 'add-child', 'delete']);

const childTeams = computed(() => toIterableList(props.node.children));
const hasChildren = computed(() => childTeams.value.length > 0);
</script>

<template>
  <li class="org-tree__branch">
    <div class="org-tree__stack">
      <OrgTeamTeamNode
        :node="node"
        :can-manage="canManage"
        @edit="emit('edit', $event)"
        @add-child="emit('add-child', $event)"
        @delete="emit('delete', $event)"
      />

      <OrgTeamPeopleBranch
        :leader="node.leader"
        :members="node.members"
        :sections="node.sections"
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
        :can-manage="canManage"
        @edit="emit('edit', $event)"
        @add-child="emit('add-child', $event)"
        @delete="emit('delete', $event)"
      />
    </ul>
  </li>
</template>
