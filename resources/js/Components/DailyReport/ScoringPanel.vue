<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    report: { type: Object, required: true },
});

// Mirrors config/daily_report.php for live preview only (server is authoritative).
const weights = {
    task_completion: 0.30,
    skill_score: 0.20,
    attitude_score: 0.15,
    kaizen_score: 0.15,
    expertise_score: 0.20,
};

const dimensions = [
    ['task_completion', 'Task completion'],
    ['skill_score', 'Skill'],
    ['attitude_score', 'Attitude'],
    ['kaizen_score', 'Kaizen'],
    ['expertise_score', 'Expertise'],
];

const form = useForm({
    task_completion: 8,
    skill_score: 8,
    attitude_score: 8,
    kaizen_score: 8,
    expertise_score: 8,
    notes: '',
});

const rejectForm = useForm({ notes: '' });

const total = computed(() =>
    Object.entries(weights).reduce((sum, [key, w]) => sum + Number(form[key] || 0) * w, 0),
);

const grade = computed(() => {
    const t = total.value;
    if (t >= 9) return 'S';
    if (t >= 8) return 'A';
    if (t >= 6.5) return 'B';
    if (t >= 5) return 'C';
    return 'D';
});

const submitScore = () =>
    form.post(`/daily-reports/${props.report.id}/score`, { preserveScroll: true });

const submitReject = () =>
    rejectForm.post(`/daily-reports/${props.report.id}/reject`, { preserveScroll: true });
</script>

<template>
    <div class="space-y-4">
        <div class="grid grid-cols-1 gap-3">
            <div v-for="[key, label] in dimensions" :key="key">
                <div class="flex items-center justify-between mb-1">
                    <label class="text-sm text-slate-600">{{ label }}</label>
                    <span class="text-sm font-medium text-slate-800">{{ Number(form[key]).toFixed(1) }}</span>
                </div>
                <input
                    v-model.number="form[key]"
                    type="range"
                    min="0"
                    max="10"
                    step="0.5"
                    class="w-full accent-brand"
                />
                <p v-if="form.errors[key]" class="text-xs text-danger">{{ form.errors[key] }}</p>
            </div>
        </div>

        <div class="flex items-center justify-between rounded-card bg-slate-50 px-4 py-3">
            <span class="text-sm text-slate-500">Computed total</span>
            <div class="flex items-center gap-3">
                <span class="text-2xl font-display font-bold text-brand">{{ total.toFixed(2) }}</span>
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-brand text-sm font-bold text-white">
                    {{ grade }}
                </span>
            </div>
        </div>

        <div>
            <label class="label">Reviewer notes (optional)</label>
            <textarea v-model="form.notes" rows="2" class="input"></textarea>
        </div>

        <button class="btn-primary w-full" :disabled="form.processing" @click="submitScore">
            Score &amp; mark reviewed
        </button>

        <details class="text-sm">
            <summary class="cursor-pointer text-slate-500 hover:text-slate-700">Return to author instead</summary>
            <div class="mt-2 space-y-2">
                <textarea
                    v-model="rejectForm.notes"
                    rows="2"
                    class="input"
                    placeholder="What needs to be added?"
                ></textarea>
                <p v-if="rejectForm.errors.notes" class="text-xs text-danger">{{ rejectForm.errors.notes }}</p>
                <button class="btn-ghost w-full text-danger" :disabled="rejectForm.processing" @click="submitReject">
                    Reject &amp; request changes
                </button>
            </div>
        </details>
    </div>
</template>
