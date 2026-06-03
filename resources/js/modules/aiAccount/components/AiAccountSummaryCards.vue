<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import { formatVnd } from '@/modules/aiAccount/utils/formatVnd';

const props = defineProps({
    cards: { type: Object, default: null },
});

const items = computed(() => {
    const c = props.cards;
    if (!c) return [];
    return [
        { label: 'Tổng tài khoản', value: c.total_accounts, icon: 'account', tone: 'text-brand', bg: 'bg-brand-50' },
        { label: 'Đang hoạt động', value: c.active_accounts, icon: 'done', tone: 'text-emerald-600', bg: 'bg-emerald-50' },
        { label: 'Sắp hết hạn', value: c.expiring_soon, icon: 'flag', tone: 'text-amber-600', bg: 'bg-amber-50' },
        {
            label: 'Chi phí / tháng',
            value: formatVnd(c.monthly_cost_active),
            icon: 'cost',
            tone: 'text-violet-600',
            bg: 'bg-violet-50',
            isText: true,
        },
    ];
});
</script>

<template>
  <div
    v-if="cards"
    class="mb-5 grid grid-cols-2 gap-4 sm:grid-cols-4"
  >
    <div
      v-for="item in items"
      :key="item.label"
      class="card flex items-center gap-3 p-4"
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
      <div class="min-w-0">
        <p class="truncate text-xs text-slate-500">
          {{ item.label }}
        </p>
        <p
          class="font-display font-bold leading-tight"
          :class="[item.tone, item.isText ? 'text-base sm:text-lg' : 'text-xl']"
        >
          {{ item.value }}
        </p>
      </div>
    </div>
  </div>
</template>
