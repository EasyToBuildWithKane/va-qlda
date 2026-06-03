<script setup>
defineProps({
    banner: { type: Object, default: null },
});

const formatItems = (items) =>
    (items ?? [])
        .map((i) => `${i.tool_name} (${i.group})`)
        .join(' · ');
</script>

<template>
  <div
    v-if="banner && banner.total > 0"
    class="mx-5 mb-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
    role="status"
  >
    <span class="font-medium">Cần chú ý:</span>
    <template v-if="banner.expiring_soon_count > 0 && banner.expired_count > 0">
      {{ banner.expiring_soon_count }} tài khoản sắp hết hạn,
      {{ banner.expired_count }} đã hết hạn:
    </template>
    <template v-else-if="banner.expired_count > 0">
      {{ banner.expired_count }} tài khoản đã hết hạn:
    </template>
    <template v-else>
      {{ banner.expiring_soon_count }} tài khoản sắp hết hạn:
    </template>
    {{ formatItems(banner.items) }}
  </div>
</template>
