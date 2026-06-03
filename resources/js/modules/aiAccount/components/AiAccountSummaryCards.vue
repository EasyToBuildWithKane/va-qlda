<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import VndAmount from '@/modules/aiAccount/components/VndAmount.vue';

const props = defineProps({
    cards: { type: Object, default: null },
    activeStatus: { type: String, default: 'all' },
});

const emit = defineEmits(['filter-status']);

const items = computed(() => {
    const c = props.cards;
    if (!c) return [];
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
        },
        {
            key: 'cost',
            label: 'Chi phí / tháng',
            icon: 'cost',
            tone: 'text-violet-600',
            ring: 'ring-violet-200',
            bg: 'bg-violet-50',
            isMoney: true,
            clickable: false,
        },
    ];
});

function onCardClick(item) {
    if (!item.clickable) return;
    if (item.key === 'cost') return;
    emit('filter-status', item.key);
}
</script>

<template>
  <div
    v-if="cards"
    class="mb-4 grid grid-cols-2 gap-2.5 sm:grid-cols-4 sm:gap-3"
  >
    <button
      v-for="item in items"
      :key="item.key"
      type="button"
      class="card flex items-center gap-3 p-3 text-left transition sm:p-3.5"
      :class="[
        item.clickable ? 'cursor-pointer hover:shadow-md' : 'cursor-default',
        item.highlight ? 'ring-2 ring-amber-400/60' : '',
        activeStatus === item.key && item.clickable ? `ring-2 ${item.ring}` : '',
      ]"
      :disabled="!item.clickable"
      @click="onCardClick(item)"
    >
      <span
        class="grid h-9 w-9 shrink-0 place-items-center rounded-lg sm:h-10 sm:w-10"
        :class="item.bg"
      >
        <AppIcon
          :name="item.icon"
          :size="18"
          :class="item.tone"
        />
      </span>
      <div class="min-w-0 flex-1">
        <p class="text-[11px] font-medium text-slate-500 sm:text-xs">
          {{ item.label }}
        </p>
        <VndAmount
          v-if="item.isMoney"
          :amount="cards.monthly_cost_active"
          suffix=" / tháng"
        />
        <p
          v-else
          class="font-display text-lg font-bold leading-tight tabular-nums sm:text-xl"
          :class="item.tone"
        >
          {{ item.value }}
        </p>
      </div>
    </button>
  </div>
</template>
