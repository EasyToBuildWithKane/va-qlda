<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import CongngheSoftwareProposalSheet from '@/Pages/Congnghe/partials/CongngheSoftwareProposalSheet.vue';
import { referenceCodeLabel } from '@/Pages/Congnghe/partials/congngheProposalDisplay.js';

defineProps({
    proposal: { type: Object, required: true },
});
</script>

<template>
  <Head :title="proposal.reference_code ?? 'Đề xuất PM'" />

  <AppLayout>
    <template #header>
      <PageHeader
        :title="proposal.title"
        :subtitle="referenceCodeLabel(proposal.reference_code)"
        icon="template"
        icon-color="brand"
      />
    </template>

    <CongngheSoftwareProposalSheet :proposal="proposal" />
  </AppLayout>
</template>
