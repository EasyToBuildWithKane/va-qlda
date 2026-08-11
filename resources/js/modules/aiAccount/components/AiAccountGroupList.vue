<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import AiAccountRowActions from '@/modules/aiAccount/components/AiAccountRowActions.vue';
import VndAmount from '@/modules/aiAccount/components/VndAmount.vue';
import { costUnitSuffix } from '@/modules/aiAccount/utils/formatVnd';
import { formatDaysLeftLabel, resolveDaysLeft } from '@/modules/aiAccount/utils/daysUntilExpiry';
import { statusSelectClass, statusTextClass } from '@/modules/aiAccount/utils/accountStatusStyle';
import { displayOrEmpty } from '@/shared/utils/emptyDisplay';

defineProps({
    groups: { type: Array, default: () => [] },
    expanded: { type: Object, required: true },
    loading: { type: Boolean, default: false },
    statusOptions: { type: Array, default: () => [] },
    colVisible: {
        type: Object,
        default: () => ({
            email: true,
            purchase: true,
            expiry: true,
            purchase_type: true,
            proposal_approved: true,
            proposal_sent: false,
            payment_sent: false,
            cost: true,
            status: true,
        }),
    },
});

const emit = defineEmits([
    'toggle',
    'edit',
    'delete',
    'renew',
    'status-change',
]);

function onStatusChange(row, event) {
    const next = event.target.value;
    if (next === row.status) return;
    emit('status-change', row, next);
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
    const date = displayOrEmpty(row.expiry_date, 'Chưa cập nhật');
    const left = formatDaysLeftLabel(resolveDaysLeft(row), row.status);
    if (!left) {
        return { date, hint: null, urgent: false };
    }
    return { date, hint: left.text, urgent: left.urgent };
}

function formatDate(value) {
    return displayOrEmpty(value, 'Chưa cập nhật');
}
</script>

