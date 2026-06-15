<script setup>
import { computed } from 'vue';
import Avatar from '@/shared/ui/Avatar.vue';
import CongngheOrgChartBranch from './CongngheOrgChartBranch.vue';
import { toIterableList, useOrgTeamRoster } from '@/modules/people/composables/useOrgTeamPeople.js';
import {
    buildCongngheLeadershipLayout,
    shouldShowRoleSubtitle,
    staffSectionGroups,
} from './useCongngheOrgLayout.js';

const props = defineProps({
    team: { type: Object, required: true },
    depth: { type: Number, default: 0 },
    column: { type: Boolean, default: false },
    revealed: { type: Boolean, default: false },
});

const emit = defineEmits(['select-person']);

const children = computed(() => toIterableList(props.team.children));
const roster = useOrgTeamRoster(() => props.team);

const staffGroups = computed(() => staffSectionGroups(roster.value));

const leadershipLayout = computed(() => buildCongngheLeadershipLayout(roster.value, {
    nestedBranch: props.column || props.depth > 0,
}));

const managerCard = computed(() => leadershipLayout.value.managerCard);
const leadershipCards = computed(() => leadershipLayout.value.tierCards);
const leadershipEyebrow = computed(() => leadershipLayout.value.leadershipEyebrow);

const hasLeadership = computed(() => Boolean(managerCard.value) || leadershipCards.value.length > 0);
const hasStaff = computed(() => staffGroups.value.length > 0);

const hasChildren = computed(() => children.value.length > 0);
const hasBody = computed(() => hasLeadership.value || hasStaff.value || hasChildren.value);

const staffStaggerBase = computed(() => {
    let n = leadershipCards.value.length + 2;
    if (managerCard.value) {
        n += 1;
    }
    return n;
});

function staggerStyle(index, base = 0) {
    if (!props.revealed) {
        return {};
    }
    return { animationDelay: `${base + index * 75}ms` };
}

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

function openPerson(person, branchTitle = null) {
    emit('select-person', personPayload(person, {
        sectionTitle: branchTitle ?? person.sectionTitle,
    }));
}

function showRoleOnCard(card) {
    return shouldShowRoleSubtitle(card.branchTitle, card.person.roleTitle);
}
</script>

