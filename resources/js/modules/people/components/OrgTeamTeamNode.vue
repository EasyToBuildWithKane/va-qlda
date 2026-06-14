<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import { useOrgTeamRoster, toIterableList } from '@/modules/people/composables/useOrgTeamPeople.js';

const props = defineProps({
    node: { type: Object, required: true },
    editMode: { type: Boolean, default: false },
    canManage: { type: Boolean, default: false },
});

const emit = defineEmits(['edit', 'add-child', 'delete']);

const levelClass = {
    1: 'org-team-node--l1',
    2: 'org-team-node--l2',
    3: 'org-team-node--l3',
};

function levelTone(level) {
    return levelClass[level] || 'org-team-node--l3';
}

const roster = useOrgTeamRoster(() => ({
    leader: props.node.leader,
    members: props.node.members,
    sections: props.node.sections,
}));

const childCount = computed(() => toIterableList(props.node.children).length);

const menuOpen = ref(false);
const rootRef = ref(null);

function closeMenu() {
    menuOpen.value = false;
}

function toggleMenu() {
    menuOpen.value = !menuOpen.value;
}

function onPointerDownOutside(e) {
    if (rootRef.value?.contains(e.target)) {
        return;
    }
    closeMenu();
}

watch(menuOpen, (open) => {
    if (open) {
        document.addEventListener('mousedown', onPointerDownOutside);
    } else {
        document.removeEventListener('mousedown', onPointerDownOutside);
    }
});

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onPointerDownOutside);
});
</script>

<template>
  <article
    ref="rootRef"
    class="org-team-node"
    :class="levelTone(node.level)"
  >
    <div class="org-team-node__head">
      <p
        v-if="node.level <= 2"
        class="org-team-node__level"
      >
        {{ node.level === 1 ? 'Ban / Khối' : 'Nhóm' }}
      </p>
      <h3 class="org-team-node__title">
        {{ node.name }}
      </h3>
      <div
        v-if="node.level > 1 && node.leader"
        class="org-team-node__leader-mini"
      >
        <Avatar
          :src="node.leader.avatar_path"
          :name="node.leader.name"
          :size="22"
        />
        <span class="org-team-node__leader-mini-name">{{ node.leader.name }}</span>
      </div>
    </div>

    <div class="org-team-node__stats">
      <span class="org-team-node__stat">
        <AppIcon
          name="member-profiles"
          :size="12"
        />
        {{ roster.totalCount }}
      </span>
      <span
        v-if="childCount"
        class="org-team-node__stat"
      >
        <AppIcon
          name="org-teams"
          :size="12"
        />
        {{ childCount }} con
      </span>
    </div>

    <div
      v-if="editMode && canManage && node.can?.update"
      class="org-team-node__manage"
    >
      <button
        type="button"
        class="org-team-node__manage-trigger"
        :aria-expanded="menuOpen"
        aria-haspopup="menu"
        aria-label="Thao tác nhóm"
        @click="toggleMenu"
      >
        <AppIcon
          name="more-horizontal"
          :size="16"
        />
      </button>
      <div
        v-if="menuOpen"
        class="org-team-node__menu"
        role="menu"
      >
        <button
          type="button"
          role="menuitem"
          class="org-team-node__menu-item"
          @click="closeMenu(); emit('edit', node)"
        >
          <AppIcon
            name="edit"
            :size="14"
          />
          Sửa nhóm
        </button>
        <button
          v-if="node.level < 2"
          type="button"
          role="menuitem"
          class="org-team-node__menu-item"
          @click="closeMenu(); emit('add-child', node)"
        >
          <AppIcon
            name="plus"
            :size="14"
          />
          Thêm nhóm con
        </button>
        <button
          v-if="node.can?.delete"
          type="button"
          role="menuitem"
          class="org-team-node__menu-item org-team-node__menu-item--danger"
          @click="closeMenu(); emit('delete', node)"
        >
          <AppIcon
            name="delete"
            :size="14"
          />
          Xoá nhóm
        </button>
      </div>
    </div>
  </article>
</template>

<style scoped>
.org-team-node {
    position: relative;
    z-index: 2;
    min-width: 13rem;
    max-width: 18rem;
    overflow: visible;
    background: #fff;
    border: 1px solid rgb(226 232 240);
    border-radius: 1rem;
    box-shadow:
        0 1px 2px rgb(15 23 42 / 0.06),
        0 8px 20px rgb(15 23 42 / 0.05);
}

