<script setup>
import MiniCalendar from './MiniCalendar.vue';

defineProps({
    selectedDate: { type: String, default: '' },
});

const emit = defineEmits(['select-date']);
</script>

<template>
  <div class="flex h-full flex-col overflow-y-auto p-4">
    <MiniCalendar
      :selected="selectedDate"
      @select="(d) => emit('select-date', d)"
    />
  </div>
</template>
