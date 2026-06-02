<script setup>
import Avatar from '@/Components/Project/Avatar.vue';
import { hours } from '@/composables/useFormat';

defineProps({
    rows: { type: Array, default: () => [] },
});
</script>

<template>
    <div class="card p-5 dark:border-slate-700 dark:bg-slate-900">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="font-display font-semibold text-slate-800 dark:text-slate-100">Workload thành viên</h2>
            <span class="rounded-full bg-brand-50 px-2 py-0.5 text-xs font-semibold text-brand dark:bg-brand/20 dark:text-brand-100">{{ rows.length }}</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[480px] text-sm">
                <thead class="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-400 dark:border-slate-700">
                    <tr>
                        <th class="pb-2 font-medium">Thành viên</th>
                        <th class="pb-2 font-medium text-center">Đang chạy</th>
                        <th class="pb-2 font-medium text-right">Giờ ước lượng</th>
                        <th class="pb-2 pl-3 font-medium">Tiến độ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    <tr v-for="row in rows" :key="row.member.id" class="hover:bg-slate-50/70 dark:hover:bg-slate-800/50">
                        <td class="py-2.5">
                            <div class="flex items-center gap-2">
                                <Avatar :name="row.member.name" :src="row.member.avatar_path" :size="32" />
                                <div class="min-w-0">
                                    <p class="flex flex-wrap items-center gap-1.5 font-medium text-slate-700 dark:text-slate-200">
                                        {{ row.member.name }}
                                        <span
                                            v-if="row.overloaded"
                                            class="rounded-full bg-rose-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-rose-600 dark:bg-rose-950/50 dark:text-rose-300"
                                        >Quá tải</span>
                                    </p>
                                    <p class="text-xs text-slate-400">{{ row.member.project_role }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="py-2.5 text-center font-semibold text-slate-700 dark:text-slate-200">{{ row.activeTasks }}</td>
                        <td class="py-2.5 text-right text-slate-600 dark:text-slate-300">{{ hours(row.totalHours) }}</td>
                        <td class="py-2.5 pl-3">
                            <div class="flex items-center gap-2">
                                <div class="h-2 min-w-[80px] flex-1 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-700">
                                    <div class="h-full rounded-full transition-all" :class="row.progressColor" :style="{ width: row.personalProgress + '%' }" />
                                </div>
                                <span class="w-9 text-right text-xs text-slate-500">{{ row.personalProgress }}%</span>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!rows.length">
                        <td colspan="4" class="py-6 text-center text-slate-400">Chưa có thành viên.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
