<script setup>
import { computed, onMounted, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import { useAiExecutiveDashboard } from '@/modules/aiAccount/composables/useAiExecutiveDashboard';
import AiExecutiveSummaryStrip from '@/modules/aiAccount/components/AiExecutiveSummaryStrip.vue';
import AiDashboardCostTrendChart from '@/modules/aiAccount/components/dashboard/AiDashboardCostTrendChart.vue';
import AiDashboardProductMixChart from '@/modules/aiAccount/components/dashboard/AiDashboardProductMixChart.vue';
import AiDashboardDonutChart from '@/modules/aiAccount/components/dashboard/AiDashboardDonutChart.vue';
import AiDashboardTopLists from '@/modules/aiAccount/components/dashboard/AiDashboardTopLists.vue';

defineProps({
    exchangeRate: { type: Number, default: 25500 },
});

const {
    loading,
    error,
    data,
    granularity,
    comparePreviousYear,
    load,
} = useAiExecutiveDashboard();

onMounted(load);
watch([granularity, comparePreviousYear], load);

const kpis = computed(() => data.value?.kpis ?? {});
const top = computed(() => data.value?.top ?? {});
</script>

<template>
  <Head title="Dashboard AI & Chi phí" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Dashboard quản trị AI"
        subtitle="Tổng quan chi phí, ngân sách và trạng thái tài khoản — dữ liệu từ PĐX & ĐNTT"
        icon="overview"
        icon-color="brand"
      />
    </template>

    <div class="mx-auto max-w-[1600px] space-y-5">
      <div
        v-if="error"
        class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"
        role="alert"
      >
        {{ error }}
      </div>

      <AiExecutiveSummaryStrip
        :kpis="kpis"
        :loading="loading && !data"
      />

      <AiDashboardCostTrendChart
        v-model:granularity="granularity"
        v-model:compare-previous-year="comparePreviousYear"
        :series="data?.cost_over_time"
        :loading="loading"
      />

      <AiDashboardProductMixChart
        :rows="data?.by_product ?? []"
        :loading="loading"
      />

      <div class="grid gap-4 lg:grid-cols-2">
        <AiDashboardDonutChart
          title="Phân bổ ngân sách"
          subtitle="Đã sử dụng · đã cam kết · còn lại (PĐX/ĐNTT)"
          icon="budget"
          palette="budget"
          value-key="amount"
          center-caption="Ngân sách"
          empty-title="Chưa có phân bổ ngân sách"
          empty-description="Dữ liệu xuất hiện khi có phiếu đề xuất đã duyệt và thanh toán được ghi nhận."
          :rows="data?.budget_allocation ?? []"
          :loading="loading"
        />
        <AiDashboardDonutChart
          title="Trạng thái tài khoản"
          subtitle="Theo vòng đời đăng ký và cấp phát"
          icon="account"
          palette="status"
          value-key="count"
          center-caption="Tài khoản"
          empty-title="Chưa có tài khoản thống kê"
          empty-description="Thêm tài khoản AI vào danh mục để xem tỷ lệ trạng thái."
          :rows="data?.account_status ?? []"
          :loading="loading"
        />
      </div>

      <AiDashboardTopLists
        :top="top"
        :loading="loading && !data"
      />
    </div>
  </AppLayout>
</template>
