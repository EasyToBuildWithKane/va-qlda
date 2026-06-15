<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    profile: { type: Object, required: true },
});

const rows = computed(() => {
    const p = props.profile;
    const primaryTeam = p.teams?.[0] ?? null;
    return [
        {
            icon: 'settings',
            label: 'Vai trò hệ thống',
            value: p.account_role?.label || '—',
        },
        {
            icon: 'career',
            label: 'Cấp bậc',
            value: p.seniority?.label || '—',
        },
        { icon: 'org-teams', label: 'Nhóm QLDA', value: primaryTeam?.name || '—' },
        { icon: 'account', label: 'Quản lý trực tiếp', value: p.manager?.name || '—' },
        {
            icon: 'performance',
            label: 'Trạng thái',
            value: p.is_active ? 'Đang hoạt động' : 'Ngừng hoạt động',
        },
    ];
});
</script>

<template>
  <section class="rounded-2xl border border-slate-200/70 bg-white shadow-sm">
    <header class="flex items-center gap-2.5 border-b border-slate-100 px-5 py-3.5">
      <AppIcon
        name="briefcase"
        :size="16"
        class="text-slate-400"
      />
      <h2 class="text-sm font-semibold text-slate-800">
        Vận hành trên QLDA
      </h2>
    </header>

    <dl class="grid grid-cols-1 gap-x-6 gap-y-4 p-5 sm:grid-cols-2">
      <div
        v-for="r in rows"
        :key="r.label"
        class="flex items-start gap-2.5"
      >
        <div class="mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-slate-100 text-slate-500">
          <AppIcon
            :name="r.icon"
            :size="13"
          />
        </div>
        <div class="min-w-0">
          <dt class="text-[11px] uppercase tracking-wide text-slate-400">
            {{ r.label }}
          </dt>
          <dd class="truncate text-[13px] font-medium text-slate-700">
            {{ r.value }}
          </dd>
        </div>
      </div>
    </dl>
  </section>
</template>
