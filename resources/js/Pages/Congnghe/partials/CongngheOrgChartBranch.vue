<script setup>
import { computed } from 'vue';
import Avatar from '@/shared/ui/Avatar.vue';
import CongngheOrgChartBranch from './CongngheOrgChartBranch.vue';
import { toIterableList, useOrgTeamRoster } from '@/modules/people/composables/useOrgTeamPeople.js';

const props = defineProps({
    team: { type: Object, required: true },
    depth: { type: Number, default: 0 },
});

const emit = defineEmits(['select-person']);

const children = computed(() => toIterableList(props.team.children));
const roster = useOrgTeamRoster(() => props.team);

const hasPeople = computed(() => roster.value.totalCount > 0);
const hasBody = computed(() => hasPeople.value || children.value.length > 0);

function personPayload(person, extra = {}) {
    return {
        employeeId: person.employeeId,
        name: person.name,
        avatar: person.avatar,
        roleTitle: person.roleTitle,
        email: person.email,
        sectionTitle: person.sectionTitle ?? extra.sectionTitle ?? null,
        teamName: props.team.name,
        isActive: true,
        isLeader: person.isLeader ?? false,
        ...extra,
    };
}

function openPerson(person) {
    emit('select-person', personPayload(person));
}

function openLeader() {
    const leader = roster.value.leader;
    if (!leader) {
        return;
    }
    openPerson(leader);
}
</script>

<template>
  <div
    class="cn-org-branch flex flex-col items-center"
    :class="depth === 0 ? 'cn-org-branch--root' : 'cn-org-branch--nested'"
    role="treeitem"
    :aria-expanded="hasBody ? 'true' : 'false'"
  >
    <!-- Nút đơn vị -->
    <div
      class="cn-org-node cn-org-node--unit relative z-[1] max-w-[min(100%,18rem)] text-center"
      :class="depth === 0 ? 'cn-org-node--unit-root' : ''"
    >
      <p class="font-mono text-[9px] font-semibold uppercase tracking-[0.18em] text-cyan-200/55">
        {{ depth === 0 ? 'Đơn vị' : 'Nhóm' }}
      </p>
      <p class="mt-0.5 font-display text-sm font-bold leading-snug text-white sm:text-base">
        {{ team.name }}
      </p>
    </div>

    <div
      v-if="hasBody"
      class="cn-org-stem"
      aria-hidden="true"
    />

    <div
      v-if="roster.leader || roster.sectionGroups.length"
      class="cn-org-roster w-full max-w-5xl"
    >
      <button
        v-if="roster.leader"
        type="button"
        class="cn-org-node cn-org-node--person cn-org-node--leader mx-auto flex max-w-xs items-center gap-3 text-left"
        @click="openLeader()"
      >
        <span class="relative shrink-0">
          <Avatar
            :name="roster.leader.name"
            :src="roster.leader.avatar"
            :size="44"
          />
          <span
            class="absolute -bottom-0.5 -right-0.5 grid h-4 w-4 place-items-center rounded-full bg-amber-400 text-[8px] text-amber-950 shadow"
            title="Quản lý"
          >
            ★
          </span>
        </span>
        <span class="min-w-0 flex-1">
          <span class="block truncate text-sm font-semibold text-white">{{ roster.leader.name }}</span>
          <span class="block truncate text-[11px] text-white/55">
            {{ roster.leader.roleTitle || 'Quản lý' }}
          </span>
        </span>
      </button>

      <div
        v-if="roster.sectionGroups.length"
        class="mt-4 space-y-4"
        :class="roster.leader ? 'pt-1' : ''"
      >
        <div
          v-for="group in roster.sectionGroups"
          :key="group.key"
          class="cn-org-section"
        >
          <p
            v-if="group.title"
            class="mb-2 text-center font-mono text-[9px] font-semibold uppercase tracking-[0.14em] text-white/40"
          >
            {{ group.title }}
          </p>
          <ul
            class="cn-org-people-grid mx-auto grid max-w-4xl list-none gap-2 p-0 sm:gap-2.5"
            :class="group.people.length === 1 ? 'grid-cols-1 place-items-center' : 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-4'"
          >
            <li
              v-for="person in group.people"
              :key="person.key"
            >
              <button
                type="button"
                class="cn-org-node cn-org-node--person flex h-full w-full flex-col items-center gap-2 px-2 py-2.5 text-center sm:px-2.5"
                @click="openPerson(person)"
              >
                <Avatar
                  :name="person.name"
                  :src="person.avatar"
                  :size="40"
                />
                <span class="min-w-0 w-full">
                  <span class="block truncate text-[12px] font-semibold leading-tight text-white sm:text-[13px]">
                    {{ person.name }}
                  </span>
                  <span
                    v-if="person.roleTitle || person.branchLabel"
                    class="mt-0.5 block truncate text-[10px] leading-snug text-white/50 sm:text-[11px]"
                  >
                    {{ person.roleTitle || person.branchLabel }}
                  </span>
                </span>
              </button>
            </li>
          </ul>
        </div>
      </div>
    </div>

    <div
      v-if="children.length"
      class="cn-org-children mt-5 w-full"
    >
      <div
        v-if="hasPeople"
        class="cn-org-stem cn-org-stem--to-children mx-auto"
        aria-hidden="true"
      />
      <div
        class="cn-org-children-row relative flex flex-wrap items-start justify-center gap-4 pt-4 sm:gap-5 lg:gap-6"
        role="group"
        :aria-label="`Đơn vị con của ${team.name}`"
      >
        <CongngheOrgChartBranch
          v-for="child in children"
          :key="child.id"
          :team="child"
          :depth="depth + 1"
          class="cn-org-child-wrap"
          @select-person="emit('select-person', $event)"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.cn-org-stem {
    width: 2px;
    height: 1.25rem;
    margin-top: 0.35rem;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.28), rgba(255, 255, 255, 0.08));
    border-radius: 1px;
}

