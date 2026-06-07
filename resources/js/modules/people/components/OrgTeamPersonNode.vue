<script setup>
import Avatar from '@/shared/ui/Avatar.vue';

defineProps({
    name: { type: String, required: true },
    avatar: { type: String, default: null },
    role: { type: String, default: null },
    isLeader: { type: Boolean, default: false },
});
</script>

<template>
  <div
    class="org-person-node"
    :class="{ 'org-person-node--leader': isLeader }"
  >
    <Avatar
      :src="avatar"
      :name="name"
      :size="36"
    />
    <p class="org-person-node__name">
      {{ name }}
    </p>
    <p
      v-if="role"
      class="org-person-node__role"
    >
      {{ role }}
    </p>
  </div>
</template>

<style scoped>
.org-person-node {
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 7.25rem;
    max-width: 9rem;
    padding: 0.625rem 0.5rem;
    text-align: center;
    background: #fff;
    border: 1px solid rgb(226 232 240);
    border-radius: 0.75rem;
    box-shadow: 0 1px 2px rgb(15 23 42 / 0.04);
}

.org-person-node--leader {
    border-color: rgb(154 0 54 / 0.22);
    box-shadow: 0 0 0 1px rgb(154 0 54 / 0.06);
}

.org-person-node__name {
    margin-top: 0.5rem;
    width: 100%;
    font-size: 11px;
    font-weight: 600;
    line-height: 1.35;
    color: rgb(15 23 42);
    overflow-wrap: anywhere;
}

.org-person-node__role {
    margin-top: 0.2rem;
    font-size: 10px;
    line-height: 1.3;
    color: rgb(100 116 139);
}
</style>
