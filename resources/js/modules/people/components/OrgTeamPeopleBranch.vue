<script setup>
import OrgTeamPersonNode from '@/modules/people/components/OrgTeamPersonNode.vue';
import { useOrgTeamPeople } from '@/modules/people/composables/useOrgTeamPeople.js';

const props = defineProps({
    leader: { type: Object, default: null },
    members: { type: Array, default: () => [] },
});

const people = useOrgTeamPeople(() => ({
    leader: props.leader,
    members: props.members,
}));
</script>

<template>
  <div class="org-tree__people">
    <ul
      v-if="people.length"
      class="org-tree__people-list"
    >
      <li
        v-for="person in people"
        :key="person.key"
        class="org-tree__people-item"
      >
        <OrgTeamPersonNode
          :name="person.name"
          :avatar="person.avatar"
          :role="person.role"
          :is-leader="person.isLeader"
        />
      </li>
    </ul>
  </div>
</template>