.cn-org-stem--to-children {
    height: 1rem;
}

.cn-org-children-row::before {
    content: '';
    position: absolute;
    top: 0;
    left: 8%;
    right: 8%;
    height: 2px;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2) 15%, rgba(255, 255, 255, 0.2) 85%, transparent);
    border-radius: 1px;
}

.cn-org-child-wrap {
    flex: 1 1 min(100%, 16rem);
    max-width: 22rem;
}

.cn-org-child-wrap::before {
    content: '';
    display: block;
    width: 2px;
    height: 1rem;
    margin: 0 auto;
    background: rgba(255, 255, 255, 0.22);
}

.cn-org-node {
    border-radius: 14px;
    border: 1px solid rgba(255, 255, 255, 0.12);
    background: rgba(255, 255, 255, 0.04);
    transition: border-color 0.2s ease, background 0.2s ease, transform 0.2s ease;
}

.cn-org-node--unit {
    padding: 0.65rem 1rem;
    background: linear-gradient(135deg, rgba(154, 0, 54, 0.35), rgba(12, 14, 24, 0.92));
    border-color: rgba(255, 77, 141, 0.35);
    box-shadow: 0 8px 32px -12px rgba(154, 0, 54, 0.45);
}

.cn-org-node--unit-root {
    padding: 0.85rem 1.25rem;
}

.cn-org-node--person {
    cursor: pointer;
}

.cn-org-node--person:hover,
.cn-org-node--person:focus-visible {
    border-color: rgba(255, 77, 141, 0.45);
    background: rgba(255, 255, 255, 0.08);
    transform: translateY(-1px);
    outline: none;
}

.cn-org-node--leader {
    border-color: rgba(251, 191, 36, 0.35);
    background: rgba(251, 191, 36, 0.06);
}

.cn-org-node--leader:hover,
.cn-org-node--leader:focus-visible {
    border-color: rgba(251, 191, 36, 0.55);
    background: rgba(251, 191, 36, 0.1);
}

@media (prefers-reduced-motion: reduce) {
    .cn-org-node {
        transition: none;
    }

    .cn-org-node--person:hover,
    .cn-org-node--person:focus-visible {
        transform: none;
    }
}
</style>
