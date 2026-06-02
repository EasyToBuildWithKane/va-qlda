<script setup>
defineProps({
    modelValue: { type: String, default: null },
    id: { type: String, default: null },
    invalid: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    min: { type: String, default: null },
    max: { type: String, default: null },
});

defineEmits(['update:modelValue']);
</script>

<template>
  <input
    :id="id"
    type="date"
    :value="modelValue"
    :disabled="disabled"
    :min="min"
    :max="max"
    class="input w-full"
    :class="invalid ? 'border-danger focus:border-danger focus:ring-danger/30' : ''"
    @input="$emit('update:modelValue', $event.target.value || null)"
  >
</template>