<template>
  <div
    class="cn-org-branch flex w-full flex-col items-center"
    :class="[
      depth === 0 && !column ? 'cn-org-branch--root' : '',
      column ? 'cn-org-branch--column' : '',
      revealed ? 'cn-org-branch--revealed' : '',
    ]"
    role="treeitem"
    :aria-expanded="hasBody ? 'true' : 'false'"
  >
    <div
      class="cn-org-node cn-org-node--unit cn-org-animate relative z-[1] text-center"
      :class="[
        depth === 0 && !column ? 'cn-org-node--unit-root max-w-md' : 'cn-org-node--unit-branch max-w-[14rem]',
      ]"
      :style="staggerStyle(0)"
    >
      <p class="font-mono text-[10px] font-semibold uppercase tracking-[0.16em] text-cyan-200/55">
        {{ depth === 0 && !column ? 'Đơn vị' : 'Nhánh' }}
      </p>
      <p
        class="mt-1 font-display font-bold leading-snug text-white"
        :class="depth === 0 && !column ? 'text-base sm:text-lg' : 'text-sm'"
      >
        {{ team.name }}
      </p>
    </div>

    <template v-if="hasBody">
      <div
        class="cn-org-stem cn-org-stem--pulse"
        aria-hidden="true"
      />

      <div
        v-if="hasLeadership"
        class="cn-org-leadership w-full"
        role="group"
        :aria-label="`${leadershipEyebrow} — ${team.name}`"
      >
        <template v-if="managerCard">
          <p class="cn-org-eyebrow mb-3 text-center text-brand-200/70">
            Quản lý
          </p>
          <div class="mx-auto mb-2 flex w-full max-w-xl justify-center">
            <button
              type="button"
              class="cn-org-node cn-org-node--person cn-org-node--lead-card cn-org-node--manager cn-org-animate flex w-full max-w-[24rem] flex-row items-center gap-3 px-3.5 py-3 text-left"
              :style="staggerStyle(0, 1)"
              @click="openPerson(managerCard.person, managerCard.branchTitle)"
            >
              <span class="cn-org-avatar-ring cn-org-avatar-ring--manager shrink-0">
                <Avatar
                  :name="managerCard.person.name"
                  :src="managerCard.person.avatar"
                  :size="52"
                />
              </span>
              <span class="min-w-0 flex-1">
                <span class="line-clamp-1 text-[10px] font-semibold uppercase tracking-wide text-brand-100/85">
                  {{ managerCard.branchTitle }}
                </span>
                <span class="mt-0.5 block text-sm font-semibold leading-tight text-white">
                  {{ managerCard.person.name }}
                </span>
                <span
                  v-if="showRoleOnCard(managerCard)"
                  class="mt-0.5 block text-[11px] leading-snug text-white/55"
                >
                  {{ managerCard.person.roleTitle }}
                </span>
              </span>
            </button>
          </div>
          <div
            v-if="leadershipCards.length"
            class="cn-org-stem cn-org-stem--short cn-org-stem--pulse mx-auto"
            aria-hidden="true"
          />
        </template>

        <template v-if="leadershipCards.length">
          <p
            class="cn-org-eyebrow mb-3 text-center text-amber-200/55"
            :class="managerCard ? 'mt-2' : ''"
          >
            {{ leadershipEyebrow }}
          </p>
          <ul
            class="cn-org-leadership-row mx-auto flex list-none flex-wrap justify-center gap-2 p-0 sm:gap-2.5"
          >
            <li
              v-for="(card, li) in leadershipCards"
              :key="card.key"
              class="w-full max-w-[20rem] sm:w-[calc(50%-0.3125rem)] lg:max-w-none lg:w-[calc(50%-0.3125rem)] xl:w-[calc(33.333%-0.5rem)]"
            >
              <button
                type="button"
                class="cn-org-node cn-org-node--person cn-org-node--lead-card cn-org-animate flex h-full w-full flex-row items-center gap-2.5 px-3 py-2.5 text-left"
                :style="staggerStyle(li, managerCard ? 2 : 1)"
                @click="openPerson(card.person, card.branchTitle)"
              >
                <span class="cn-org-avatar-ring shrink-0">
                  <Avatar
                    :name="card.person.name"
                    :src="card.person.avatar"
                    :size="44"
                  />
                </span>
                <span class="min-w-0 flex-1">
                  <span class="line-clamp-1 text-[10px] font-semibold uppercase tracking-wide text-amber-100/80">
                    {{ card.branchTitle }}
                  </span>
                  <span class="mt-0.5 block text-sm font-semibold leading-tight text-white">
                    {{ card.person.name }}
                  </span>
                  <span
                    v-if="showRoleOnCard(card)"
                    class="mt-0.5 block text-[11px] leading-snug text-white/55"
                  >
                    {{ card.person.roleTitle }}
                  </span>
                </span>
              </button>
            </li>
          </ul>
        </template>
      </div>

      <div
        v-if="hasStaff"
        class="cn-org-staff mt-4 w-full"
        :class="hasLeadership ? 'pt-1' : ''"
      >
        <div
          v-if="hasLeadership && hasStaff"
          class="cn-org-stem cn-org-stem--short cn-org-stem--pulse mx-auto"
          aria-hidden="true"
        />
        <p
          v-if="depth === 0"
          class="cn-org-eyebrow mb-4 text-center text-white/40"
        >
          Nhân sự theo nhánh
        </p>
        <div class="space-y-5">
          <section
            v-for="(group, gi) in staffGroups"
            :key="group.key"
            class="cn-org-staff-branch cn-org-animate"
            :style="staggerStyle(gi, staffStaggerBase)"
          >
            <header class="mb-2.5 text-center">
              <h4 class="font-mono text-[10px] font-semibold uppercase tracking-[0.14em] text-cyan-200/70">
                {{ group.title || 'Thành viên' }}
              </h4>
            </header>
            <ul
              class="mx-auto flex list-none flex-wrap justify-center gap-2 p-0 sm:gap-2.5"
            >
              <li
                v-for="(person, pi) in group.people"
                :key="person.key"
                class="w-full max-w-[18rem] sm:w-[calc(50%-0.3125rem)] lg:max-w-none lg:w-[calc(33.333%-0.5rem)]"
              >
                <button
                  type="button"
                  class="cn-org-node cn-org-node--person cn-org-node--staff flex w-full flex-row items-center gap-2.5 px-3 py-2.5 text-left"
                  :style="staggerStyle(pi, staffStaggerBase + gi * 2 + 1)"
                  @click="openPerson(person, group.title)"
                >
                  <Avatar
                    class="shrink-0"
                    :name="person.name"
                    :src="person.avatar"
                    :size="40"
                  />
                  <span class="min-w-0 flex-1">
                    <span class="block text-sm font-semibold leading-snug text-white">
                      {{ person.name }}
                    </span>
                    <span
                      v-if="shouldShowRoleSubtitle(group.title, person.roleTitle || person.branchLabel)"
                      class="mt-0.5 block text-[11px] leading-snug text-white/50 line-clamp-2"
                    >
                      {{ person.roleTitle || person.branchLabel }}
                    </span>
                  </span>
                </button>
              </li>
            </ul>
          </section>
        </div>
      </div>

      <div
        v-if="hasChildren"
        class="cn-org-children mt-5 w-full"
      >
        <div
          v-if="hasLeadership || hasStaff"
          class="cn-org-stem cn-org-stem--to-children cn-org-stem--pulse mx-auto"
          aria-hidden="true"
        />
        <p
          v-if="depth === 0"
          class="cn-org-eyebrow mb-4 text-center text-white/40"
        >
          Phân nhánh chuyên môn
        </p>
        <div
          class="cn-org-children-grid relative mx-auto grid w-full max-w-none grid-cols-1 gap-3 pt-3 sm:gap-4"
          :class="children.length >= 3 ? 'sm:grid-cols-2 xl:grid-cols-3' : children.length >= 2 ? 'sm:grid-cols-2' : ''"
          role="group"
          :aria-label="`Phân nhánh của ${team.name}`"
        >
          <CongngheOrgChartBranch
            v-for="(child, ci) in children"
            :key="child.id"
            :team="child"
            :depth="depth + 1"
            :revealed="revealed"
            column
            class="cn-org-child-column cn-org-animate"
            :style="staggerStyle(ci, staffStaggerBase + staffGroups.length + 2)"
            @select-person="emit('select-person', $event)"
          />
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.cn-org-eyebrow {
    font-family: ui-monospace, monospace;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.14em;
    text-transform: uppercase;
}

