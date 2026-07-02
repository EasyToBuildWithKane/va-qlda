<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import VndAmount from '@/modules/aiAccount/components/VndAmount.vue';
import ProposalRowActions from '@/modules/aiAccount/components/ProposalRowActions.vue';

defineProps({
    rows: { type: Array, default: () => [] },
    expanded: { type: Object, required: true },
    visibleCols: { type: Object, required: true },
    highlightId: { type: String, default: null },
    canReview: { type: Boolean, default: false },
});

const emit = defineEmits([
    'toggle',
    'timeline',
    'edit',
    'approve',
    'reject',
    'delete',
    'create-payment-request',
    'approve-payment-request',
    'reject-payment-request',
    'mark-paid-payment-request',
]);

function pdxSimplifiedLabel(row) {
    if (row.status === 'rejected') return 'Từ chối';
    if (['pending', 'submitted', 'draft'].includes(row.status)) return 'Chờ duyệt';
    if (['approved', 'purchased', 'active'].includes(row.status)) return 'Đã duyệt';
    return row.status_label;
}

function pdxSimplifiedColor(row) {
    if (row.status === 'rejected') return 'rose';
    if (['pending', 'submitted', 'draft'].includes(row.status)) return 'amber';
    if (['approved', 'purchased', 'active'].includes(row.status)) return 'emerald';
    return row.status_color;
}

function dnttLabel(row) {
    if (!row.payment_request) return 'Chưa tạo';
    return row.payment_request.status_label;
}

function dnttColor(row) {
    if (!row.payment_request) return 'slate';
    return row.payment_request.status_color;
}
</script>

