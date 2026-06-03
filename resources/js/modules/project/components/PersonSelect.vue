<script setup>
import SearchSelect from '@/shared/ui/SearchSelect.vue';

defineProps({
    options: { type: Array, default: () => [] },
    modelValue: { type: [Number, String, null], default: null },
    placeholder: { type: String, default: 'Tìm & chọn người…' },
    id: { type: String, default: null },
    disabled: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);
</script>

<template>
  <SearchSelect
    :id="id"
    :options="options"
    :model-value="modelValue"
    :placeholder="placeholder"
    search-placeholder="Tìm theo tên…"
    :disabled="disabled"
    show-avatar
    @update:model-value="$emit('update:modelValue', $event)"
  />
</template>
