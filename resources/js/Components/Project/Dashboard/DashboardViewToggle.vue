<script setup>
const model = defineModel({ type: String, default: 'pm' });

const options = [
    { value: 'pm', label: 'PM' },
    { value: 'team', label: 'Team' },
    { value: 'bgh', label: 'BGH' },
];
</script>

<template>
    <div class="inline-flex rounded-btn border border-slate-200 bg-slate-100 p-0.5 dark:border-slate-700 dark:bg-slate-800">
        <button
            v-for="o in options"
            :key="o.value"
            type="button"
            class="rounded-[5px] px-3 py-1.5 text-xs font-semibold transition"
            :class="model === o.value
                ? 'bg-white text-brand shadow-sm dark:bg-slate-700 dark:text-brand-100'
                : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'"
            @click="model = o.value"
        >
            {{ o.label }}
        </button>
    </div>
</template>
