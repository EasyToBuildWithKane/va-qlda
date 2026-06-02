<script setup>
import { computed } from 'vue';
import Avatar from '@/Components/Project/Avatar.vue';

const props = defineProps({
    members: { type: Array, default: () => [] },
    maxVisible: { type: Number, default: 4 },
    compact: { type: Boolean, default: false },
});

const normalizedMembers = computed(() =>
    (props.members || []).map((member) => ({
        ...member,
        name: member?.name || 'Thành viên',
    })),
);

const visibleMembers = computed(() => normalizedMembers.value.slice(0, props.maxVisible));
const hiddenCount = computed(() => Math.max(normalizedMembers.value.length - visibleMembers.value.length, 0));
</script>

<template>
    <div :class="compact ? '' : 'min-w-[120px]'">
        <p v-if="!compact" class="text-[10px] font-medium uppercase tracking-wide text-slate-400">Team</p>
        <div v-if="normalizedMembers.length" class="flex items-center" :class="compact ? '' : 'mt-1'">
            <span
                v-for="(member, index) in visibleMembers"
                :key="member.id || `${member.name}-${index}`"
                class="relative inline-flex rounded-full ring-2 ring-white"
                :style="{ marginLeft: index === 0 ? '0' : '-7px', zIndex: 10 - index }"
            >
                <Avatar :name="member.name" :src="member.avatar_path" :size="26" />
            </span>
            <span
                v-if="hiddenCount"
                class="relative ml-[-7px] inline-flex h-[26px] min-w-[26px] items-center justify-center rounded-full bg-slate-100 px-1 text-[11px] font-semibold text-slate-600 ring-2 ring-white"
            >
                +{{ hiddenCount }}
            </span>
        </div>
        <p v-else class="mt-1 text-xs text-slate-400">Chưa có thành viên</p>
    </div>
</template>