.cn-org-stem {
    width: 2px;
    height: 1.5rem;
    margin-top: 0.5rem;
    background: linear-gradient(180deg, rgba(255, 77, 141, 0.55), rgba(255, 255, 255, 0.08));
    border-radius: 1px;
}

.cn-org-stem--short {
    height: 1rem;
    margin-bottom: 0.65rem;
}

.cn-org-stem--to-children {
    height: 1.15rem;
}

.cn-org-stem--pulse {
    animation: cn-org-stem-pulse 2.8s ease-in-out infinite;
}

@keyframes cn-org-stem-pulse {
    0%,
    100% {
        opacity: 0.65;
        filter: drop-shadow(0 0 0 transparent);
    }

    50% {
        opacity: 1;
        filter: drop-shadow(0 0 6px rgba(255, 77, 141, 0.45));
    }
}

.cn-org-children-grid::before {
    content: '';
    position: absolute;
    top: 0;
    left: 4%;
    right: 4%;
    height: 2px;
    background: linear-gradient(
        90deg,
        transparent,
        rgba(255, 77, 141, 0.35) 15%,
        rgba(56, 189, 248, 0.25) 50%,
        rgba(255, 77, 141, 0.35) 85%,
        transparent
    );
    animation: cn-org-line-shimmer 4s ease-in-out infinite;
}

@keyframes cn-org-line-shimmer {
    0%,
    100% {
        opacity: 0.5;
    }

    50% {
        opacity: 1;
    }
}

.cn-org-child-column {
    position: relative;
    padding-top: 0.85rem;
}

.cn-org-child-column::before {
    content: '';
    position: absolute;
    top: 0;
    left: 50%;
    width: 2px;
    height: 0.85rem;
    transform: translateX(-50%);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.35), rgba(255, 255, 255, 0.06));
}

.cn-org-branch--column {
    border-radius: 1rem;
    background: linear-gradient(165deg, rgba(255, 255, 255, 0.04), rgba(8, 9, 16, 0.65));
    padding: 0.75rem 0.85rem 1rem;
    box-shadow: 0 12px 36px -20px rgba(0, 0, 0, 0.65);
}

