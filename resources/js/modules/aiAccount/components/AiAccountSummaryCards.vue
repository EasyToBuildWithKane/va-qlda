<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import VndAmount from '@/modules/aiAccount/components/VndAmount.vue';

const props = defineProps({
    cards: { type: Object, default: null },
    activeStatus: { type: String, default: 'all' },
    activePayment: { type: String, default: 'all' },
    /** Khi đang lọc — số liệu theo danh sách đã lọc */
    filteredOverlay: { type: Object, default: null },
});

const emit = defineEmits(['filter-status', 'filter-payment', 'open-cost-report']);

const COST_CARD_KEYS = [
    'monthly_cost_running',
    'monthly_cost_all',
    'monthly_cost_active',
    'monthly_cost_unpaid_renewal',
];

const display = computed(() => {
    const base = props.cards ?? {};
    const overlay = props.filteredOverlay;
    if (!overlay?.isFiltered) {
        return base;
    }
    const merged = { ...base, ...overlay };
    for (const key of COST_CARD_KEYS) {
        if (base[key] !== undefined) {
            merged[key] = base[key];
        }
    }
    return merged;
});

const items = computed(() => {
    const c = display.value;
    if (!c || c.total_accounts === undefined) return [];

    const monthlyRunning = c.monthly_cost_running ?? c.monthly_cost_all ?? c.monthly_cost_active ?? 0;

    return [
        {
            key: 'all',
            label: 'Tổng tài khoản',
            value: c.total_accounts,
            icon: 'account',
            tone: 'text-brand',
            ring: 'ring-brand/20',
            bg: 'bg-brand-50',
            clickable: true,
            filter: 'status',
        },
        {
            key: 'active',
            label: 'Đang hoạt động',
            value: c.active_accounts,
            icon: 'done',
            tone: 'text-emerald-600',
            ring: 'ring-emerald-200',
            bg: 'bg-emerald-50',
            clickable: true,
            filter: 'status',
        },
        {
            key: 'expiring_soon',
            label: 'Sắp hết hạn',
            value: c.expiring_soon,
            icon: 'flag',
            tone: 'text-amber-700',
            ring: 'ring-amber-300',
            bg: 'bg-amber-50',
            highlight: (c.expiring_soon ?? 0) > 0,
            clickable: true,
            filter: 'status',
        },
        {
            key: 'expired',
            label: 'Hết hạn',
            value: c.expired,
            icon: 'alert',
            tone: 'text-rose-700',
            ring: 'ring-rose-200',
            bg: 'bg-rose-50',
            highlight: (c.expired ?? 0) > 0,
            clickable: true,
            filter: 'status',
        },
        {
            key: 'cost',
            label: 'Chi phí / tháng',
            sub: 'Phiếu đã duyệt',
            icon: 'cost',
            tone: 'text-violet-600',
            ring: 'ring-violet-200',
            bg: 'bg-violet-50',
            isMoney: true,
            moneyAmount: monthlyRunning,
            clickable: false,
        },
        {
            key: 'renewal_unpaid',
            label: 'Chưa thanh toán GH',
            value: c.renewal_unpaid_count ?? 0,
            sub: c.monthly_cost_unpaid_renewal > 0 ? 'Chi phí ước tính' : null,
            icon: 'cost',
            tone: 'text-amber-800',
            ring: 'ring-amber-300',
            bg: 'bg-amber-50',
            highlight: (c.renewal_unpaid_count ?? 0) > 0,
            clickable: true,
            filter: 'payment',
            isMoneySub: c.monthly_cost_unpaid_renewal > 0,
            moneySubAmount: c.monthly_cost_unpaid_renewal,
        },
        {
            key: 'renewal_paid',
            label: 'Đã thanh toán GH',
            value: c.renewal_paid_count ?? 0,
            icon: 'check',
            tone: 'text-emerald-700',
            ring: 'ring-emerald-200',
            bg: 'bg-emerald-50',
            clickable: true,
            filter: 'payment',
        },
    ];
});

function isActive(item) {
    if (item.filter === 'payment') {
        const map = {
            renewal_unpaid: 'unpaid',
            renewal_paid: 'paid',
        };
        return props.activePayment === map[item.key];
    }
    return props.activeStatus === item.key;
}

function onCardClick(item) {
    if (!item.clickable) return;
    if (item.filter === 'cost') {
        emit('open-cost-report');
        return;
    }
    if (item.filter === 'payment') {
        const map = {
            renewal_unpaid: 'unpaid',
            renewal_paid: 'paid',
        };
        emit('filter-payment', map[item.key] ?? 'all');
        return;
    }
    emit('filter-status', item.key);
}
</script>

<template>
  <div
    v-if="display.total_accounts !== undefined"
    class="mb-4 space-y-2.5"
  >
    <p
      v-if="filteredOverlay?.isFiltered"
      class="text-xs text-slate-500"
    >
      Số liệu đếm theo <span class="font-medium text-slate-700">bộ lọc hiện tại</span>
      ({{ filteredOverlay.filtered_count }} tài khoản).
      Chi phí thẻ vẫn theo <span class="font-medium text-slate-700">tất cả phiếu đã duyệt</span>.
    </p>
    <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-7 sm:gap-3">
      <button
        v-for="item in items"
        :key="item.key"
        type="button"
        class="card flex min-h-[4.5rem] flex-col justify-center gap-2 p-3 text-left transition sm:p-3.5"
        :class="[
          item.clickable ? 'cursor-pointer hover:shadow-md' : 'cursor-default',
          item.highlight ? 'ring-2 ring-amber-400/60' : '',
          isActive(item) && item.clickable ? `ring-2 ${item.ring}` : '',
        ]"
        :disabled="!item.clickable"
        @click="onCardClick(item)"
      >
        <div class="flex items-center gap-2.5">
          <span
            class="grid h-9 w-9 shrink-0 place-items-center rounded-lg"
            :class="item.bg"
          >
            <AppIcon
              :name="item.icon"
              :size="18"
              :class="item.tone"
            />
          </span>
          <div class="min-w-0 flex-1">
            <p class="text-[11px] font-medium leading-tight text-slate-500">
              {{ item.label }}
            </p>
            <p
              v-if="item.sub && !item.isMoney"
              class="text-[10px] text-slate-400"
            >
              {{ item.sub }}
            </p>
            <VndAmount
              v-if="item.isMoney"
              :amount="item.moneyAmount"
              suffix=" / tháng"
              compact
            />
            <p
              v-else
              class="font-display text-lg font-bold leading-tight tabular-nums xl:text-xl"
              :class="item.tone"
            >
              {{ item.value }}
            </p>
          </div>
        </div>
        <VndAmount
          v-if="item.isMoneySub"
          :amount="item.moneySubAmount"
          suffix=" / tháng"
          compact
          class="pl-11 text-xs"
        />
      </button>
    </div>
  </div>
</template>
