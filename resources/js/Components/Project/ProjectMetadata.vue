<script setup>
import { computed } from 'vue';

const props = defineProps({
    department: { type: Object, default: null },
    type: { type: Object, default: null },
    status: { type: Object, default: null },
});

const items = computed(() => ([
    { key: 'department', label: props.department?.name, tone: 'neutral' },
    { key: 'type', label: props.type?.label, tone: 'neutral' },
    { key: 'status', label: props.status?.label, tone: 'status', color: props.status?.color },
]).filter((item) => item.label));

const statusTone = {
    slate: 'bg-slate-100 text-slate-700',
    sky: 'bg-sky-100 text-sky-700',
    emerald: 'bg-emerald-100 text-emerald-700',
    violet: 'bg-violet-100 text-violet-700',
    amber: 'bg-amber-100 text-amber-700',
    rose: 'bg-rose-100 text-rose-700',
    cyan: 'bg-cyan-100 text-cyan-700',
    brand: 'bg-brand-100 text-brand-700',
};

const toneClass = (item) => {
    if (item.tone !== 'status') return 'bg-slate-100 text-slate-600';
    return statusTone[item.color] || 'bg-brand-100 text-brand-700';
};
</script>

<template>
    <div class="flex flex-wrap items-center justify-start gap-1.5 lg:justify-center" aria-label="Thông tin phân loại dự án">
        <span
            v-for="item in items"
            :key="item.key"
            class="inline-flex max-w-[180px] items-center truncate rounded-full px-2 py-0.5 text-[11px] font-medium"
            :class="toneClass(item)"
        >
            {{ item.label }}
        </span>
    </div>
</template>
