<script setup>
defineProps({
    modelValue: { type: String, default: '' },
    error: { type: String, default: '' },
    /** single | bulk-compose */
    variant: { type: String, default: 'single' },
    step: { type: [Number, String], default: null },
    disabled: { type: Boolean, default: false },
    maxLength: { type: Number, default: 255 },
    /** Chỉ label + input, không bọc card */
    compact: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);

const placeholders = {
    single: 'VD: API đăng nhập trả lỗi 500 khi tải cao…',
    'bulk-compose': 'Mỗi dòng một vướng mắc — dán từ Excel được',
};
</script>

<template>
  <div
    v-if="compact"
    class="space-y-1"
  >
    <label class="label flex items-center justify-between gap-2">
      <span>
        Đề vướng mắc
        <span class="text-rose-600">*</span>
      </span>
      <span class="tabular-nums text-[11px] font-normal text-slate-400">
        {{ (modelValue || '').length }} / {{ maxLength }}
      </span>
    </label>
    <input
      :value="modelValue"
      type="text"
      class="input text-sm font-medium text-slate-900 placeholder:font-normal placeholder:text-slate-400"
      :placeholder="placeholders.single"
      :maxlength="maxLength"
      :disabled="disabled"
      @input="$emit('update:modelValue', $event.target.value)"
    >
    <p
      v-if="error"
      class="text-xs text-danger"
    >
      {{ error }}
    </p>
  </div>
  <section
    v-else
    class="overflow-hidden rounded-lg border border-slate-200 bg-white"
  >
    <header class="flex items-start gap-3 border-b border-slate-100 bg-slate-50/90 px-3 py-2.5">
      <span
        v-if="step != null && step !== ''"
        class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-brand font-display text-xs font-bold text-white shadow-sm"
        aria-hidden="true"
      >
        {{ step }}
      </span>
      <div class="min-w-0 flex-1">
        <h3 class="text-sm font-semibold text-slate-900">
          {{ variant === 'bulk-compose' ? 'Danh sách đề vướng mắc' : 'Đề vướng mắc' }}
          <span class="text-rose-600">*</span>
        </h3>
      </div>
    </header>

    <div class="p-3">
      <textarea
        v-if="variant === 'bulk-compose'"
        :value="modelValue"
        rows="9"
        class="input min-h-[11rem] resize-y font-mono text-sm leading-relaxed"
        :placeholder="placeholders['bulk-compose']"
        :disabled="disabled"
        @input="$emit('update:modelValue', $event.target.value)"
      />
      <input
        v-else
        :value="modelValue"
        type="text"
        class="input text-sm font-semibold text-slate-900 placeholder:font-normal placeholder:text-slate-400"
        :placeholder="placeholders.single"
        :maxlength="maxLength"
        :disabled="disabled"
        @input="$emit('update:modelValue', $event.target.value)"
      >

      <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-xs">
        <p
          v-if="error"
          class="text-danger"
        >
          {{ error }}
        </p>
        <span v-else />
        <span
          v-if="variant === 'single'"
          class="tabular-nums text-slate-400"
        >
          {{ (modelValue || '').length }} / {{ maxLength }}
        </span>
        <slot name="footer" />
      </div>
    </div>
  </section>
</template>
