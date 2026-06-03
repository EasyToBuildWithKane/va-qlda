<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import VndAmount from '@/modules/aiAccount/components/VndAmount.vue';
import { formatVndDisplay } from '@/modules/aiAccount/utils/formatVnd';

const props = defineProps({
    cards: { type: Object, default: null },
});

const items = computed(() => {
    const c = props.cards;
    if (!c) return [];
    const costDisplay = formatVndDisplay(c.monthly_cost_active);
    return [
        { label: 'Tổng tài khoản', value: c.total_accounts, icon: 'account', tone: 'text-brand', bg: 'bg-brand-50' },
        { label: 'Đang hoạt động', value: c.active_accounts, icon: 'done', tone: 'text-emerald-600', bg: 'bg-emerald-50' },
        { label: 'Sắp hết hạn', value: c.expiring_soon, icon: 'flag', tone: 'text-amber-600', bg: 'bg-amber-50' },
        {
            label: 'Chi phí / tháng',
            value: costDisplay.primary,
            sub: costDisplay.secondary,
            icon: 'cost',
            tone: 'text-violet-600',
            bg: 'bg-violet-50',
            isMoney: true,
        },
    ];
});
</script>

<template>
  <div
    v-if="cards"
    class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4"
  >
    <div
      v-for="item in items"
      :key="item.label"
      class="card flex items-center gap-3 p-3.5 sm:p-4"
    >
      <span
        class="grid h-10 w-10 shrink-0 place-items-center rounded-btn"
        :class="item.bg"
      >
        <AppIcon
          :name="item.icon"
          :size="20"
          :class="item.tone"
        />
      </span>
      <div class="min-w-0 flex-1">
        <p class="text-xs text-slate-500">
          {{ item.label }}
        </p>
        <template v-if="item.isMoney">
          <VndAmount
            :amount="cards.monthly_cost_active"
            suffix=" / tháng"
          />
        </template>
        <p
          v-else
          class="font-display text-xl font-bold leading-tight tabular-nums"
          :class="item.tone"
        >
          {{ item.value }}
        </p>
      </div>
    </div>
  </div>
</template>
