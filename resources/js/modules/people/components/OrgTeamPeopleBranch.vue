<script setup>
import OrgTeamPersonNode from '@/modules/people/components/OrgTeamPersonNode.vue';
import { useOrgTeamRoster } from '@/modules/people/composables/useOrgTeamPeople.js';

const props = defineProps({
    leader: { type: Object, default: null },
    members: { type: Array, default: () => [] },
});

const roster = useOrgTeamRoster(() => ({
    leader: props.leader,
    members: props.members,
}));
</script>

<template>
  <div
    v-if="roster.leader || roster.branches.length"
    class="org-tree__people"
  >
    <div
      v-if="roster.leader"
      class="org-tree__leader"
    >
      <OrgTeamPersonNode
        :name="roster.leader.name"
        :avatar="roster.leader.avatar"
        :role="roster.leader.role"
        :is-leader="true"
      />
    </div>

    <div
      v-if="roster.branches.length"
      class="org-tree__member-branches"
      :class="{ 'org-tree__member-branches--solo': roster.branches.length === 1 }"
    >
      <ul class="org-tree__branch-columns">
        <li
          v-for="branch in roster.branches"
          :key="branch.label"
          class="org-tree__branch-column"
        >
          <p class="org-tree__branch-label">
            {{ branch.label }}
          </p>
          <ul class="org-tree__branch-members">
            <li
              v-for="person in branch.people"
              :key="person.key"
              class="org-tree__branch-member"
            >
              <OrgTeamPersonNode
                :name="person.name"
                :avatar="person.avatar"
                :role="null"
                :is-leader="false"
              />
            </li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</template>
