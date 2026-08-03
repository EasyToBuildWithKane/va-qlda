<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import EvaluationFormWizard from '@/modules/evaluation-form/components/EvaluationFormWizard.vue';

defineProps({
    typeOptions: { type: Array, default: () => [] },
    templateOptions: { type: Array, default: () => [] },
    criteriaOptions: { type: Array, default: () => [] },
    employeeOptions: { type: Array, default: () => [] },
    periodKindOptions: { type: Array, default: () => [] },
    orderOptions: { type: Array, default: () => [] },
    statusOptions: { type: Array, default: () => [] },
    defaultRaters: { type: Array, default: () => [] },
    defaultFields: { type: Array, default: () => [] },
    raterRoleOptions: { type: Array, default: () => [] },
    nextCode: { type: String, default: 'PDG001' },
    prefill: { type: Object, default: () => ({}) },
});
</script>

<template>
  <Head title="Tạo phiếu đánh giá" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Tạo mới phiếu đánh giá"
        subtitle="Cấu hình thông tin chung, tiêu chí và danh sách nhân sự"
        icon="clipboard-list"
        icon-color="emerald"
        back-href="/workspace-config/evaluation-forms"
      />
    </template>

    <EvaluationFormWizard
      mode="create"
      :type-options="typeOptions"
      :template-options="templateOptions"
      :criteria-options="criteriaOptions"
      :employee-options="employeeOptions"
      :period-kind-options="periodKindOptions"
      :order-options="orderOptions"
      :status-options="statusOptions"
      :default-raters="defaultRaters"
      :default-fields="defaultFields"
      :rater-role-options="raterRoleOptions"
      :next-code="nextCode"
      :prefill="prefill"
    />
  </AppLayout>
</template>
