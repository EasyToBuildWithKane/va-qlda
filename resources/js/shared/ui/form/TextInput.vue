<script setup>
defineProps({
    modelValue: { type: [String, Number], default: '' },
    type: { type: String, default: 'text' },
    placeholder: { type: String, default: null },
    id: { type: String, default: null },
    invalid: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    autocomplete: { type: String, default: null },
});

defineEmits(['update:modelValue']);
</script>

<template>
  <input
    :id="id"
    :type="type"
    :value="modelValue"
    :placeholder="placeholder"
    :disabled="disabled"
    :autocomplete="autocomplete"
    class="input w-full"
    :class="invalid ? 'border-danger focus:border-danger focus:ring-danger/30' : ''"
    @input="$emit('update:modelValue', $event.target.value)"
  >
</template>
