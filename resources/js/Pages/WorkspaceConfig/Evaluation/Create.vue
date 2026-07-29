<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import EvaluationConfigForm from '@/modules/evaluation/components/EvaluationConfigForm.vue';

defineProps({
    departments: { type: Array, default: () => [] },
    templates: { type: Array, default: () => [] },
    templateTypeOptions: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
});
</script>

<template>
  <Head title="Tạo cấu hình đánh giá" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Tạo cấu hình đánh giá"
        subtitle="Chọn phòng ban, loại mẫu và danh sách tiêu chí"
        icon="award"
        icon-color="emerald"
        back-href="/workspace-config/evaluation"
      />
    </template>

    <EvaluationConfigForm
      mode="create"
      :departments="departments"
      :templates="templates"
      :template-type-options="templateTypeOptions"
    />
  </AppLayout>
</template>
