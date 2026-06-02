<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ProjectForm from '@/Components/Project/ProjectForm.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';

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
            <PageHeader
                title="Chỉnh sửa dự án"
                :subtitle="project.name"
                icon="edit"
                icon-color="amber"
                :back-href="`/projects/${project.id}`"
            />
        </template>

        <ProjectForm :project="project" :employees="employees" :status-options="statusOptions" :type-options="typeOptions" :scope-options="scopeOptions" :region-options="regionOptions" :department-options="departmentOptions" />
    </AppLayout>
</template>
