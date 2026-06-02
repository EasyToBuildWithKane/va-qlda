<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    stats: { type: Array, default: () => [] },
});
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout>
        <template #header>
            <h1 class="font-display font-semibold text-slate-800">Executive Overview</h1>
        </template>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div v-for="stat in stats" :key="stat.label" class="card p-5">
                <p class="text-sm text-slate-500">{{ stat.label }}</p>
                <p class="mt-2 text-3xl font-display font-bold text-brand">{{ stat.value }}</p>
            </div>
        </div>

        <div class="card p-6 mt-6">
            <h2 class="font-display font-semibold text-slate-800 mb-1">Foundation ready</h2>
            <p class="text-slate-500 text-sm">
                Stage 0 app shell is live: custom auth, Inertia + Vue 3, Tailwind design tokens,
                and the role-gated navigation. KPI cards, charts, and module pages arrive in later stages.
            </p>
        </div>
    </AppLayout>
</template>
