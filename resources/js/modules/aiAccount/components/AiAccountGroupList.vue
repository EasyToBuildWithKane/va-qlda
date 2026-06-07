<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import AiAccountRowActions from '@/modules/aiAccount/components/AiAccountRowActions.vue';
import VndAmount from '@/modules/aiAccount/components/VndAmount.vue';
import { costUnitSuffix } from '@/modules/aiAccount/utils/formatVnd';
import { formatDaysLeftLabel, resolveDaysLeft } from '@/modules/aiAccount/utils/daysUntilExpiry';
import { statusSelectClass, statusTextClass } from '@/modules/aiAccount/utils/accountStatusStyle';

defineProps({
    groups: { type: Array, default: () => [] },
    expanded: { type: Object, required: true },
    loading: { type: Boolean, default: false },
    statusOptions: { type: Array, default: () => [] },
    colVisible: {
        type: Object,
        default: () => ({
            license: true,
            email: true,
            purchase: true,
            expiry: true,
            cost: true,
            lifecycle: false,
            status: true,
        }),
    },
    canManagePasswordViewers: { type: Boolean, default: false },
});

const emit = defineEmits([
    'toggle',
    'edit',
    'delete',
    'renew',
    'status-change',
    'password-viewers',
    'renewal-payment',
]);

function onStatusChange(row, event) {
    const next = event.target.value;
    if (next === row.status) return;
    emit('status-change', row, next);
}

function onRenewalPayment(row, status) {
    if (status === row.renewal_payment_status) return;
    emit('renewal-payment', row, status);
}

function rowClasses(row) {
    if (row.urgency === 'expired') {
        return 'bg-rose-50/90 border-l-4 border-l-rose-500';
    }
    if (row.urgency === 'expiring_soon') {
        return 'bg-amber-50/80 border-l-4 border-l-amber-500';
    }
    return 'border-l-4 border-l-transparent';
}

function expiryDisplay(row) {
    const date = row.expiry_date ?? '—';
    const left = formatDaysLeftLabel(resolveDaysLeft(row), row.status);
    if (!left) {
        return { date, hint: null, urgent: false };
    }
    return { date, hint: left.text, urgent: left.urgent };
}
</script>

