<script setup>
import { onMounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AiAccountSectionNav from '@/modules/aiAccount/components/AiAccountSectionNav.vue';
import AiCostByGroupPanel from '@/modules/aiAccount/components/AiCostByGroupPanel.vue';
import { useAiCostReport } from '@/modules/aiAccount/composables/useAiCostReport';
import { httpGet } from '@/shared/services/http';

defineProps({
    options: { type: Object, required: true },
});

const { loading, byGroup, cards, loadSummary } = useAiCostReport();
const proposalPendingCount = ref(0);

async function loadPendingBadge() {
    try {
        const res = await httpGet(route('api.ai-accounts.proposals.index'));
        const data = res.data ?? {};
        proposalPendingCount.value = data.counts?.pending ?? 0;
    } catch {
        proposalPendingCount.value = 0;
    }
}

onMounted(async () => {
    await Promise.all([loadSummary(), loadPendingBadge()]);
});
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
      >
        <AiAccountSectionNav
          active="cost-by-group"
          :proposals-badge="proposalPendingCount > 0 ? proposalPendingCount : null"
        />
      </PageHeader>
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
