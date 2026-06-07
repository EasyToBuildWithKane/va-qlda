<script setup>
import { onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AiCostByGroupPanel from '@/modules/aiAccount/components/AiCostByGroupPanel.vue';
import { useAiCostReport } from '@/modules/aiAccount/composables/useAiCostReport';

defineProps({
    options: { type: Object, required: true },
});

const { loading, byGroup, cards, loadSummary } = useAiCostReport();

onMounted(loadSummary);
</script>

<template>
  <Head title="Quản lý AI · Chi phí theo nhóm" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Quản lý AI"
        subtitle="Tổng hợp chi phí theo nhóm chức năng từ phiếu đề xuất đã duyệt"
        icon="cost"
        icon-color="brand"
        :badge="byGroup.length || null"
      />
    </template>

    <div
      v-if="loading && !byGroup.length"
      class="card px-5 py-12 text-center text-sm text-slate-500"
    >
      Đang tải dữ liệu chi phí…
    </div>
    <AiCostByGroupPanel
      v-else
      :rows="byGroup"
      :cards="cards"
      :options="options"
      :show-account-link="false"
    />
  </AppLayout>
</template>
