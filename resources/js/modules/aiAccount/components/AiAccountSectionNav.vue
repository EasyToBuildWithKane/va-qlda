<script setup>
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';

defineProps({
    /** 'accounts' | 'proposals' | 'cost-by-group' */
    active: { type: String, required: true },
    accountsBadge: { type: [String, Number], default: null },
    proposalsBadge: { type: [String, Number], default: null },
});

const TABS = [
    {
        key: 'accounts',
        label: 'Tài khoản AI',
        hint: 'License đang dùng',
        icon: 'account',
        routeName: 'ai-accounts.index',
    },
    {
        key: 'proposals',
        label: 'Phiếu đề xuất',
        hint: 'Đề xuất · duyệt · chi phí',
        icon: 'budget',
        routeName: 'ai-accounts.cost-report',
    },
    {
        key: 'cost-by-group',
        label: 'Chi phí theo nhóm',
        hint: 'Tổng hợp theo nhóm chức năng',
        icon: 'cost',
        routeName: 'ai-accounts.cost-by-group',
    },
];
</script>

<template>
  <nav
    class="flex shrink-0 flex-wrap items-center gap-1 rounded-xl border border-slate-200 bg-slate-50/80 p-1"
    aria-label="Khu vực quản lý AI"
  >
    <Link
      v-for="tab in TABS"
      :key="tab.key"
      :href="route(tab.routeName)"
      preserve-scroll
      class="inline-flex min-w-0 items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition"
      :class="active === tab.key
        ? 'bg-white text-brand shadow-sm ring-1 ring-slate-200/80'
        : 'text-slate-600 hover:bg-white/70 hover:text-slate-800'"
      :aria-current="active === tab.key ? 'page' : undefined"
    >
      <AppIcon
        :name="tab.icon"
        :size="16"
        :class="active === tab.key ? 'text-brand' : 'text-slate-400'"
      />
      <span class="flex min-w-0 flex-col items-start leading-tight">
        <span class="flex items-center gap-1.5">
          <span>{{ tab.label }}</span>
          <span
            v-if="tab.key === 'accounts' && accountsBadge != null && accountsBadge !== ''"
            class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-brand/10 px-1.5 text-[10px] font-bold text-brand"
          >
            {{ accountsBadge }}
          </span>
          <span
            v-if="tab.key === 'proposals' && proposalsBadge != null && proposalsBadge !== ''"
            class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-brand/10 px-1.5 text-[10px] font-bold text-brand"
          >
            {{ proposalsBadge }}
          </span>
        </span>
        <span
          class="hidden text-[10px] font-normal text-slate-400 sm:block"
          :class="active === tab.key && 'text-brand/70'"
        >
          {{ tab.hint }}
        </span>
      </span>
    </Link>
  </nav>
</template>
