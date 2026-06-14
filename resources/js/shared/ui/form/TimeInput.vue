<script setup>
defineProps({
    modelValue: { type: String, default: '' },
    id: { type: String, default: null },
    invalid: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    step: { type: [Number, String], default: 60 },
});

defineEmits(['update:modelValue']);
</script>

<template>
  <input
    :id="id"
    type="time"
    :value="modelValue"
    :disabled="disabled"
    :step="step"
    class="input input--picker w-full tabular-nums"
    :class="invalid ? 'border-danger focus:border-danger focus:ring-danger/30' : ''"
    @input="$emit('update:modelValue', $event.target.value || '')"
  >
</template>
