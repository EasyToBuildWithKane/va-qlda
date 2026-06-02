<script setup>
defineProps({
    modelValue: { type: [String, Number, null], default: null },
    options: { type: Array, default: () => [] },
    placeholder: { type: String, default: 'Chọn…' },
    id: { type: String, default: null },
    invalid: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    valueKey: { type: String, default: 'value' },
    labelKey: { type: String, default: 'label' },
});

defineEmits(['update:modelValue']);
</script>

<template>
  <select
    :id="id"
    :value="modelValue"
    :disabled="disabled"
    class="input w-full"
    :class="invalid ? 'border-danger focus:border-danger focus:ring-danger/30' : ''"
    @change="$emit('update:modelValue', $event.target.value || null)"
  >
    <option value="">
      {{ placeholder }}
    </option>
    <option
      v-for="opt in options"
      :key="opt[valueKey]"
      :value="opt[valueKey]"
    >
      {{ opt[labelKey] }}
    </option>
  </select>
</template>
