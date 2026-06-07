<script setup>
import AppIcon from '@/Components/AppIcon.vue';

defineProps({
    node: { type: Object, required: true },
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
</script>

<template>
  <article
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
    </div>

    <div
      v-if="canManage && node.can?.update"
      class="org-team-node__actions"
    >
      <button
        type="button"
        class="org-team-node__btn"
        @click="emit('edit', node)"
      >
        <AppIcon
          name="edit"
          :size="12"
        />
        Sửa
      </button>
      <button
        v-if="node.level < 2"
        type="button"
        class="org-team-node__btn"
        @click="emit('add-child', node)"
      >
        <AppIcon
          name="plus"
          :size="12"
        />
        Nhóm bên dưới
      </button>
      <button
        v-if="node.can?.delete"
        type="button"
        class="org-team-node__btn org-team-node__btn--danger"
        @click="emit('delete', node)"
      >
        <AppIcon
          name="delete"
          :size="12"
        />
        Xoá
      </button>
    </div>
  </article>
</template>

<style scoped>
.org-team-node {
    position: relative;
    z-index: 2;
    min-width: 12rem;
    max-width: 17rem;
    overflow: hidden;
    background: #fff;
    border: 1px solid rgb(226 232 240);
    border-radius: 0.875rem;
    box-shadow:
        0 1px 2px rgb(15 23 42 / 0.06),
        0 4px 14px rgb(15 23 42 / 0.05);
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
    border-color: rgb(154 0 54 / 0.28);
    box-shadow:
        0 1px 2px rgb(154 0 54 / 0.08),
        0 6px 18px rgb(15 23 42 / 0.06);
}

.org-team-node--l1 .org-team-node__head {
    padding-top: 0.75rem;
    padding-bottom: 0.875rem;
    background: linear-gradient(165deg, #9a0036 0%, #810030 100%);
}

.org-team-node--l1 .org-team-node__level {
    color: rgb(255 255 255 / 0.72);
}

.org-team-node--l1 .org-team-node__title {
    font-size: 1rem;
    color: #fff;
}

.org-team-node--l2 .org-team-node__head {
    background: #fdf2f6;
    border-bottom: 1px solid rgb(154 0 54 / 0.1);
}

.org-team-node--l2 .org-team-node__level {
    color: rgb(154 0 54 / 0.75);
}

.org-team-node--l2 .org-team-node__title {
    color: #660026;
}

.org-team-node--l3 .org-team-node__head {
    background: rgb(248 250 252);
    border-bottom: 1px solid rgb(226 232 240);
}

.org-team-node--l3 .org-team-node__level {
    color: rgb(100 116 139);
}

.org-team-node--l3 .org-team-node__title {
    font-size: 0.875rem;
    font-weight: 600;
    color: rgb(30 41 59);
}

.org-team-node__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem;
    padding: 0.5rem 0.75rem 0.625rem;
    border-top: 1px solid rgb(241 245 249);
}

.org-team-node__btn {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.5rem;
    font-size: 10px;
    font-weight: 500;
    color: rgb(71 85 105);
    background: #fff;
    border: 1px solid rgb(226 232 240);
    border-radius: 0.375rem;
}

.org-team-node__btn:hover {
    background: rgb(248 250 252);
}

.org-team-node__btn--danger:hover {
    color: rgb(190 18 60);
    background: rgb(255 241 242);
    border-color: rgb(254 205 211);
}
</style>