<template>
  <div class="proposal-workflow-list">
    <div
      class="sticky top-0 z-10 hidden border-b border-slate-200 bg-slate-50 text-[11px] font-semibold uppercase tracking-wide text-slate-500 sm:grid sm:grid-cols-[2rem_1fr_auto] sm:gap-2 sm:px-4 sm:py-2.5 lg:grid-cols-[2rem_minmax(0,1fr)_minmax(0,2fr)_auto]"
    >
      <span />
      <span>Mã phiếu · tóm tắt</span>
      <span class="hidden lg:block">Trạng thái quy trình</span>
      <span
        class="flex justify-center"
        aria-label="Thao tác"
      >
        <span class="sr-only">Thao tác</span>
        <AppIcon
          name="more-horizontal"
          :size="16"
          class="text-slate-400"
          aria-hidden="true"
        />
      </span>
    </div>

    <div
      v-if="!rows.length"
      class="px-5 py-12 text-center text-sm text-slate-400"
    >
      Không có phiếu đề xuất nào.
    </div>

    <div
      v-for="row in rows"
      :id="`proposal-row-${row.id}`"
      :key="row.id"
      class="border-b border-slate-100 last:border-b-0"
      :class="highlightId === row.id && 'bg-brand/5 ring-2 ring-inset ring-brand/20'"
    >
      <div
        class="flex flex-col gap-2 px-4 py-3 sm:grid sm:grid-cols-[2rem_1fr_auto] sm:items-center lg:grid-cols-[2rem_minmax(0,1fr)_minmax(0,2fr)_auto]"
      >
        <button
          type="button"
          class="grid h-8 w-8 shrink-0 place-items-center rounded-md text-slate-400 hover:bg-slate-100"
          :aria-expanded="!!expanded[row.id]"
          :title="expanded[row.id] ? 'Thu gọn' : 'Mở rộng'"
          @click="emit('toggle', row.id)"
        >
          <AppIcon
            name="chevron-down"
            :size="18"
            :class="expanded[row.id] ? 'rotate-180 transition' : 'transition'"
          />
        </button>

        <button
          type="button"
          class="min-w-0 text-left"
          @click="emit('toggle', row.id)"
        >
          <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
            <span class="font-mono text-sm font-bold text-brand">{{ row.proposal_code ?? '—' }}</span>
            <span
              v-if="visibleCols.proposer_name"
              class="text-sm font-medium text-slate-800"
            >{{ row.proposer_name }}</span>
            <span
              v-if="visibleCols.tool_name"
              class="truncate text-sm text-slate-600"
            >· {{ row.tool_name }}</span>
          </div>
          <div class="mt-0.5 flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-slate-500">
            <span v-if="visibleCols.proposer_department">{{ row.proposer_department ?? '—' }}</span>
            <span v-if="visibleCols.proposal_type">{{ row.proposal_type_label }}</span>
            <span
              v-if="visibleCols.cost_amount"
              class="font-medium text-slate-700"
            >
              <VndAmount
                :amount="row.cost_amount"
                compact
              />
              <span class="text-slate-400"> / {{ row.cost_unit_label }}</span>
            </span>
          </div>
        </button>

        <div class="flex flex-wrap items-center gap-2 lg:justify-start">
          <template v-if="visibleCols.overall_status">
            <span
              class="cursor-help"
              :title="row.approval_history_tooltip"
            >
              <Badge
                :label="row.overall_status?.label ?? '—'"
                :color="row.overall_status?.color ?? 'slate'"
              />
            </span>
          </template>
          <button
            type="button"
            class="text-xs font-medium text-brand hover:underline"
            @click.stop="emit('timeline', row)"
          >
            Tiến trình
          </button>
        </div>

        <div class="flex justify-end sm:col-span-1 lg:col-span-1">
          <ProposalRowActions
            :row="row"
            :can-review="canReview"
            @edit="emit('edit', row)"
            @approve="emit('approve', row)"
            @reject="emit('reject', row)"
            @delete="emit('delete', row)"
            @create-payment-request="emit('create-payment-request', row)"
            @approve-payment-request="(pr) => emit('approve-payment-request', pr)"
            @reject-payment-request="(pr) => emit('reject-payment-request', pr)"
            @mark-paid-payment-request="(pr) => emit('mark-paid-payment-request', pr)"
          />
        </div>
      </div>

      <div
        v-show="expanded[row.id]"
        class="border-t border-slate-100 bg-slate-50/60 px-4 py-4"
      >
        <div class="grid gap-4 lg:grid-cols-2">
          <section class="rounded-xl border border-brand/15 bg-white p-4 shadow-sm">
            <div class="mb-3 flex items-center gap-2">
              <AppIcon
                name="pdf"
                :size="18"
                class="text-brand"
              />
              <h3 class="text-sm font-bold text-slate-800">
                Phiếu đề xuất
              </h3>
              <Badge
                :label="pdxSimplifiedLabel(row)"
                :color="pdxSimplifiedColor(row)"
                class="ml-auto text-[11px]"
              />
            </div>
            <dl class="grid gap-2 text-sm sm:grid-cols-2">
              <div>
                <dt class="text-[11px] font-medium uppercase text-slate-400">
                  Mã phiếu
                </dt>
                <dd class="font-mono text-brand">
                  {{ row.proposal_code ?? '—' }}
                </dd>
              </div>
              <div>
                <dt class="text-[11px] font-medium uppercase text-slate-400">
                  Ngày tạo
                </dt>
                <dd>{{ row.created_at ? row.created_at.slice(0, 16) : '—' }}</dd>
              </div>
              <div>
                <dt class="text-[11px] font-medium uppercase text-slate-400">
                  Người tạo
                </dt>
                <dd>{{ row.created_by_name ?? row.proposer_name ?? '—' }}</dd>
              </div>
              <div>
                <dt class="text-[11px] font-medium uppercase text-slate-400">
                  Người duyệt
                </dt>
                <dd>{{ row.reviewed_by_name ?? '—' }}</dd>
              </div>
              <div>
                <dt class="text-[11px] font-medium uppercase text-slate-400">
                  Ngày duyệt
                </dt>
                <dd>{{ row.reviewed_at ? row.reviewed_at.slice(0, 16) : '—' }}</dd>
              </div>
              <div>
                <dt class="text-[11px] font-medium uppercase text-slate-400">
                  Trạng thái chi tiết
                </dt>
                <dd>
                  <Badge
                    :label="row.status_label"
                    :color="row.status_color"
                  />
                </dd>
              </div>
              <div
                v-if="row.review_notes || row.rejection_reason"
                class="sm:col-span-2"
              >
                <dt class="text-[11px] font-medium uppercase text-slate-400">
                  Ghi chú / lý do
                </dt>
                <dd class="text-slate-600">
                  {{ row.rejection_reason || row.review_notes || '—' }}
                </dd>
              </div>
            </dl>
          </section>

          <section class="rounded-xl border border-emerald-200/80 bg-white p-4 shadow-sm">
            <div class="mb-3 flex items-center gap-2">
              <AppIcon
                name="banknote"
                :size="18"
                class="text-emerald-700"
              />
              <h3 class="text-sm font-bold text-slate-800">
                Đề nghị thanh toán
              </h3>
              <Badge
                :label="dnttLabel(row)"
                :color="dnttColor(row)"
                class="ml-auto text-[11px]"
              />
            </div>

            <template v-if="row.payment_request">
              <dl class="grid gap-2 text-sm sm:grid-cols-2">
                <div>
                  <dt class="text-[11px] font-medium uppercase text-slate-400">
                    Mã ĐNTT
                  </dt>
                  <dd class="font-mono text-emerald-800">
                    {{ row.payment_request.payment_request_code ?? '—' }}
                  </dd>
                </div>
                <div>
                  <dt class="text-[11px] font-medium uppercase text-slate-400">
                    Ngày tạo
                  </dt>
                  <dd>{{ row.payment_request.created_at ? row.payment_request.created_at.slice(0, 16) : '—' }}</dd>
                </div>
                <div>
                  <dt class="text-[11px] font-medium uppercase text-slate-400">
                    Người tạo
                  </dt>
                  <dd>{{ row.payment_request.created_by_name ?? '—' }}</dd>
                </div>
                <div>
                  <dt class="text-[11px] font-medium uppercase text-slate-400">
                    Người duyệt
                  </dt>
                  <dd>{{ row.payment_request.reviewed_by_name ?? '—' }}</dd>
                </div>
                <div>
                  <dt class="text-[11px] font-medium uppercase text-slate-400">
                    Ngày duyệt
                  </dt>
                  <dd>{{ row.payment_request.reviewed_at ? row.payment_request.reviewed_at.slice(0, 16) : '—' }}</dd>
                </div>
                <div>
                  <dt class="text-[11px] font-medium uppercase text-slate-400">
                    Ngày thanh toán
                  </dt>
                  <dd>{{ row.payment_request.paid_at ? row.payment_request.paid_at.slice(0, 16) : '—' }}</dd>
                </div>
                <div>
                  <dt class="text-[11px] font-medium uppercase text-slate-400">
                    Số tiền
                  </dt>
                  <dd>
                    <VndAmount
                      :amount="row.payment_request.amount"
                      compact
                    />
                  </dd>
                </div>
                <div>
                  <dt class="text-[11px] font-medium uppercase text-slate-400">
                    Chứng từ
                  </dt>
                  <dd class="text-slate-600">
                    <template v-if="row.payment_request.payment_document_paths?.length">
                      {{ row.payment_request.payment_document_paths.length }} tệp đính kèm
                    </template>
                    <span v-else>—</span>
                  </dd>
                </div>
              </dl>
            </template>
            <p
              v-else
              class="text-sm text-slate-500"
            >
              Chưa lập đề nghị thanh toán cho phiếu này. Dùng menu thao tác để tạo ĐNTT sau khi PĐX được duyệt.
            </p>
          </section>
        </div>
      </div>
    </div>
  </div>
</template>
