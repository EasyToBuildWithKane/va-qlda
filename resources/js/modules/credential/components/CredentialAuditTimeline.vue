<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import { datetime } from '@/composables/useFormat';

defineProps({
    logs: { type: Array, default: () => [] },
});

const actionIcon = {
    viewed_password: 'eye',
    copied_password: 'copy',
    edited: 'edit',
    updated: 'edit',
    changed_password: 'refresh',
    created: 'add',
    deleted: 'delete',
    access_granted: 'people',
    access_revoked: 'people',
};

const actionTone = {
    viewed_password: 'sky',
    copied_password: 'violet',
    edited: 'amber',
    updated: 'amber',
    changed_password: 'emerald',
    created: 'emerald',
    deleted: 'rose',
    access_granted: 'brand',
    access_revoked: 'slate',
};

function toneRing(tone) {
    const map = {
        brand: 'ring-brand/25 bg-brand/5 text-brand',
        sky: 'ring-sky-200 bg-sky-50 text-sky-700',
        violet: 'ring-violet-200 bg-violet-50 text-violet-700',
        amber: 'ring-amber-200 bg-amber-50 text-amber-800',
        emerald: 'ring-emerald-200 bg-emerald-50 text-emerald-700',
        rose: 'ring-rose-200 bg-rose-50 text-rose-700',
        slate: 'ring-slate-200 bg-slate-50 text-slate-600',
    };
    return map[tone] || map.slate;
}
</script>

<template>
  <ul class="space-y-2">
    <li
      v-for="log in logs"
      :key="log.id"
      class="flex gap-3 rounded-lg border border-slate-200/80 bg-white px-3 py-3 shadow-sm"
    >
      <span
        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full ring-2"
        :class="toneRing(actionTone[log.action?.value] || 'slate')"
      >
        <AppIcon
          :name="actionIcon[log.action?.value] || 'info'"
          :size="16"
        />
      </span>
      <div class="min-w-0 flex-1">
        <p class="text-sm font-medium text-slate-800">
          {{ log.action?.label || log.action?.value }}
          <span
            v-if="log.account"
            class="font-normal text-slate-600"
          > · {{ log.account.display_name }}</span>
        </p>
        <p class="mt-0.5 text-xs text-slate-500">
          {{ datetime(log.created_at) }}
          <span
            v-if="log.ip_address"
            class="text-slate-400"
          > · {{ log.ip_address }}</span>
        </p>
      </div>
    </li>
    <li
      v-if="!logs.length"
      class="rounded-lg border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-500"
    >
      Chưa có nhật ký.
    </li>
  </ul>
</template>
