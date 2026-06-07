<script setup>
import VndAmount from '@/modules/aiAccount/components/VndAmount.vue';

defineProps({
    metrics: { type: Object, default: null },
    loading: { type: Boolean, default: false },
});
</script>

<template>
  <div
    v-if="loading"
    class="mb-4 rounded-card border border-slate-100 bg-white px-5 py-6 text-center text-sm text-slate-500"
  >
    Đang tải thống kê…
  </div>
  <div
    v-else-if="metrics"
    class="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
  >
    <div class="rounded-card border border-slate-100 bg-white px-4 py-3 shadow-sm">
      <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">
        Phiếu đề xuất
      </p>
      <p class="mt-1 font-display text-2xl font-bold text-slate-800">
        {{ metrics.proposals_total ?? 0 }}
      </p>
      <p class="mt-1 text-xs text-slate-500">
        Chờ duyệt: <span class="font-semibold text-amber-700">{{ metrics.proposals_pending ?? 0 }}</span>
        · Đã duyệt: <span class="font-semibold text-emerald-700">{{ metrics.proposals_approved ?? 0 }}</span>
      </p>
    </div>

    <div class="rounded-card border border-slate-100 bg-white px-4 py-3 shadow-sm">
      <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">
        Đề nghị thanh toán
      </p>
      <p class="mt-1 text-xs text-slate-600">
        Chờ duyệt:
        <span class="font-semibold text-amber-700">{{ metrics.payment_requests_pending ?? 0 }}</span>
      </p>
      <p class="text-xs text-slate-600">
        Đã duyệt:
        <span class="font-semibold text-emerald-700">{{ metrics.payment_requests_approved ?? 0 }}</span>
        · Đã thanh toán:
        <span class="font-semibold text-blue-700">{{ metrics.payment_requests_paid ?? 0 }}</span>
      </p>
    </div>

    <div class="rounded-card border border-slate-100 bg-white px-4 py-3 shadow-sm">
      <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">
        Ngân sách đề xuất
      </p>
      <p class="mt-1">
        <VndAmount
          :amount="metrics.budget_proposed_total ?? 0"
          compact
          class="font-display text-xl font-bold text-slate-800"
        />
      </p>
      <p class="mt-1 text-xs text-slate-500">
        Đã duyệt PĐX:
        <VndAmount
          :amount="metrics.budget_proposal_approved_total ?? 0"
          compact
          class="inline font-medium text-emerald-700"
        />
      </p>
    </div>

    <div class="rounded-card border border-slate-100 bg-white px-4 py-3 shadow-sm">
      <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">
        Đã thanh toán
      </p>
      <p class="mt-1">
        <VndAmount
          :amount="metrics.budget_paid_total ?? 0"
          compact
          class="font-display text-xl font-bold text-blue-800"
        />
      </p>
      <p class="mt-1 text-xs text-slate-500">
        ĐNTT đã duyệt (chưa TT):
        <VndAmount
          :amount="(metrics.budget_payment_approved_total ?? 0) - (metrics.budget_paid_total ?? 0)"
          compact
          class="inline font-medium text-slate-600"
        />
      </p>
    </div>
  </div>
</template>