.org-team-node__head {
    padding: 0.625rem 0.875rem 0.75rem;
    text-align: center;
}

.org-team-node__level {
    margin: 0 0 0.25rem;
    font-size: 0.625rem;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    line-height: 1.2;
}

.org-team-node__title {
    margin: 0;
    font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif;
    font-size: 0.9375rem;
    font-weight: 700;
    line-height: 1.35;
    color: rgb(15 23 42);
    overflow-wrap: anywhere;
}

.org-team-node--l1 {
    border-color: rgb(154 0 54 / 0.22);
    box-shadow:
        0 1px 2px rgb(15 23 42 / 0.05),
        0 4px 12px rgb(15 23 42 / 0.04);
}

.org-team-node--l1 .org-team-node__head {
    padding-top: 0.75rem;
    padding-bottom: 0.875rem;
    background: #fff;
    border-bottom: 1px solid rgb(241 245 249);
}

.org-team-node--l1 .org-team-node__level {
    color: rgb(154 0 54 / 0.8);
}

.org-team-node--l1 .org-team-node__title {
    font-size: 1rem;
    color: rgb(15 23 42);
}

.org-team-node__leader-mini {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.375rem;
    margin-top: 0.5rem;
}

.org-team-node__leader-mini-name {
    max-width: 10rem;
    font-size: 0.6875rem;
    font-weight: 500;
    color: rgb(100 116 139);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.org-team-node--l1 .org-team-node__leader-mini-name {
    color: rgb(100 116 139);
}

.org-team-node__stats {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 0.375rem;
    padding: 0.375rem 0.75rem 0.625rem;
    border-top: 1px solid rgb(241 245 249);
}

.org-team-node--l1 .org-team-node__stats {
    border-top-color: rgb(241 245 249);
}

.org-team-node__stat {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.125rem 0.5rem;
    font-size: 0.625rem;
    font-weight: 600;
    color: rgb(100 116 139);
    background: rgb(248 250 252);
    border-radius: 999px;
}

.org-team-node--l1 .org-team-node__stat {
    color: rgb(71 85 105);
    background: rgb(248 250 252);
    border: 1px solid rgb(226 232 240);
}

.org-team-node--l2 .org-team-node__stat {
    background: rgb(248 250 252);
    color: rgb(71 85 105);
    border: 1px solid rgb(226 232 240);
}

.org-team-node--l2 .org-team-node__head {
    background: #fff;
    border-bottom: 1px solid rgb(241 245 249);
}

.org-team-node--l2 .org-team-node__level {
    color: rgb(100 116 139);
}

.org-team-node--l2 .org-team-node__title {
    color: rgb(15 23 42);
}

.org-team-node--l3 .org-team-node__head {
    background: #fff;
    border-bottom: 1px solid rgb(241 245 249);
}

.org-team-node--l3 .org-team-node__level {
    color: rgb(100 116 139);
}

.org-team-node--l3 .org-team-node__title {
    font-size: 0.875rem;
    font-weight: 600;
    color: rgb(30 41 59);
}

.org-team-node__manage {
    position: relative;
    border-top: 1px solid rgb(241 245 249);
}

.org-team-node__manage-trigger {
    display: flex;
    width: 100%;
    align-items: center;
    justify-content: center;
    gap: 0.375rem;
    padding: 0.4rem 0.75rem;
    font-size: 0.6875rem;
    font-weight: 500;
    color: rgb(100 116 139);
    background: rgb(248 250 252);
}

.org-team-node__manage-trigger:hover {
    color: rgb(51 65 85);
    background: rgb(241 245 249);
}

.org-team-node__menu {
    position: absolute;
    left: 0.5rem;
    right: 0.5rem;
    top: calc(100% + 0.25rem);
    z-index: 20;
    overflow: hidden;
    border: 1px solid rgb(226 232 240);
    border-radius: 0.5rem;
    background: #fff;
    box-shadow: 0 8px 24px rgb(15 23 42 / 0.12);
}

.org-team-node__menu-item {
    display: flex;
    width: 100%;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.75rem;
    font-size: 0.75rem;
    font-weight: 500;
    color: rgb(51 65 85);
    text-align: left;
    background: #fff;
}

.org-team-node__menu-item:hover {
    background: rgb(248 250 252);
}

.org-team-node__menu-item--danger {
    color: rgb(190 18 60);
}

.org-team-node__menu-item--danger:hover {
    background: rgb(255 241 242);
}
</style>
