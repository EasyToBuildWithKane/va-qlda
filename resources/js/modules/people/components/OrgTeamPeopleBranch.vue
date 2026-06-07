<script setup>
import { computed } from 'vue';
import OrgTeamPersonNode from '@/modules/people/components/OrgTeamPersonNode.vue';
import { useOrgTeamRoster } from '@/modules/people/composables/useOrgTeamPeople.js';

const props = defineProps({
    teamName: { type: String, required: true },
    leader: { type: Object, default: null },
    members: { type: Array, default: () => [] },
    sections: { type: Array, default: () => [] },
});

const emit = defineEmits(['select-person']);

const roster = useOrgTeamRoster(() => ({
    leader: props.leader,
    members: props.members,
    sections: props.sections,
}));

const memberCards = computed(() => {
    const cards = [];
    for (const group of roster.value.sectionGroups) {
        for (const person of group.people) {
            cards.push({
                ...person,
                displayRole: group.title || person.role,
                sectionLabel: group.title,
            });
        }
    }

    return cards;
});

function withTeamContext(person) {
    return {
        ...person,
        teamName: props.teamName,
        avatar: person.avatar,
    };
}

function onSelect(person) {
    emit('select-person', withTeamContext(person));
}
</script>

<template>
  <div
    v-if="roster.leader || memberCards.length"
    class="org-tree__people"
  >
    <div
      v-if="roster.leader"
      class="org-tree__leader"
    >
      <OrgTeamPersonNode
        :name="roster.leader.name"
        :avatar="roster.leader.avatar"
        :role="roster.leader.roleTitle || roster.leader.role"
        :is-leader="true"
        @select="onSelect(roster.leader)"
      />
    </div>

    <div
      v-if="memberCards.length"
      class="org-tree__members-section"
    >
      <ul
        class="org-tree__members-row"
        :class="{ 'org-tree__members-row--multi': memberCards.length > 1 }"
      >
        <li
          v-for="person in memberCards"
          :key="person.key"
          class="org-tree__members-item"
        >
          <OrgTeamPersonNode
            :name="person.name"
            :avatar="person.avatar"
            :role="person.displayRole || person.roleTitle"
            :section-label="person.sectionLabel"
            :is-leader="false"
            @select="onSelect(person)"
          />
        </li>
      </ul>
    </div>
  </div>
</template>
