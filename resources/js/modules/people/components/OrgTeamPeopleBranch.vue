<script setup>
import OrgTeamPersonNode from '@/modules/people/components/OrgTeamPersonNode.vue';
import { useOrgTeamRoster } from '@/modules/people/composables/useOrgTeamPeople.js';

const props = defineProps({
    leader: { type: Object, default: null },
    members: { type: Array, default: () => [] },
    sections: { type: Array, default: () => [] },
});

const roster = useOrgTeamRoster(() => ({
    leader: props.leader,
    members: props.members,
    sections: props.sections,
}));
</script>

<template>
  <div
    v-if="roster.leader || roster.sectionGroups.length"
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
      v-if="roster.sectionGroups.length"
      class="org-tree__members-section"
    >
      <div
        v-for="group in roster.sectionGroups"
        :key="group.key"
        class="org-tree__section-block"
      >
        <p
          v-if="group.title"
          class="org-tree__section-title"
        >
          {{ group.title }}
        </p>
        <ul
          class="org-tree__members-row"
          :class="{ 'org-tree__members-row--multi': group.people.length > 1 }"
        >
          <li
            v-for="person in group.people"
            :key="person.key"
            class="org-tree__members-item"
          >
            <OrgTeamPersonNode
              :name="person.name"
              :avatar="person.avatar"
              :role="person.role"
              :is-leader="false"
            />
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>
