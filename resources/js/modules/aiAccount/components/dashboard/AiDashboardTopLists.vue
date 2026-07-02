<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import EmptyState from '@/shared/ui/EmptyState.vue';
import { formatVndCompact } from '@/modules/aiAccount/utils/formatVnd';

defineProps({
    loading: { type: Boolean, default: false },
    top: { type: Object, default: () => ({}) },
});

const panels = [
    {
        key: 'costly_products',
        title: 'Top chi phí sản phẩm',
        icon: 'budget',
        empty: 'Chưa có chi phí theo sản phẩm',
        rowKey: 'tool_name',
        labelKey: 'tool_name',
        value: (row) => formatVndCompact(row.cost_monthly),
        valueClass: 'text-brand',
    },
    {
        key: 'users_most_accounts',
        title: 'Top người dùng (nhiều TK)',
        icon: 'account',
        empty: 'Chưa có người dùng được gán tài khoản',
        rowKey: 'user_name',
        labelKey: 'user_name',
        value: (row) => `${row.account_count} TK`,
        valueClass: 'text-slate-700',
    },
    {
        key: 'expiring_soon',
        title: 'Sắp hết hạn gần nhất',
        icon: 'clock',
        empty: 'Không có tài khoản sắp hết hạn',
        rowKey: 'id',
        labelKey: 'tool_name',
        sub: (row) => `${row.expiry_date} · còn ${row.days_until_expiry} ngày`,
    },
];
</script>

<template>
  <div class="grid gap-4 lg:grid-cols-3">
    <section
      v-for="panel in panels"
      :key="panel.key"
      class="relative overflow-hidden rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm"
      :aria-label="panel.title"
    >
      <h3 class="mb-3 flex items-center gap-2 border-b border-slate-100 pb-3 font-display text-sm font-semibold text-slate-800">
        <AppIcon
          :name="panel.icon"
          :size="15"
          class="text-brand"
        />
        {{ panel.title }}
      </h3>

      <div
        v-if="loading"
        class="absolute inset-0 z-10 flex items-center justify-center bg-white/70 text-xs text-slate-500"
      >
        Đang tải…
      </div>

      <ol
        v-if="(top[panel.key] ?? []).length"
        class="space-y-0"
      >
        <li
          v-for="(row, i) in (top[panel.key] ?? []).slice(0, 10)"
          :key="row[panel.rowKey] ?? i"
          class="group flex gap-3 border-b border-slate-50 py-2.5 last:border-0"
        >
          <span
            class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-slate-100 text-[11px] font-bold tabular-nums text-slate-500 group-hover:bg-brand/10 group-hover:text-brand"
          >
            {{ i + 1 }}
          </span>
          <div class="min-w-0 flex-1">
            <div class="flex items-start justify-between gap-2">
              <span class="truncate text-sm text-slate-700">{{ row[panel.labelKey] }}</span>
              <span
                v-if="panel.value"
                class="shrink-0 text-sm font-medium tabular-nums"
                :class="panel.valueClass"
              >
                {{ panel.value(row) }}
              </span>
            </div>
            <p
              v-if="panel.sub"
              class="mt-0.5 text-xs text-slate-500"
            >
              {{ panel.sub(row) }}
            </p>
          </div>
        </li>
      </ol>
      <EmptyState
        v-else
        class="py-8"
        :title="panel.empty"
        :icon="panel.icon"
      />
    </section>
  </div>
</template>
