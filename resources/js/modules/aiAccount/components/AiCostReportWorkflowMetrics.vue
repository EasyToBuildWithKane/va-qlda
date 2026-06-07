<script setup>
import VndAmount from '@/modules/aiAccount/components/VndAmount.vue';

defineProps({
    metrics: { type: Object, default: () => ({}) },
    loading: { type: Boolean, default: false },
});

function num(val) {
    return typeof val === 'number' ? val : 0;
}
</script>

<template>
  <section
    aria-label="Chỉ số quy trình PĐX · ĐNTT · Tài khoản"
    class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm"
  >
    <div class="flex items-center gap-2 border-b border-slate-100 px-5 py-3">
      <span class="text-xs font-bold uppercase tracking-wider text-brand">
        Chỉ số theo giai đoạn
      </span>
      <span class="rounded-full bg-brand/10 px-2 py-0.5 text-[11px] font-semibold text-brand">
        PĐX · ĐNTT · TK
      </span>
    </div>

    <div
      class="grid grid-cols-2 divide-y divide-slate-100 sm:grid-cols-3 lg:grid-cols-5"
      :class="loading ? 'opacity-60' : ''"
    >
      <!-- PĐX -->
      <div class="flex flex-col gap-0.5 px-5 py-4">
        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
          Đề xuất (PĐX)
        </p>
        <VndAmount
          :amount="num(metrics.budget_proposed_total)"
          class="text-lg font-bold text-slate-800"
        />
        <p class="text-[11px] text-slate-500">
          Tổng các PĐX chưa từ chối
        </p>
      </div>

      <div class="flex flex-col gap-0.5 px-5 py-4">
        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
          PĐX đã duyệt
        </p>
        <VndAmount
          :amount="num(metrics.budget_proposal_approved_total)"
          class="text-lg font-bold text-emerald-700"
        />
        <p class="text-[11px] text-slate-500">
          Phiếu đề xuất được chấp thuận
        </p>
      </div>

      <!-- ĐNTT -->
      <div class="flex flex-col gap-0.5 px-5 py-4">
        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
          ĐNTT đã duyệt
        </p>
        <VndAmount
          :amount="num(metrics.budget_payment_approved_total)"
          class="text-lg font-bold text-blue-700"
        />
        <p class="text-[11px] text-slate-500">
          Đề nghị thanh toán được chấp thuận
        </p>
      </div>

      <div class="flex flex-col gap-0.5 px-5 py-4">
        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
          Đã thanh toán
        </p>
        <VndAmount
          :amount="num(metrics.budget_paid_total)"
          class="text-lg font-bold text-violet-700"
        />
        <p class="text-[11px] text-slate-500">
          Thực chi ghi nhận (ĐNTT paid)
        </p>
      </div>

      <!-- Tài khoản -->
      <div class="flex flex-col gap-0.5 px-5 py-4">
        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
          Mua thực tế
        </p>
        <VndAmount
          :amount="num(metrics.actual_purchase_total)"
          class="text-lg font-bold text-slate-800"
        />
        <p class="text-[11px] text-slate-500">
          Tổng chi phí tài khoản đã lập
        </p>
      </div>
    </div>

    <div
      class="grid grid-cols-3 divide-x divide-slate-100 border-t border-slate-100 bg-slate-50/60"
    >
      <div class="flex items-center gap-2 px-5 py-3">
        <span class="text-2xl font-bold text-emerald-700">
          {{ num(metrics.accounts_allocated_count) }}
        </span>
        <span class="text-[11px] leading-tight text-slate-500">TK đang<br>sử dụng</span>
      </div>
      <div class="flex items-center gap-2 px-5 py-3">
        <span class="text-2xl font-bold text-amber-600">
          {{ num(metrics.accounts_expiring_soon_count) }}
        </span>
        <span class="text-[11px] leading-tight text-slate-500">TK sắp<br>hết hạn</span>
      </div>
      <div class="flex items-center gap-2 px-5 py-3">
        <span class="text-2xl font-bold text-rose-600">
          {{ num(metrics.accounts_expired_count) }}
        </span>
        <span class="text-[11px] leading-tight text-slate-500">TK đã<br>hết hạn</span>
      </div>
    </div>
  </section>
</template>
