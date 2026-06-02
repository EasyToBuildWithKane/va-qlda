<script setup>
import { computed } from 'vue';
import { Doughnut } from 'vue-chartjs';
import { Chart as ChartJS, ArcElement, Tooltip, Legend } from 'chart.js';
import { currency, hours } from '@/composables/useFormat';

ChartJS.register(ArcElement, Tooltip, Legend);

const props = defineProps({
    cost: { type: Object, required: true }, // { budget, labor_cost, remaining, utilization, total_hours, by_member }
});

const hasBudget = computed(() => props.cost.budget !== null && props.cost.budget !== undefined);

const chartData = computed(() => {
    if (hasBudget.value) {
        const used = props.cost.labor_cost || 0;
        const remaining = Math.max(0, (props.cost.budget || 0) - used);
        const over = Math.max(0, used - (props.cost.budget || 0));
        return {
            labels: over > 0 ? ['Đã dùng', 'Vượt ngân sách'] : ['Đã dùng', 'Còn lại'],
            datasets: [{
                data: over > 0 ? [props.cost.budget, over] : [used, remaining],
                backgroundColor: over > 0 ? ['#1E3A5F', '#f43f5e'] : ['#1E3A5F', '#e2e8f0'],
                borderWidth: 0,
            }],
        };
    }
    // No budget: break cost down by member.
    const members = props.cost.by_member || [];
    return {
        labels: members.map((m) => m.name),
        datasets: [{
            data: members.map((m) => m.cost),
            backgroundColor: ['#1E3A5F', '#0ea5e9', '#10b981', '#f59e0b', '#8b5cf6', '#f43f5e', '#06b6d4'],
            borderWidth: 0,
        }],
    };
});

const options = {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '68%',
    plugins: {
        legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
        tooltip: {
            callbacks: {
                label: (ctx) => `${ctx.label}: ${currency(ctx.parsed)}`,
            },
        },
    },
};

const overBudget = computed(() => hasBudget.value && (props.cost.labor_cost || 0) > (props.cost.budget || 0));
</script>

<template>
    <div class="grid gap-6 md:grid-cols-2">
        <div>
            <div class="relative h-56">
                <Doughnut :data="chartData" :options="options" />
            </div>
        </div>

        <div class="space-y-3">
            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-card bg-slate-50 p-3">
                    <p class="text-xs text-slate-500">Ngân sách</p>
                    <p class="mt-1 font-display text-lg font-bold text-slate-800">{{ currency(cost.budget) }}</p>
                </div>
                <div class="rounded-card bg-slate-50 p-3">
                    <p class="text-xs text-slate-500">Chi phí nhân công</p>
                    <p class="mt-1 font-display text-lg font-bold" :class="overBudget ? 'text-danger' : 'text-brand'">
                        {{ currency(cost.labor_cost) }}
                    </p>
                </div>
                <div class="rounded-card bg-slate-50 p-3">
                    <p class="text-xs text-slate-500">Còn lại</p>
                    <p class="mt-1 font-display text-lg font-bold text-slate-800">{{ currency(cost.remaining) }}</p>
                </div>
                <div class="rounded-card bg-slate-50 p-3">
                    <p class="text-xs text-slate-500">Tổng giờ</p>
                    <p class="mt-1 font-display text-lg font-bold text-slate-800">{{ hours(cost.total_hours) }}</p>
                </div>
            </div>

            <div v-if="cost.by_member?.length" class="rounded-card border border-slate-100 p-3">
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Theo thành viên</p>
                <ul class="space-y-1.5">
                    <li v-for="m in cost.by_member" :key="m.name" class="flex items-center justify-between text-sm">
                        <span class="text-slate-600">{{ m.name }}</span>
                        <span class="text-slate-500">{{ hours(m.hours) }} · <span class="font-medium text-slate-700">{{ currency(m.cost) }}</span></span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>
