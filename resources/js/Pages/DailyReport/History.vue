<script setup>
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/DailyReport/StatusBadge.vue';
import GradePill from '@/Components/DailyReport/GradePill.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';

const props = defineProps({
    reports: { type: Object, required: true }, // { data, meta, links }
    filters: { type: Object, default: () => ({}) },
    statuses: { type: Array, default: () => [] },
});

const filterForm = reactive({
    status: props.filters.status ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});

const applyFilters = () => {
    router.get('/daily-reports', { ...filterForm }, { preserveState: true, replace: true });
};

const clearFilters = () => {
    filterForm.status = '';
    filterForm.from = '';
    filterForm.to = '';
    applyFilters();
};

const pageLabel = (label) =>
    String(label).replace('&laquo;', '«').replace('&raquo;', '»').replace(/<[^>]*>/g, '').trim();
</script>

<template>
    <Head title="Report History" />

    <AppLayout>
        <template #header>
            <PageHeader
                title="Lịch sử báo cáo"
                subtitle="Xem lại tất cả báo cáo đã nộp"
                icon="report-history"
                icon-color="sky"
                :badge="reports.meta?.total"
            />
        </template>

        <!-- Filters -->
        <div class="card p-4 mb-4 flex flex-wrap items-end gap-3">
            <div>
                <label class="label">Status</label>
                <select v-model="filterForm.status" class="input" @change="applyFilters">
                    <option value="">All</option>
                    <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>
            </div>
            <div>
                <label class="label">From</label>
                <input v-model="filterForm.from" type="date" class="input" @change="applyFilters" />
            </div>
            <div>
                <label class="label">To</label>
                <input v-model="filterForm.to" type="date" class="input" @change="applyFilters" />
            </div>
            <button class="btn-ghost" @click="clearFilters">Clear</button>
        </div>

        <!-- Table -->
        <div class="card overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="px-4 py-3 font-medium">Date</th>
                        <th class="px-4 py-3 font-medium">Employee</th>
                        <th class="px-4 py-3 font-medium">Title</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Grade</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="r in reports.data" :key="r.id" class="hover:bg-slate-50">
                        <td class="px-4 py-3 whitespace-nowrap">
                            {{ r.date }}
                            <span v-if="r.is_late" class="ml-1 text-xs text-danger">late</span>
                        </td>
                        <td class="px-4 py-3">{{ r.employee?.name ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ r.title }}</td>
                        <td class="px-4 py-3"><StatusBadge :label="r.status_label" :color="r.status_color" /></td>
                        <td class="px-4 py-3">
                            <GradePill v-if="r.score" :grade="r.score.grade" :color="r.score.grade_color" />
                            <span v-else class="text-slate-300">—</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Link :href="`/daily-reports/${r.id}`" class="text-brand hover:underline">View</Link>
                        </td>
                    </tr>
                    <tr v-if="reports.data.length === 0">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">No reports found.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="reports.meta && reports.meta.last_page > 1" class="flex flex-wrap gap-1 mt-4">
            <template v-for="(link, i) in reports.meta.links" :key="i">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    class="px-3 py-1.5 rounded-btn text-sm"
                    :class="link.active ? 'bg-brand text-white' : 'bg-white border border-slate-200 text-slate-600'"
                >
                    {{ pageLabel(link.label) }}
                </Link>
                <span v-else class="px-3 py-1.5 rounded-btn text-sm text-slate-300">
                    {{ pageLabel(link.label) }}
                </span>
            </template>
        </div>
    </AppLayout>
</template>
