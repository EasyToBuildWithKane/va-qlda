<script setup>
import { computed, ref } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

defineProps({
    modelValue: { type: String, default: '' },
    id: { type: String, default: undefined },
    placeholder: { type: String, default: '' },
    autocomplete: { type: String, default: 'new-password' },
    required: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'input']);

const visible = ref(false);

const inputType = computed(() => (visible.value ? 'text' : 'password'));

function onInput(e) {
    emit('update:modelValue', e.target.value);
    emit('input', e);
}
</script>

<template>
  <div class="relative">
    <input
      :id="id"
      :value="modelValue"
      :type="inputType"
      :required="required"
      :placeholder="placeholder"
      :autocomplete="autocomplete"
      class="input h-10 w-full pr-10 font-mono text-sm"
      @input="onInput"
    >
    <button
      type="button"
      class="absolute right-2 top-1/2 grid h-8 w-8 -translate-y-1/2 place-items-center rounded-md text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
      :aria-label="visible ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'"
      tabindex="-1"
      @click="visible = !visible"
    >
      <AppIcon
        :name="visible ? 'eye-off' : 'eye'"
        :size="18"
      />
    </button>
  </div>
</template>