<template>
  <div>
    <div
      v-if="loading"
      class="px-3 py-10 text-center text-sm text-slate-500 sm:px-4"
    >
      Đang tải danh sách…
    </div>

    <div
      v-else-if="groups.length === 0"
      class="px-3 py-10 text-center text-sm text-slate-500 sm:px-4"
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
          class="flex w-full flex-wrap items-center gap-x-3 gap-y-2 px-3 py-2.5 text-left transition-colors hover:bg-slate-50/90 sm:px-4"
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
          </span>
          <span class="flex flex-wrap items-center gap-x-3 text-xs text-slate-600 sm:text-sm">
            <span class="tabular-nums font-medium">{{ g.total }} TK</span>
            <VndAmount
              :amount="g.total_cost_monthly"
              suffix="/tháng"
              class="text-slate-600"
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
                  <th class="px-3 py-2 sm:px-4">
                    Công cụ
                  </th>
                  <th
                    v-if="colVisible.email"
                    class="px-3 py-2 sm:px-4"
                  >
                    Email
                  </th>
                  <th
                    v-if="colVisible.purchase"
                    class="hidden px-3 py-2 sm:table-cell sm:px-4"
                  >
                    Ngày mua
                  </th>
                  <th
                    v-if="colVisible.expiry"
                    class="px-3 py-2 sm:px-4"
                  >
                    Hết hạn
                  </th>
                  <th
                    v-if="colVisible.purchase_type"
                    class="hidden px-3 py-2 sm:table-cell sm:px-4"
                  >
                    Loại mua
                  </th>
                  <th
                    v-if="colVisible.proposal_approved"
                    class="hidden px-3 py-2 sm:table-cell sm:px-4"
                  >
                    Duyệt PĐX
                  </th>
                  <th
                    v-if="colVisible.proposal_sent"
                    class="hidden px-3 py-2 lg:table-cell lg:px-4"
                  >
                    Gửi PĐX
                  </th>
                  <th
                    v-if="colVisible.payment_sent"
                    class="hidden px-3 py-2 lg:table-cell lg:px-4"
                  >
                    Gửi ĐNTT
                  </th>
                  <th
                    v-if="colVisible.cost"
                    class="px-3 py-2 sm:px-4"
                  >
                    Chi phí
                  </th>
                  <th
                    v-if="colVisible.status"
                    class="px-3 py-2 sm:px-4"
                  >
                    Trạng thái
                  </th>
                  <th class="px-3 py-2 text-right sm:px-4">
                    Thao tác
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr
                  v-for="row in g.accounts"
                  :key="row.id"
                  class="transition-colors hover:bg-slate-50/70"
                  :class="rowClasses(row)"
                >
                  <td class="px-3 py-2.5 sm:px-4">
                    <div class="flex flex-wrap items-center gap-1.5">
                      <span class="font-medium text-slate-900">
                        {{ row.tool_name }}
                      </span>
                      <span
                        class="inline-flex items-center rounded-md px-1.5 py-0.5 text-[10px] font-semibold ring-1"
                        :class="row.login_method === 'google'
                          ? 'bg-sky-50 text-sky-800 ring-sky-200'
                          : 'bg-slate-50 text-slate-700 ring-slate-200'"
                      >
                        {{ row.login_method_label || (row.login_method === 'google' ? 'Google' : 'Thường') }}
                      </span>
                      <a
                        v-if="row.purchase_url"
                        :href="row.purchase_url"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center text-brand hover:underline"
                        title="Mở link chỗ mua"
                        @click.stop
                      >
                        <AppIcon
                          name="external-link"
                          :size="12"
                        />
                      </a>
                    </div>
                    <div
                      v-if="row.has_password && row.password"
                      class="mt-0.5 font-mono text-[11px] text-slate-500"
                    >
                      MK: {{ row.password }}
                    </div>
                  </td>
                  <td
                    v-if="colVisible.email"
                    class="px-3 py-2.5 sm:px-4"
                  >
                    <span class="break-all text-slate-700">{{ row.email_registered }}</span>
                  </td>
                  <td
                    v-if="colVisible.purchase"
                    class="hidden px-3 py-2.5 tabular-nums text-slate-600 sm:table-cell sm:px-4"
                  >
                    {{ formatDate(row.purchase_date) }}
                  </td>
                  <td
                    v-if="colVisible.expiry"
                    class="px-3 py-2.5 sm:px-4"
                  >
                    <div class="tabular-nums text-slate-700">
                      {{ expiryDisplay(row).date }}
                    </div>
                    <div
                      v-if="expiryDisplay(row).hint"
                      class="text-[11px]"
                      :class="expiryDisplay(row).urgent ? 'font-semibold text-rose-600' : 'text-slate-500'"
                    >
                      {{ expiryDisplay(row).hint }}
                    </div>
                  </td>
                  <td
                    v-if="colVisible.purchase_type"
                    class="hidden px-3 py-2.5 sm:table-cell sm:px-4"
                  >
                    <span
                      class="inline-flex items-center rounded-md px-1.5 py-0.5 text-[10px] font-semibold ring-1"
                      :class="row.purchase_type === 'renewal'
                        ? 'bg-violet-50 text-violet-800 ring-violet-200'
                        : 'bg-sky-50 text-sky-800 ring-sky-200'"
                    >
                      {{ row.purchase_type_label || (row.purchase_type === 'renewal' ? 'Gia hạn' : 'Mua mới') }}
                    </span>
                  </td>
                  <td
                    v-if="colVisible.proposal_approved"
                    class="hidden px-3 py-2.5 sm:table-cell sm:px-4"
                  >
                    <span
                      class="inline-flex items-center rounded-md px-1.5 py-0.5 text-[10px] font-semibold ring-1"
                      :class="row.proposal_approved
                        ? 'bg-emerald-50 text-emerald-800 ring-emerald-200'
                        : 'bg-amber-50 text-amber-900 ring-amber-200'"
                    >
                      {{ row.proposal_approved_label || (row.proposal_approved ? 'Đã duyệt' : 'Chưa duyệt') }}
                    </span>
                  </td>
                  <td
                    v-if="colVisible.proposal_sent"
                    class="hidden px-3 py-2.5 tabular-nums text-slate-600 lg:table-cell lg:px-4"
                  >
                    {{ formatDate(row.proposal_sent_at) }}
                  </td>
                  <td
                    v-if="colVisible.payment_sent"
                    class="hidden px-3 py-2.5 tabular-nums text-slate-600 lg:table-cell lg:px-4"
                  >
                    {{ formatDate(row.payment_request_sent_at) }}
                  </td>
                  <td
                    v-if="colVisible.cost"
                    class="px-3 py-2.5 sm:px-4"
                  >
                    <VndAmount
                      :amount="row.cost_amount"
                      :suffix="'/' + costUnitSuffix(row.cost_unit)"
                    />
                    <div class="text-[11px] text-slate-500">
                      ≈ <VndAmount
                        :amount="row.cost_monthly"
                        suffix="/tháng"
                      />
                    </div>
                  </td>
                  <td
                    v-if="colVisible.status"
                    class="px-3 py-2.5 sm:px-4"
                  >
                    <select
                      v-if="row.can_update_status"
                      class="input h-8 max-w-[10rem] text-xs"
                      :class="statusSelectClass(row.status)"
                      :value="row.status"
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
                      class="text-xs font-medium"
                      :class="statusTextClass(row.status)"
                    >
                      {{ row.status_label }}
                    </span>
                  </td>
                  <td class="px-3 py-2.5 text-right sm:px-4">
                    <AiAccountRowActions
                      :row="row"
                      @edit="emit('edit', $event)"
                      @renew="emit('renew', $event)"
                      @delete="emit('delete', $event)"
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
