<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import EvaluationConfigForm from '@/modules/evaluation/components/EvaluationConfigForm.vue';

defineProps({
    config: { type: Object, required: true },
    departments: { type: Array, default: () => [] },
    templates: { type: Array, default: () => [] },
    templateTypeOptions: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
});
</script>

<template>
  <Head :title="`Sửa · ${config.config_name}`" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Sửa cấu hình đánh giá"
        :subtitle="config.config_name"
        icon="award"
        :back-href="`/workspace-config/evaluation/${config.id}`"
      />
    </template>

    <EvaluationConfigForm
      mode="edit"
      :config="config"
      :departments="departments"
      :templates="templates"
      :template-type-options="templateTypeOptions"
    />
  </AppLayout>
</template>
