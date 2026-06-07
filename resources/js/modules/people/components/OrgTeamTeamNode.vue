<script setup>
import AppIcon from '@/Components/AppIcon.vue';

defineProps({
    node: { type: Object, required: true },
    canManage: { type: Boolean, default: false },
    memberCount: { type: Number, default: 0 },
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
      <span class="org-team-node__level">{{ node.level_label }}</span>
      <h3 class="org-team-node__title">
        {{ node.name }}
      </h3>
      <p
        v-if="memberCount > 0"
        class="org-team-node__meta"
      >
        {{ memberCount }} thành viên trên sơ đồ
      </p>
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
        v-if="node.level < 3"
        type="button"
        class="org-team-node__btn"
        @click="emit('add-child', node)"
      >
        <AppIcon
          name="plus"
          :size="12"
        />
        Nhóm con
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
    min-width: 11.5rem;
    max-width: 16rem;
    overflow: hidden;
    background: #fff;
    border: 1px solid rgb(226 232 240);
    border-radius: 0.875rem;
    box-shadow:
        0 1px 2px rgb(15 23 42 / 0.05),
        0 4px 12px rgb(15 23 42 / 0.04);
}

.org-team-node::before {
    content: '';
    position: absolute;
    top: 0;
    bottom: 0;
    left: 0;
    width: 4px;
    background: rgb(148 163 184);
}

.org-team-node--l1::before {
    background: #9a0036;
}

.org-team-node--l2::before {
    background: rgb(100 116 139);
}

.org-team-node--l3::before {
    background: rgb(203 213 225);
}

.org-team-node__head {
    padding: 0.75rem 0.875rem 0.75rem 1rem;
}

.org-team-node__level {
    display: inline-block;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: rgb(100 116 139);
}

.org-team-node--l1 .org-team-node__level {
    color: #9a0036;
}

.org-team-node__title {
    margin-top: 0.25rem;
    font-size: 0.9375rem;
    font-weight: 600;
    line-height: 1.35;
    color: rgb(15 23 42);
}

.org-team-node__meta {
    margin-top: 0.35rem;
    font-size: 10px;
    color: rgb(100 116 139);
}

.org-team-node__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem;
    padding: 0.5rem 0.75rem 0.625rem 1rem;
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
