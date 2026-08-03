<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import EvaluationTemplateForm from '@/modules/evaluation-template/components/EvaluationTemplateForm.vue';

defineProps({
    criteriaOptions: { type: Array, default: () => [] },
    jobTitles: { type: Array, default: () => [] },
    jobRanks: { type: Array, default: () => [] },
    fieldTypeOptions: { type: Array, default: () => [] },
    nextCode: { type: String, default: 'MDG001' },
});
</script>

<template>
  <Head title="Thêm mẫu đánh giá" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Thêm mẫu đánh giá"
        :subtitle="`Mã gợi ý: ${nextCode}`"
        icon="clipboard-list"
        icon-color="emerald"
        back-href="/workspace-config/evaluation-templates"
      />
    </template>

    <div class="evaluation-template-create-scale w-full">
      <EvaluationTemplateForm
        layout="page"
        :criteria-options="criteriaOptions"
        :job-titles="jobTitles"
        :job-ranks="jobRanks"
        :field-type-options="fieldTypeOptions"
      />
    </div>
  </AppLayout>
</template>

<style scoped>
/* Thu nhỏ form tạo mẫu ~ Ctrl− 90% (Chrome/Edge) */
.evaluation-template-create-scale {
  zoom: 0.9;
}
</style>
