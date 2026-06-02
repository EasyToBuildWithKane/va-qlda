<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ProjectForm from '@/Components/Project/ProjectForm.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';

defineProps({
    employees: { type: Array, default: () => [] },
    statusOptions: { type: Array, default: () => [] },
    typeOptions: { type: Array, default: () => [] },
    scopeOptions: { type: Array, default: () => [] },
    regionOptions: { type: Array, default: () => [] },
    departmentOptions: { type: Array, default: () => [] },
    suggestedCode: { type: String, default: '' },
    defaultDepartmentId: { type: Number, default: null },
});
</script>

<template>
    <Head title="Tạo dự án" />
    <AppLayout>
        <template #header>
            <PageHeader
                title="Tạo dự án mới"
                subtitle="Điền thông tin để khởi tạo dự án"
                icon="new-project"
                icon-color="emerald"
                back-href="/projects"
            />
        </template>

        <ProjectForm
            :employees="employees"
            :status-options="statusOptions"
            :type-options="typeOptions"
            :scope-options="scopeOptions"
            :region-options="regionOptions"
            :department-options="departmentOptions"
            :suggested-code="suggestedCode"
            :default-department-id="defaultDepartmentId"
        />
    </AppLayout>
</template>
