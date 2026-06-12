<script setup>
defineProps({
    modelValue: { type: String, default: '' },
    error: { type: String, default: '' },
    /** single | bulk-compose */
    variant: { type: String, default: 'single' },
    step: { type: [Number, String], default: null },
    disabled: { type: Boolean, default: false },
    maxLength: { type: Number, default: 255 },
});

defineEmits(['update:modelValue']);

const placeholders = {
    single: 'VD: API đăng nhập trả lỗi 500 khi tải cao…',
    'bulk-compose': 'Mỗi dòng một vướng mắc — dán từ Excel được',
};
</script>

<template>
  <section
    class="overflow-hidden rounded-xl border border-brand/20 bg-gradient-to-b from-brand-50/40 via-white to-white shadow-sm ring-1 ring-brand/5"
  >
    <div class="border-b border-brand/10 bg-brand/5 px-4 py-2.5">
      <div class="flex flex-wrap items-center gap-2">
        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-white text-brand shadow-sm ring-1 ring-brand/15">
          <span
            v-if="step != null && step !== ''"
            class="font-display text-xs font-bold"
          >{{ step }}</span>
          <slot
            v-else
            name="icon"
          >
            <span class="font-display text-sm font-bold">!</span>
          </slot>
        </span>
        <div class="min-w-0 flex-1">
          <h3 class="text-sm font-semibold text-slate-900">
            {{ variant === 'bulk-compose' ? 'Danh sách đề vướng mắc' : 'Đề vướng mắc' }}
            <span class="text-rose-600">*</span>
          </h3>
          <p class="text-xs text-slate-600">
            {{
              variant === 'bulk-compose'
                ? 'Một dòng = một vướng mắc · tiêu đề ngắn, dễ nhận biết trên bảng.'
                : 'Một câu tóm tắt — người xử lý hiểu ngay khi nhìn danh sách.'
            }}
          </p>
        </div>
      </div>
    </div>

    <div class="p-4">
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
        class="input text-base font-semibold text-slate-900 placeholder:font-normal placeholder:text-slate-400"
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
