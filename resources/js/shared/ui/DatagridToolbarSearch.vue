<script setup>
import AppIcon from '@/Components/AppIcon.vue';

defineProps({
    modelValue: { type: String, default: '' },
    inputId: { type: String, required: true },
    placeholder: { type: String, default: 'Tìm kiếm…' },
});

const emit = defineEmits(['update:modelValue', 'clear']);
</script>

<template>
  <div class="flex min-w-0 flex-1 items-center gap-2">
    <label
      :for="inputId"
      class="shrink-0 text-xs font-medium text-slate-500"
    >
      Tìm kiếm
    </label>
    <div class="relative min-w-0 flex-1 sm:min-w-[200px] lg:min-w-[28rem] xl:min-w-[32rem]">
      <AppIcon
        name="search"
        :size="15"
        class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"
      />
      <input
        :id="inputId"
        :value="modelValue"
        type="search"
        :placeholder="placeholder"
        class="input h-9 w-full pl-9 pr-8 text-sm placeholder:text-slate-400"
        autocomplete="off"
        @input="emit('update:modelValue', $event.target.value)"
      >
      <button
        v-if="modelValue"
        type="button"
        class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
        title="Xoá từ khoá"
        @click="emit('update:modelValue', ''); emit('clear')"
      >
        <AppIcon
          name="close"
          :size="14"
        />
      </button>
    </div>
  </div>
</template>
