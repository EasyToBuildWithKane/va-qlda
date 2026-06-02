<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import ProjectForm from '@/Components/Project/ProjectForm.vue';

defineProps({
    employees: { type: Array, default: () => [] },
    statusOptions: { type: Array, default: () => [] },
    typeOptions: { type: Array, default: () => [] },
    scopeOptions: { type: Array, default: () => [] },
    regionOptions: { type: Array, default: () => [] },
    departmentOptions: { type: Array, default: () => [] },
    suggestedCode: { type: String, default: '' },
});
</script>

<template>
    <Head title="Tạo dự án" />
    <AppLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <Link href="/projects" class="grid h-8 w-8 place-items-center rounded-btn text-slate-400 hover:bg-slate-100">
                    <AppIcon name="back" :size="18" />
                </Link>
                <h1 class="font-display font-semibold text-slate-800">Tạo dự án mới</h1>
            </div>
        </template>

        <ProjectForm :employees="employees" :status-options="statusOptions" :type-options="typeOptions" :scope-options="scopeOptions" :region-options="regionOptions" :department-options="departmentOptions" :suggested-code="suggestedCode" />
    </AppLayout>
</template>
