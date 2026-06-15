<script setup>
import Avatar from '@/shared/ui/Avatar.vue';
import AppIcon from '@/Components/AppIcon.vue';

defineProps({
    name: { type: String, required: true },
    avatar: { type: String, default: null },
    role: { type: String, default: null },
    sectionLabel: { type: String, default: null },
    isLeader: { type: Boolean, default: false },
    interactive: { type: Boolean, default: true },
});

const emit = defineEmits(['select']);

function onActivate() {
    emit('select');
}
</script>

<template>
  <component
    :is="interactive ? 'button' : 'div'"
    type="button"
    class="org-person-node org-flow-node"
    :class="{
      'org-person-node--leader': isLeader,
      'org-person-node--interactive': interactive,
    }"
    @click="interactive ? onActivate() : undefined"
  >
    <span
      class="org-person-node__fx org-person-node__fx--aurora"
      aria-hidden="true"
    />
    <span
      class="org-person-node__fx org-person-node__fx--shimmer"
      aria-hidden="true"
    />
    <span
      v-if="isLeader"
      class="org-person-node__badge"
    >
      Trưởng nhóm
    </span>
    <span
      v-else-if="sectionLabel"
      class="org-person-node__chip"
    >
      {{ sectionLabel }}
    </span>
    <span
      v-else
      class="org-person-node__chip org-person-node__chip--muted"
    >
      Thành viên
    </span>

    <Avatar
      :src="avatar"
      :name="name"
      :size="48"
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
    <p
      v-else
      class="org-person-node__role org-person-node__role--placeholder"
    >
      —
    </p>

    <span
      v-if="interactive"
      class="org-person-node__hint"
    >
      <AppIcon
        name="chevron-right"
        :size="12"
      />
      Chi tiết
    </span>
  </component>
</template>

<style scoped>
.org-person-node {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-start;
    width: 10rem;
    min-height: 10.5rem;
    padding: 0.75rem 0.55rem 0.55rem;
    padding-left: 0.65rem;
    text-align: center;
    background: rgb(255 255 255 / 0.94);
    border: 1px solid rgb(226 232 240);
    border-radius: 0.75rem;
    box-shadow: 0 1px 3px rgb(15 23 42 / 0.05);
    backdrop-filter: blur(4px);
}

.org-person-node--interactive {
    cursor: pointer;
    transition:
        border-color 0.2s ease,
        box-shadow 0.25s ease,
        transform 0.25s ease;
}

.org-person-node--interactive:hover {
    border-color: rgb(14 165 233 / 0.4);
    box-shadow:
        0 4px 16px rgb(14 165 233 / 0.1),
        0 0 0 1px rgb(14 165 233 / 0.08);
    transform: translateY(-2px);
}

.org-person-node--interactive:focus-visible {
    outline: 2px solid rgb(154 0 54 / 0.45);
    outline-offset: 2px;
}

.org-person-node--leader {
    border-color: rgb(154 0 54 / 0.25);
    background: rgb(255 255 255 / 0.98);
}

.org-person-node__badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 1.125rem;
    margin-bottom: 0.375rem;
    padding: 0 0.45rem;
    font-size: 0.5625rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #9a0036;
    background: #fff;
    border: 1px solid rgb(154 0 54 / 0.2);
    border-radius: 999px;
}

.org-person-node__chip {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    max-width: 100%;
    min-height: 1.125rem;
    margin-bottom: 0.375rem;
    padding: 0 0.4rem;
    font-size: 0.5625rem;
    font-weight: 600;
    line-height: 1.2;
    color: rgb(51 65 85);
    background: rgb(241 245 249);
    border-radius: 999px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.org-person-node__chip--muted {
    color: rgb(100 116 139);
    background: rgb(248 250 252);
}

.org-person-node__name {
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    margin-top: 0.5rem;
    width: 100%;
    min-height: 2.7em;
    font-size: 0.75rem;
    font-weight: 700;
    line-height: 1.35;
    color: rgb(15 23 42);
    overflow: hidden;
    overflow-wrap: anywhere;
}

.org-person-node__role {
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    margin-top: 0.25rem;
    width: 100%;
    min-height: 2.6em;
    font-size: 0.625rem;
    line-height: 1.3;
    color: rgb(100 116 139);
    overflow: hidden;
}

.org-person-node__role--placeholder {
    color: rgb(203 213 225);
}

.org-person-node__hint {
    display: inline-flex;
    align-items: center;
    gap: 0.125rem;
    margin-top: auto;
    padding-top: 0.375rem;
    font-size: 0.5625rem;
    font-weight: 500;
    color: rgb(148 163 184);
}

.org-person-node--interactive:hover .org-person-node__hint {
    color: #9a0036;
}
</style>
