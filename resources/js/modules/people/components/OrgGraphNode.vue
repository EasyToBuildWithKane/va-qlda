<script setup>
import { computed } from 'vue';
import Avatar from '@/shared/ui/Avatar.vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    node: { type: Object, required: true },
    index: { type: Number, default: 0 },
});

const emit = defineEmits(['toggle-members', 'toggle-subteams', 'select-person', 'select-leader']);

const team = computed(() => props.node.team ?? null);

const levelLabel = computed(() => {
    const lvl = team.value?.level ?? 1;
    return lvl === 1 ? 'Ban / Khối' : 'Nhóm';
});

const boxStyle = computed(() => ({
    left: `${props.node.x}px`,
    top: `${props.node.y}px`,
    width: `${props.node.w}px`,
    height: `${props.node.h}px`,
    '--reveal-delay': `${Math.min(props.index * 18, 520)}ms`,
}));

const dimmed = computed(() => props.node.inPath === false);
const matched = computed(() => props.node.matched === true);

const person = computed(() => props.node.person ?? null);

const personMangLabel = computed(() => {
    const title = person.value?.sectionTitle?.trim();
    return title || null;
});

const personSubtitle = computed(() => {
    const p = person.value;
    if (!p) return 'Thành viên';
    const parts = [];
    if (p.roleTitle?.trim()) parts.push(p.roleTitle.trim());
    if (p.branchLabel?.trim() && p.branchLabel !== p.roleTitle) {
        parts.push(p.branchLabel.trim());
    }
    return parts.length ? parts.join(' · ') : 'Thành viên';
});

function onTeamPrimary() {
    if (props.node.hasMembers) emit('toggle-members', props.node);
    else if (props.node.hasSubteams) emit('toggle-subteams', props.node);
}
</script>

<template>
  <!-- Synthetic organization hub -->
  <div
    v-if="node.type === 'org'"
    class="org-node org-node--org"
    :class="{ 'org-node--dim': dimmed }"
    :style="boxStyle"
    data-node
  >
    <span class="org-node__hub-icon">
      <AppIcon
        name="org-teams"
        :size="20"
      />
    </span>
    <div class="min-w-0">
      <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-white/70">
        Tổ chức
      </p>
      <p class="truncate font-display text-sm font-bold text-white">
        {{ node.rootCount }} Ban / Khối · {{ node.peopleCount }} nhân sự
      </p>
    </div>
  </div>

  <!-- Team (Ban/Khối or Nhóm) -->
  <div
    v-else-if="node.type === 'team'"
    class="org-node org-node--team"
    :class="{ 'org-node--dim': dimmed, 'org-node--match': matched }"
    :style="boxStyle"
    data-node
    @pointerdown.stop
  >
    <div
      class="org-node__surface group"
      role="button"
      tabindex="0"
      @click="onTeamPrimary"
      @keydown.enter.prevent="onTeamPrimary"
      @keydown.space.prevent="onTeamPrimary"
    >
      <span
        class="org-node__status"
        :class="team.is_active === false ? 'org-node__status--off' : 'org-node__status--on'"
        :title="team.is_active === false ? 'Ngừng hoạt động' : 'Đang hoạt động'"
      />

      <div class="flex items-start gap-2.5">
        <button
          v-if="team.leader"
          type="button"
          class="org-node__avatar-btn"
          :title="`Trưởng nhóm: ${team.leader.name}`"
          @click.stop="emit('select-leader', node)"
        >
          <Avatar
            :name="team.leader.name"
            :src="team.leader.avatar_path"
            :size="38"
          />
          <span class="org-node__lead-badge">
            <AppIcon
              name="star"
              :size="9"
            />
          </span>
        </button>
        <span
          v-else
          class="org-node__avatar-fallback"
        >
          <AppIcon
            name="org-teams"
            :size="18"
          />
        </span>

        <div class="min-w-0 flex-1 text-left">
          <p class="org-node__eyebrow">
            {{ levelLabel }}
          </p>
          <p class="org-node__title">
            {{ team.name }}
          </p>
          <p
            v-if="team.leader"
            class="org-node__subtitle"
          >
            {{ team.leader.name }}
          </p>
        </div>
      </div>

      <div class="org-node__footer">
        <span class="org-node__chip">
          <AppIcon
            name="members"
            :size="12"
          />
          {{ node.peopleCount }}
        </span>
        <span
          v-if="node.hasMembers"
          class="org-node__toggle"
        >
          <AppIcon
            :name="node.expanded ? 'eye-off' : 'eye'"
            :size="12"
          />
          {{ node.expanded ? 'Ẩn' : 'Thành viên' }}
        </span>
        <span
          v-else-if="node.hasSubteams"
          class="org-node__toggle"
        >
          <AppIcon
            :name="node.collapsed ? 'plus' : 'minus'"
            :size="12"
          />
          {{ node.collapsed ? 'Mở' : 'Thu' }}
        </span>
      </div>
    </div>
  </div>

  <!-- Person (member) -->
  <button
    v-else
    type="button"
    class="org-node org-node--person"
    :class="{ 'org-node--dim': dimmed, 'org-node--match': matched }"
    :style="boxStyle"
    data-node
    @pointerdown.stop
    @click="emit('select-person', node.person)"
  >
    <span
      class="org-node__status"
      :class="node.person.isActive ? 'org-node__status--on' : 'org-node__status--off'"
    />
    <div class="flex items-center gap-2.5">
      <Avatar
        :name="node.person.name"
        :src="node.person.avatar"
        :size="36"
      />
      <div class="min-w-0 flex-1 text-left">
        <p
          class="org-node__mang"
          :class="personMangLabel ? 'org-node__mang--set' : 'org-node__mang--unset'"
          :title="personMangLabel ? `Mảng: ${personMangLabel}` : 'Chưa phân mảng'"
        >
          {{ personMangLabel || 'Chưa phân mảng' }}
        </p>
        <p class="org-node__title org-node__title--sm">
          {{ node.person.name }}
        </p>
        <p class="org-node__subtitle">
          {{ personSubtitle }}
        </p>
      </div>
    </div>
  </button>
