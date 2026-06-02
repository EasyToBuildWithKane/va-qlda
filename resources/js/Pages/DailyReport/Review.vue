<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ScoringPanel from '@/Components/DailyReport/ScoringPanel.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';

defineProps({
    reports: { type: Object, required: true }, // { data, meta }
});

// Report content is rich HTML from the editor.
const render = (html) => html || '<span class="text-slate-300">—</span>';

const preview = [
    ['Mục tiêu hôm nay', 'goals_today'],
    ['Tiến độ thực hiện', 'progress_update'],
    ['Kết quả & tác động', 'results_impact'],
    ['Khó khăn & vướng mắc', 'blockers'],
];
</script>

<template>
    <Head title="Duyệt báo cáo" />

    <AppLayout>
        <template #header>
            <PageHeader
                title="Duyệt báo cáo"
                subtitle="Xem xét và đánh giá báo cáo của thành viên"
                icon="review-reports"
                icon-color="violet"
                :badge="reports.data?.length"
            />
        </template>

        <div v-if="reports.data.length === 0" class="card p-10 text-center text-slate-400">
            Không có báo cáo nào chờ duyệt.
        </div>

        <div class="space-y-6">
            <div v-for="r in reports.data" :key="r.id" class="card p-6 grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Report content -->
                <div class="lg:col-span-2 space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-medium text-slate-800">{{ r.employee?.name }}</p>
                            <p class="text-xs text-slate-400">{{ r.date }} · {{ r.title }}</p>
                        </div>
                        <Link :href="`/daily-reports/${r.id}`" class="text-sm text-brand hover:underline">Mở</Link>
                    </div>
                    <div v-for="[label, key] in preview" :key="key">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-1">{{ label }}</h3>
                        <div class="text-sm text-slate-600 rich-content" v-html="render(r[key])"></div>
                    </div>
                </div>

                <!-- Scoring -->
                <div class="border-l border-slate-100 lg:pl-6">
                    <ScoringPanel :report="r" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