<template>
  <div>
    <div
      v-if="loading"
      class="px-5 py-14 text-center text-sm text-slate-500"
    >
      Đang tải danh sách…
    </div>

    <div
      v-else-if="groups.length === 0"
      class="px-5 py-14 text-center text-sm text-slate-500"
    >
      Không có tài khoản phù hợp với bộ lọc hoặc từ khóa tìm kiếm.
    </div>

    <div
      v-else
      class="divide-y divide-slate-100"
    >
      <section
        v-for="g in groups"
        :key="g.group"
        class="bg-white"
      >
        <button
          type="button"
          class="flex w-full flex-wrap items-center gap-x-3 gap-y-2 px-4 py-3 text-left transition-colors hover:bg-slate-50/90 sm:px-5"
          :class="g.has_warning ? 'bg-amber-50/30' : ''"
          @click="emit('toggle', g.group)"
        >
          <span class="flex min-w-0 flex-1 items-center gap-2">
            <span
              class="h-2.5 w-2.5 shrink-0 rounded-full ring-2 ring-white"
              :style="{ backgroundColor: g.dot_color }"
            />
            <span class="font-semibold text-slate-800">{{ g.group_label ?? g.group }}</span>
            <span
              v-if="g.warning_count > 0"
              class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-900"
            >
              {{ g.warning_count }} cần chú ý
            </span>
            <span
              v-if="g.proposal_monthly_pending_sync"
              class="inline-flex items-center rounded-full bg-violet-100 px-2 py-0.5 text-[11px] font-medium text-violet-800"
              title="Phiếu đã duyệt, chưa lập tài khoản — đã gồm trong chi phí nhóm"
            >
              + phiếu chưa lập TK
            </span>
          </span>
          <span class="flex flex-wrap items-center gap-x-3 text-xs text-slate-600 sm:text-sm">
            <span class="tabular-nums font-medium">{{ g.total }} TK</span>
            <VndAmount
              :amount="g.total_cost_monthly"
              suffix="/tháng"
              class="text-slate-600"
              title="Theo phiếu đề xuất đã duyệt"
            />
          </span>
          <AppIcon
            name="chevron-down"
            :size="18"
            class="ml-auto shrink-0 text-slate-400 transition-transform duration-200"
            :class="expanded[g.group] ? 'rotate-180' : ''"
          />
        </button>

        <div v-show="expanded[g.group]">
          <div class="overflow-x-auto border-t border-slate-100">
            <table class="w-full min-w-[720px] text-left text-sm">
              <thead class="sticky top-0 z-[1] bg-slate-50/95 text-[11px] font-semibold uppercase tracking-wide text-slate-500 backdrop-blur-sm">
                <tr>
                  <th class="px-4 py-2.5 sm:px-5">
                    Công cụ
                  </th>
                  <th
                    v-if="colVisible.license"
                    class="hidden px-4 py-2.5 sm:table-cell sm:px-5"
                  >
                    License
                  </th>
                  <th
                    v-if="colVisible.email"
                    class="px-4 py-2.5 sm:px-5"
                  >
                    Email
                  </th>
                  <th
                    v-if="colVisible.purchase"
                    class="hidden px-4 py-2.5 md:table-cell md:px-5"
                  >
                    Ngày mua
                  </th>
                  <th
                    v-if="colVisible.expiry"
                    class="px-4 py-2.5 sm:px-5"
                  >
                    Ngày hết hạn
                  </th>
                  <th
                    v-if="colVisible.cost"
                    class="hidden px-4 py-2.5 md:table-cell md:px-5"
                  >
                    Chi phí
                  </th>
                  <th
                    v-if="colVisible.lifecycle"
                    class="hidden px-4 py-2.5 lg:table-cell lg:px-5"
                  >
                    Vòng đời
                  </th>
                  <th
                    v-if="colVisible.status"
                    class="px-4 py-2.5 sm:px-5"
                  >
                    Trạng thái
                  </th>
                  <th
                    v-if="colVisible.payment"
                    class="hidden px-4 py-2.5 lg:table-cell lg:px-5"
                  >
                    Thanh toán GH
                  </th>
                  <th class="px-4 py-2.5 text-right sm:px-5">
                    Thao tác
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="row in g.accounts"
                  :key="row.id"
                  class="transition-colors hover:brightness-[0.98]"
                  :class="rowClasses(row)"
                >
                  <td class="px-4 py-3 sm:px-5">
                    <p class="font-medium text-slate-900">
                      {{ row.tool_name }}
                    </p>
                    <p
                      v-if="expiryDisplay(row).hint"
                      class="mt-0.5 text-xs font-semibold tabular-nums"
                      :class="expiryDisplay(row).urgent ? (row.status === 'expired' ? 'text-rose-700' : 'text-amber-800') : 'text-slate-500'"
                    >
                      {{ expiryDisplay(row).hint }}
                    </p>
                    <p
                      v-if="row.proposal_code"
                      class="mt-0.5 font-mono text-[11px] text-slate-500"
                    >
                      {{ row.proposal_code }}
                    </p>
                    <p
                      v-if="colVisible.license"
                      class="mt-0.5 text-xs text-slate-500 sm:hidden"
                    >
                      {{ row.license_type }}
                    </p>
                  </td>
                  <td
                    v-if="colVisible.license"
                    class="hidden px-4 py-3 text-slate-600 sm:table-cell sm:px-5"
                  >
                    {{ row.license_type }}
                  </td>
                  <td
                    v-if="colVisible.email"
                    class="max-w-[12rem] px-4 py-3 text-slate-600 sm:max-w-[14rem] sm:px-5"
                  >
                    <span
                      class="block truncate"
                      :title="row.email_registered"
                    >{{ row.email_registered }}</span>
                  </td>
                  <td
                    v-if="colVisible.purchase"
                    class="hidden px-4 py-3 tabular-nums text-slate-600 md:table-cell md:px-5"
                  >
                    {{ row.purchase_date ?? '—' }}
                  </td>
                  <td
                    v-if="colVisible.expiry"
                    class="px-4 py-3 sm:px-5"
                  >
                    <span
                      class="font-medium tabular-nums"
                      :class="expiryDisplay(row).urgent ? 'text-amber-900' : 'text-slate-700'"
                    >{{ expiryDisplay(row).date }}</span>
                    <p
                      v-if="expiryDisplay(row).hint"
                      class="mt-0.5 text-xs font-semibold"
                      :class="row.status === 'expired' ? 'text-rose-700' : 'text-amber-800'"
                    >
                      {{ expiryDisplay(row).hint }}
                    </p>
                  </td>
                  <td
                    v-if="colVisible.cost"
                    class="hidden px-4 py-3 md:table-cell md:px-5"
                  >
                    <VndAmount :amount="row.cost_amount" />
                    <p class="text-xs text-slate-500">
                      {{ costUnitSuffix(row.cost_unit) }}
                    </p>
                  </td>
                  <td
                    v-if="colVisible.lifecycle"
                    class="hidden px-4 py-3 lg:table-cell lg:px-5"
                  >
                    <span
                      v-if="row.lifecycle_label"
                      class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium"
                      :class="{
                        'bg-emerald-100 text-emerald-800': row.lifecycle_status === 'in_use',
                        'bg-blue-100 text-blue-800': row.lifecycle_status === 'allocated',
                        'bg-violet-100 text-violet-800': row.lifecycle_status === 'purchased',
                        'bg-rose-100 text-rose-800': row.lifecycle_status === 'expired',
                        'bg-slate-100 text-slate-600': row.lifecycle_status === 'not_purchased' || row.lifecycle_status === 'stopped',
                      }"
                    >
                      {{ row.lifecycle_label }}
                    </span>
                  </td>
                  <td
                    v-if="colVisible.status"
                    class="px-4 py-3 sm:px-5"
                  >
                    <select
                      v-if="row.can_update_status"
                      :value="row.status"
                      class="input h-9 w-full min-w-[9.5rem] max-w-[11rem] border px-2 text-xs font-medium"
                      :class="statusSelectClass(row.status)"
                      :title="row.status_locked ? 'Trạng thái đã chỉnh thủ công' : 'Cập nhật khi gói hết sớm hơn hạn trên phiếu'"
                      @change="onStatusChange(row, $event)"
                    >
                      <option
                        v-for="opt in statusOptions"
                        :key="opt.value"
                        :value="opt.value"
                      >
                        {{ opt.label }}
                      </option>
                    </select>
                    <span
                      v-else
                      class="text-sm font-medium"
                      :class="statusTextClass(row.status)"
                    >
                      {{ row.status_label }}
                    </span>
                    <p
                      v-if="row.status_locked && row.can_update_status"
                      class="mt-1 text-[10px] text-slate-500"
                    >
                      Đã chỉnh thủ công
                    </p>
                  </td>
                  <td
                    v-if="colVisible.payment"
                    class="hidden px-4 py-3 lg:table-cell lg:px-5"
                  >
                    <template v-if="row.show_renewal_payment">
                      <div
                        v-if="row.can_update_renewal_payment"
                        class="inline-flex flex-col gap-1"
                      >
                        <div class="inline-flex rounded-lg border border-slate-200 p-0.5">
                          <button
                            type="button"
                            class="rounded-md px-2 py-1 text-[11px] font-semibold transition"
                            :class="row.renewal_payment_status === 'paid'
                              ? 'bg-emerald-600 text-white shadow-sm'
                              : 'text-slate-500 hover:bg-slate-50'"
                            title="Đã thanh toán gia hạn"
                            @click="onRenewalPayment(row, 'paid')"
                          >
                            Đã TT
                          </button>
                          <button
                            type="button"
                            class="rounded-md px-2 py-1 text-[11px] font-semibold transition"
                            :class="row.renewal_payment_status === 'unpaid'
                              ? 'bg-amber-500 text-white shadow-sm'
                              : 'text-slate-500 hover:bg-slate-50'"
                            title="Chưa thanh toán — sẽ nhận email nhắc"
                            @click="onRenewalPayment(row, 'unpaid')"
                          >
                            Chưa TT
                          </button>
                        </div>
                        <p
                          v-if="row.renewal_payment_status === 'unpaid' && row.status === 'expired'"
                          class="text-[10px] font-medium text-amber-800"
                        >
                          Nhắc email nếu quên TT
                        </p>
                        <p
                          v-else-if="row.renewal_paid_at"
                          class="text-[10px] text-slate-500"
                        >
                          TT {{ row.renewal_paid_at }}
                        </p>
                      </div>
                      <span
                        v-else
                        class="text-xs font-medium"
                        :class="row.renewal_payment_status === 'paid' ? 'text-emerald-700' : 'text-amber-800'"
                      >
                        {{ row.renewal_payment_status_label }}
                      </span>
                    </template>
                    <span
                      v-else
                      class="text-xs text-slate-400"
                    >—</span>
                  </td>
                  <td class="px-4 py-3 text-right sm:px-5">
                    <AiAccountRowActions
                      :row="row"
                      :can-manage-password-viewers="canManagePasswordViewers"
                      @edit="emit('edit', $event)"
                      @renew="emit('renew', $event)"
                      @delete="emit('delete', $event)"
                      @password-viewers="emit('password-viewers', $event)"
                    />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>
