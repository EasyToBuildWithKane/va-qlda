<script setup>
import { computed } from 'vue';
import Avatar from '@/shared/ui/Avatar.vue';

const props = defineProps({
    members: { type: Array, default: () => [] },
    maxVisible: { type: Number, default: 4 },
    maxNameLabels: { type: Number, default: 3 },
    compact: { type: Boolean, default: false },
    showNames: { type: Boolean, default: true },
    animate: { type: Boolean, default: true },
});

const normalizedMembers = computed(() =>
    (props.members || []).map((member) => ({
        ...member,
        name: member?.name || 'Thành viên',
    })),
);

const visibleMembers = computed(() => normalizedMembers.value.slice(0, props.maxVisible));
const hiddenCount = computed(() => Math.max(normalizedMembers.value.length - visibleMembers.value.length, 0));

const namesLabel = computed(() => {
    const names = normalizedMembers.value.map((m) => m.name);
    const max = props.maxNameLabels;
    if (names.length <= max) {
        return names.join(', ');
    }
    return `${names.slice(0, max).join(', ')} +${names.length - max}`;
});
</script>

<template>
  <div :class="compact ? 'min-w-0' : 'min-w-[120px]'">
    <p
      v-if="!compact"
      class="text-[10px] font-medium uppercase tracking-wide text-slate-400"
    >
      Thành viên
    </p>
    <div
      v-if="normalizedMembers.length"
      :class="compact ? '' : 'mt-1'"
    >
      <div class="flex items-center">
        <span
          v-for="(member, index) in visibleMembers"
          :key="member.id || `${member.name}-${index}`"
          class="relative inline-flex rounded-full ring-2 ring-white"
          :class="animate ? 'member-avatar-enter' : ''"
          :style="{
            marginLeft: index === 0 ? '0' : '-7px',
            zIndex: 10 - index,
            animationDelay: animate ? `${index * 55}ms` : undefined,
          }"
          :title="member.name"
        >
          <Avatar
            :name="member.name"
            :src="member.avatar_path"
            :size="compact ? 24 : 26"
          />
        </span>
        <span
          v-if="hiddenCount"
          class="relative ml-[-7px] inline-flex items-center justify-center rounded-full bg-slate-100 px-1.5 text-[11px] font-semibold text-slate-600 ring-2 ring-white"
          :class="[
            compact ? 'h-6 min-w-[1.5rem]' : 'h-[26px] min-w-[26px]',
            animate ? 'member-avatar-enter' : '',
          ]"
          :style="{
            animationDelay: animate ? `${visibleMembers.length * 55}ms` : undefined,
          }"
        >
          +{{ hiddenCount }}
        </span>
      </div>
      <p
        v-if="showNames"
        class="mt-1 line-clamp-2 text-xs leading-snug text-slate-600"
        :class="[
          compact ? 'max-w-[14rem]' : '',
          animate ? 'member-names-enter' : '',
        ]"
        :title="namesLabel"
      >
        {{ namesLabel }}
      </p>
    </div>
    <p
      v-else
      class="mt-1 text-xs text-slate-400"
    >
      Chưa có thành viên
    </p>
  </div>
</template>

<style scoped>
.member-avatar-enter {
    animation: member-pop 0.4s cubic-bezier(0.34, 1.4, 0.64, 1) backwards;
}

.member-names-enter {
    animation: member-fade-up 0.45s ease 0.12s backwards;
}

@keyframes member-pop {
    from {
        opacity: 0;
        transform: scale(0.6) translateY(4px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

@keyframes member-fade-up {
    from {
        opacity: 0;
        transform: translateY(6px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (prefers-reduced-motion: reduce) {
    .member-avatar-enter,
    .member-names-enter {
        animation: none;
    }
}
</style>
