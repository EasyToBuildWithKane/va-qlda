<script setup>
import { computed } from 'vue';

const props = defineProps({
    cards: { type: Object, default: null },
    scheduleTimes: { type: Array, default: () => ['08:00', '14:00'] },
});

const emit = defineEmits(['show-attention']);

const needsAttention = computed(() => {
    const c = props.cards;
    if (!c) return 0;
    return (c.expiring_soon ?? 0) + (c.expired ?? 0);
});

const scheduleLabel = computed(() =>
    (props.scheduleTimes ?? []).join(' và '),
);
</script>

<template>
  <div
    v-if="needsAttention > 0"
    class="mx-4 mb-0 flex flex-col gap-2 rounded-xl border border-amber-200/80 bg-gradient-to-r from-amber-50 to-orange-50/50 px-4 py-3 sm:mx-5 sm:flex-row sm:items-center sm:justify-between"
    role="status"
  >
    <div class="min-w-0 text-sm text-amber-950">
      <span class="font-semibold">{{ needsAttention }} tài khoản</span>
      cần gia hạn — hàng
      <span class="font-medium text-amber-800">nền vàng/cam</span>
      trong bảng.
      <span
        v-if="scheduleLabel"
        class="mt-1 block text-xs text-amber-800/90"
      >
        Email nhắc tự động lúc {{ scheduleLabel }} (mỗi ngày, đến khi gia hạn).
      </span>
    </div>
    <button
      type="button"
      class="btn-secondary shrink-0 border-amber-300/80 bg-white/80 text-xs text-amber-900 hover:bg-white"
      @click="emit('show-attention')"
    >
      Chỉ hiện cần chú ý
    </button>
  </div>
</template>