</template>

<style scoped>
.org-node {
    position: absolute;
    border-radius: 16px;
    animation: org-node-in 0.5s cubic-bezier(0.22, 1, 0.36, 1) both;
    animation-delay: var(--reveal-delay, 0ms);
    transition: opacity 0.3s ease, transform 0.25s ease, filter 0.3s ease,
        left 0.35s cubic-bezier(0.22, 1, 0.36, 1), top 0.35s cubic-bezier(0.22, 1, 0.36, 1);
    will-change: transform, opacity, left, top;
}

@keyframes org-node-in {
    from {
        opacity: 0;
        transform: translateY(10px) scale(0.94);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@media (prefers-reduced-motion: reduce) {
    .org-node {
        animation: none;
    }
}

.org-node--dim {
    opacity: 0.28;
    filter: grayscale(0.4);
}

/* Org hub */
.org-node--org {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0 1rem;
    background: linear-gradient(135deg, #9a0036, #c4185b 70%, #d6418a);
    box-shadow: 0 14px 30px -14px rgba(154, 0, 54, 0.65), inset 0 1px 0 rgba(255, 255, 255, 0.2);
    overflow: hidden;
}

.org-node__hub-icon {
    display: grid;
    place-items: center;
    width: 40px;
    height: 40px;
    flex-shrink: 0;
    border-radius: 12px;
    color: #fff;
    background: rgba(255, 255, 255, 0.16);
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.25);
}

/* Team card */
.org-node--team .org-node__surface,
.org-node--person {
    display: block;
    width: 100%;
    height: 100%;
    padding: 0.7rem 0.8rem;
    border-radius: 16px;
    text-align: left;
    background: rgba(255, 255, 255, 0.82);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(148, 163, 184, 0.28);
    box-shadow: 0 10px 26px -18px rgba(15, 23, 42, 0.45);
    transition: box-shadow 0.3s ease, transform 0.25s ease, border-color 0.3s ease;
    outline: none;
}

.org-node--team .org-node__surface {
    position: relative;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    cursor: pointer;
}

.org-node--team .org-node__surface:hover,
.org-node--person:hover {
    transform: translateY(-3px);
    border-color: rgba(154, 0, 54, 0.4);
    box-shadow: 0 18px 38px -18px rgba(154, 0, 54, 0.5);
}

.org-node--team .org-node__surface:focus-visible,
.org-node--person:focus-visible {
    border-color: #9a0036;
    box-shadow: 0 0 0 3px rgba(154, 0, 54, 0.22);
}

.org-node--match .org-node__surface,
.org-node--match.org-node--person {
    border-color: rgba(154, 0, 54, 0.7);
    box-shadow: 0 0 0 2px rgba(154, 0, 54, 0.35), 0 18px 36px -18px rgba(154, 0, 54, 0.5);
}

.org-node__status {
    position: absolute;
    top: 0.6rem;
    right: 0.6rem;
    width: 7px;
    height: 7px;
    border-radius: 9999px;
}

.org-node__status--on {
    background: #10b981;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.18);
}

.org-node__status--off {
    background: #94a3b8;
    box-shadow: 0 0 0 3px rgba(148, 163, 184, 0.18);
}

.org-node__avatar-btn {
    position: relative;
    flex-shrink: 0;
    border-radius: 9999px;
    line-height: 0;
}

.org-node__lead-badge {
    position: absolute;
    bottom: -2px;
    right: -2px;
    display: grid;
    place-items: center;
    width: 16px;
    height: 16px;
    border-radius: 9999px;
    color: #fff;
    background: #9a0036;
    box-shadow: 0 0 0 2px #fff;
}

.org-node__avatar-fallback {
    display: grid;
    place-items: center;
    width: 38px;
    height: 38px;
    flex-shrink: 0;
    border-radius: 12px;
    color: #9a0036;
    background: rgba(154, 0, 54, 0.1);
}

.org-node__eyebrow {
    font-size: 9.5px;
    font-weight: 700;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: #94a3b8;
}

.org-node__mang {
    display: inline-block;
    max-width: 100%;
    margin-bottom: 2px;
    padding: 0.1rem 0.4rem;
    border-radius: 9999px;
    font-size: 9.5px;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.org-node__mang--set {
    color: #9a0036;
    background: rgba(154, 0, 54, 0.1);
    border: 1px solid rgba(154, 0, 54, 0.22);
}

.org-node__mang--unset {
    color: #94a3b8;
    background: rgba(148, 163, 184, 0.12);
    border: 1px dashed rgba(148, 163, 184, 0.35);
}

.org-node__title {
    font-weight: 700;
    font-size: 13.5px;
    line-height: 1.25;
    color: #0f172a;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.org-node__title--sm {
    font-size: 12.5px;
}

.org-node__subtitle {
    margin-top: 1px;
    font-size: 11px;
    color: #64748b;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.org-node__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.4rem;
}

.org-node__chip {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.1rem 0.45rem;
    border-radius: 9999px;
    font-size: 11px;
    font-weight: 600;
    color: #475569;
    background: rgba(148, 163, 184, 0.16);
}

.org-node__toggle {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    font-size: 10.5px;
    font-weight: 600;
    color: #9a0036;
}
</style>
