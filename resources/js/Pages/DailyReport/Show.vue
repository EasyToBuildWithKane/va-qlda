<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/DailyReport/StatusBadge.vue';
import GradePill from '@/Components/DailyReport/GradePill.vue';
import { fields } from '@/Components/DailyReport/reportConfig';

const props = defineProps({
    report: { type: Object, required: true },
});

// Report content is rich HTML from the editor — render it directly.
const render = (html) => html || '<span class="text-slate-300">—</span>';

// Keep section labels in sync with the editor (Vietnamese, Horenso order).
const sections = fields.map((f) => [f.label, f.key]);

const scoreDimensions = [
    ['Hoàn thành công việc', 'task_completion'],
    ['Kỹ năng', 'skill_score'],
    ['Thái độ', 'attitude_score'],
    ['Cải tiến (Kaizen)', 'kaizen_score'],
    ['Chuyên môn', 'expertise_score'],
];

const submit = () => router.post(`/daily-reports/${props.report.id}/submit`);
</script>

<template>
    <Head :title="report.title" />

    <AppLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link href="/daily-reports" class="text-slate-400 hover:text-slate-600">←</Link>
                <h1 class="font-display font-semibold text-slate-800">{{ report.title }}</h1>
            </div>
        </template>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Body -->
            <div class="lg:col-span-2 space-y-4">
                <div class="card p-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3 text-sm text-slate-500">
                            <span>{{ report.employee?.name }}</span>
                            <span>·</span>
                            <span>{{ report.date }}</span>
                            <span v-if="report.is_late" class="text-danger">nộp trễ</span>
                        </div>
                        <StatusBadge :label="report.status_label" :color="report.status_color" />
                    </div>
                    <div v-if="report.projects?.length" class="mt-3 space-y-2 border-t border-slate-100 pt-3">
                        <div v-for="p in report.projects" :key="p.id" class="flex flex-wrap items-center gap-1.5">
                            <span class="inline-flex items-center rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-medium text-brand">{{ p.name }}</span>
                            <span
                                v-for="t in (p.tasks || [])"
                                :key="t.id"
                                class="inline-flex items-center rounded-md border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs text-slate-600"
                            >{{ t.title }}</span>
                        </div>
                    </div>
                </div>

                <div v-if="report.review_notes && report.status === 'draft'" class="card p-4 border-l-4 border-warning">
                    <p class="text-sm font-medium text-slate-700">↩️ Báo cáo được trả lại</p>
                    <p class="text-sm text-slate-600 mt-1">{{ report.review_notes }}</p>
                </div>

                <div class="card p-6 space-y-5">
                    <div v-for="[label, key] in sections" :key="key">
                        <h3 class="text-sm font-semibold text-slate-700 mb-1">{{ label }}</h3>
                        <div class="text-sm text-slate-600 rich-content" v-html="render(report[key])"></div>
                    </div>
                </div>

                <div class="flex gap-2">
                    <Link
                        v-if="report.can?.update"
                        href="/daily-reports/today"
                        class="btn-ghost"
                    >Sửa bản nháp</Link>
                    <button
                        v-if="report.can?.submit"
                        class="btn-primary"
                        @click="submit"
                    >Nộp duyệt</button>
                </div>
            </div>

            <!-- Score sidebar -->
            <div class="space-y-4">
                <div v-if="report.score" class="card p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-display font-semibold text-slate-800">Đánh giá</h3>
                        <div class="flex items-center gap-2">
                            <span class="text-2xl font-display font-bold text-brand">
                                {{ Number(report.score.total_score ?? 0).toFixed(2) }}
                            </span>
                            <GradePill :grade="report.score.grade" :color="report.score.grade_color" />
                        </div>
                    </div>
                    <dl class="space-y-2">
                        <div v-for="[label, key] in scoreDimensions" :key="key" class="flex items-center justify-between text-sm">
                            <dt class="text-slate-500">{{ label }}</dt>
                            <dd class="font-medium text-slate-700">{{ Number(report.score[key] ?? 0).toFixed(1) }}</dd>
                        </div>
                    </dl>
                    <div v-if="report.score.notes" class="mt-4 pt-3 border-t border-slate-100">
                        <p class="text-xs text-slate-400 mb-1">Nhận xét của người duyệt</p>
                        <p class="text-sm text-slate-600">{{ report.score.notes }}</p>
                    </div>
                    <p v-if="report.score.reviewer" class="mt-3 text-xs text-slate-400">
                        Người duyệt: {{ report.score.reviewer.name }}
                    </p>
                </div>

                <div v-else class="card p-6 text-sm text-slate-400">
                    Chưa được chấm điểm.
                </div>
            </div>
        </div>
    </AppLayout>
</template>
