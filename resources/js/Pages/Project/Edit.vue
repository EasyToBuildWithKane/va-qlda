<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import ProjectForm from '@/Components/Project/ProjectForm.vue';

const props = defineProps({
    project: { type: Object, required: true },
    employees: { type: Array, default: () => [] },
    statusOptions: { type: Array, default: () => [] },
    typeOptions: { type: Array, default: () => [] },
    scopeOptions: { type: Array, default: () => [] },
    regionOptions: { type: Array, default: () => [] },
    departmentOptions: { type: Array, default: () => [] },
});
</script>

<template>
    <Head :title="'Sửa ' + project.name" />
    <AppLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <Link :href="`/projects/${project.id}`" class="grid h-8 w-8 place-items-center rounded-btn text-slate-400 hover:bg-slate-100">
                    <AppIcon name="back" :size="18" />
                </Link>
                <h1 class="font-display font-semibold text-slate-800">Chỉnh sửa dự án</h1>
            </div>
        </template>

        <ProjectForm :project="project" :employees="employees" :status-options="statusOptions" :type-options="typeOptions" :scope-options="scopeOptions" :region-options="regionOptions" :department-options="departmentOptions" />
    </AppLayout>
</template>
