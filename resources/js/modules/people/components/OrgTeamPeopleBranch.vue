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

const hasMultipleSections = computed(
    () => roster.value.sectionGroups.filter((g) => g.people.length).length > 1,
);

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
        :role="roster.leader.roleTitle || roster.leader.role"
        :is-leader="true"
        @select="onSelect(roster.leader)"
      />
    </div>

    <div
      v-if="roster.leader && roster.sectionGroups.length"
      class="org-tree__connector org-tree__connector--stem"
      aria-hidden="true"
    />

    <div
      v-if="roster.sectionGroups.length"
      class="org-tree__members-section"
      :class="{ 'org-tree__members-section--columns': hasMultipleSections }"
    >
      <div
        v-for="group in roster.sectionGroups"
        :key="group.key"
        class="org-tree__section-column"
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
              :role="person.displayRole || person.roleTitle || person.branchLabel"
              :section-label="group.title ? null : person.sectionTitle"
              :is-leader="false"
              @select="onSelect(person)"
            />
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>