@media (min-width: 640px) {
    .cn-org-branch--column {
        padding: 0.9rem 1rem 1.15rem;
    }
}

.cn-org-leadership-row {
    max-width: none;
    width: 100%;
}

.cn-org-node--manager {
    border-color: rgba(255, 77, 141, 0.4);
    background: linear-gradient(165deg, rgba(154, 0, 54, 0.22), rgba(255, 255, 255, 0.05));
}

.cn-org-avatar-ring--manager {
    background: linear-gradient(135deg, rgba(255, 77, 141, 0.65), rgba(251, 191, 36, 0.45));
}

.cn-org-node {
    border: none;
    border-radius: 1rem;
    background: rgba(255, 255, 255, 0.04);
    box-shadow:
        0 12px 40px -20px rgba(0, 0, 0, 0.75),
        inset 0 1px 0 rgba(255, 255, 255, 0.07);
    transition:
        transform 0.28s cubic-bezier(0.22, 1, 0.36, 1),
        box-shadow 0.28s ease,
        background 0.28s ease;
}

.cn-org-node--unit {
    padding: 0.85rem 1.25rem;
    background: linear-gradient(135deg, rgba(154, 0, 54, 0.42), rgba(12, 14, 24, 0.88));
    box-shadow:
        0 20px 56px -20px rgba(154, 0, 54, 0.55),
        inset 0 1px 0 rgba(255, 255, 255, 0.1);
}

.cn-org-node--unit-root {
    padding: 0.75rem 1.15rem;
}

.cn-org-node--unit-branch {
    padding: 0.65rem 1rem;
    background: linear-gradient(135deg, rgba(56, 189, 248, 0.14), rgba(12, 14, 24, 0.92));
    box-shadow: 0 12px 36px -18px rgba(56, 189, 248, 0.25);
}

.cn-org-node--person {
    cursor: pointer;
}

.cn-org-node--lead-card {
    background: linear-gradient(165deg, rgba(251, 191, 36, 0.12), rgba(255, 255, 255, 0.04));
    box-shadow:
        0 16px 48px -22px rgba(251, 191, 36, 0.35),
        inset 0 1px 0 rgba(255, 255, 255, 0.08);
}

.cn-org-node--staff {
    min-height: 0;
    justify-content: flex-start;
}

.cn-org-avatar-ring {
    display: inline-flex;
    padding: 3px;
    border-radius: 9999px;
    background: linear-gradient(135deg, rgba(251, 191, 36, 0.55), rgba(255, 77, 141, 0.45));
    box-shadow: 0 0 24px -4px rgba(255, 77, 141, 0.45);
}

.cn-org-node--person:hover,
.cn-org-node--person:focus-visible {
    transform: translateY(-4px) scale(1.02);
    background: rgba(255, 255, 255, 0.09);
    box-shadow:
        0 24px 56px -20px rgba(154, 0, 54, 0.45),
        0 0 0 1px rgba(255, 77, 141, 0.25),
        inset 0 1px 0 rgba(255, 255, 255, 0.12);
    outline: none;
}

.cn-org-node--lead-card:hover,
.cn-org-node--lead-card:focus-visible {
    box-shadow:
        0 28px 60px -18px rgba(251, 191, 36, 0.4),
        0 0 0 1px rgba(251, 191, 36, 0.35),
        inset 0 1px 0 rgba(255, 255, 255, 0.14);
}

.cn-org-branch--revealed .cn-org-animate {
    animation: cn-org-card-rise 0.65s cubic-bezier(0.22, 1, 0.36, 1) backwards;
}

@keyframes cn-org-card-rise {
    from {
        opacity: 0;
        transform: translateY(22px) scale(0.96);
    }

    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.cn-org-branch:not(.cn-org-branch--revealed) .cn-org-animate {
    opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
    .cn-org-node {
        transition: none;
    }

    .cn-org-node--person:hover,
    .cn-org-node--person:focus-visible {
        transform: none;
    }

    .cn-org-stem--pulse,
    .cn-org-children-grid::before {
        animation: none;
    }

    .cn-org-branch--revealed .cn-org-animate {
        animation: none;
        opacity: 1;
    }

    .cn-org-branch:not(.cn-org-branch--revealed) .cn-org-animate {
        opacity: 1;
    }
}
</style>
