<script setup>
/**
 * Modal tạo nhanh (tùy chọn) — luồng chính dùng trang Create.
 */
defineProps({
    show: { type: Boolean, default: false },
});
defineEmits(['close']);
</script>

<template>
  <div
    v-if="show"
    class="hidden"
    aria-hidden="true"
  />
</template>
