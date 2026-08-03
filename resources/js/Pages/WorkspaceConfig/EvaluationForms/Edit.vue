<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import EvaluationFormWizard from '@/modules/evaluation-form/components/EvaluationFormWizard.vue';

defineProps({
    form: { type: Object, required: true },
    typeOptions: { type: Array, default: () => [] },
    templateOptions: { type: Array, default: () => [] },
    criteriaOptions: { type: Array, default: () => [] },
    employeeOptions: { type: Array, default: () => [] },
    periodKindOptions: { type: Array, default: () => [] },
    orderOptions: { type: Array, default: () => [] },
    defaultRaters: { type: Array, default: () => [] },
    defaultFields: { type: Array, default: () => [] },
    raterRoleOptions: { type: Array, default: () => [] },
    jobTitleOptions: { type: Array, default: () => [] },
    nextCode: { type: String, default: 'PDG001' },
    can: { type: Object, default: () => ({}) },
});
</script>

<template>
  <Head :title="`Sửa phiếu: ${form.name}`" />
  <AppLayout>
    <template #header>
      <PageHeader
        :title="form.name"
        :subtitle="`Mã ${form.form_code} · ${form.status_label || form.status}`"
        icon="clipboard-list"
        icon-color="brand"
        back-href="/workspace-config/evaluation-forms"
      />
    </template>

    <EvaluationFormWizard
      mode="edit"
      :form-data="form"
      :type-options="typeOptions"
      :template-options="templateOptions"
      :criteria-options="criteriaOptions"
      :employee-options="employeeOptions"
      :period-kind-options="periodKindOptions"
      :order-options="orderOptions"
      :default-raters="defaultRaters"
      :default-fields="defaultFields"
      :rater-role-options="raterRoleOptions"
      :job-title-options="jobTitleOptions"
      :next-code="nextCode"
    />
  </AppLayout>
</template>
