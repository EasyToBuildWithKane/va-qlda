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
</script>

<template>
  <ul class="relative space-y-0 border-l border-slate-200 pl-4">
    <li
      v-for="log in logs"
      :key="log.id"
      class="relative pb-6 last:pb-0"
    >
      <span class="absolute -left-[1.35rem] top-1 flex h-6 w-6 items-center justify-center rounded-full bg-white ring-2 ring-slate-200">
        <AppIcon
          :name="actionIcon[log.action?.value] || 'info'"
          :size="13"
        />
      </span>
      <p class="text-sm font-medium text-slate-800">
        {{ log.action?.label || log.action?.value }}
        <span
          v-if="log.account"
          class="font-normal text-slate-600"
        > · {{ log.account.display_name }}</span>
      </p>
      <p class="text-xs text-slate-500">
        {{ datetime(log.created_at) }}
      </p>
    </li>
    <li
      v-if="!logs.length"
      class="text-sm text-slate-500"
    >
      Chưa có nhật ký.
    </li>
  </ul>
</template>
