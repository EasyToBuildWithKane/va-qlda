<script setup>
import AppIcon from '@/Components/AppIcon.vue';

defineProps({
    modelValue: { type: String, default: '' },
    inputId: { type: String, required: true },
    placeholder: { type: String, default: 'Tìm kiếm…' },
    /** Thu gọn cho panel nhúng (tab dự án) — ô tìm ngắn, nút cùng hàng */
    compact: { type: Boolean, default: false },
    /** ~50% hàng toolbar — nút thao tác cùng hàng (tab Phản hồi dự án, …) */
    half: { type: Boolean, default: false },
    /** Chiếm phần còn lại trên hàng toolbar (danh sách coaching, …) */
    stretch: { type: Boolean, default: false },
    /** Chiều cao input (mặc định h-9; trang lọc SaaS dùng h-10) */
    inputHeight: { type: String, default: 'h-9' },
    /** Giới hạn chiều rộng ô tìm (~500–600px) khi stretch / full toolbar */
    capInputWidth: { type: Boolean, default: false },
    /** stretch + Lọc/Cột cùng hàng: ô tìm co theo phần còn lại, không basis-full */
    inlineActions: { type: Boolean, default: false },
    /** Ẩn label «Tìm kiếm» — placeholder + aria-label trên input */
    hideLabel: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'clear']);
</script>

<template>
  <div
    class="flex min-w-0 items-center gap-2"
    :class="[
      hideLabel ? 'w-full' : '',
      half
        ? 'w-full min-w-0 sm:w-1/2 sm:max-w-[50%]'
        : compact
          ? 'w-auto max-w-[min(100%,20rem)] shrink'
          : stretch
            ? inlineActions
              ? 'min-w-0 flex-1'
              : capInputWidth
                ? 'w-full min-w-0 basis-full max-w-none flex-1 sm:max-w-[36rem] sm:basis-auto'
                : 'w-full min-w-0 flex-1'
            : capInputWidth
              ? 'w-full min-w-0 flex-1 max-w-[36rem]'
              : 'w-full flex-1 basis-full lg:basis-auto lg:min-w-0',
    ]"
  >
    <label
      v-if="!hideLabel"
      :for="inputId"
      class="shrink-0 text-xs font-medium text-slate-500"
    >
      Tìm kiếm
    </label>
    <div
      class="relative min-w-0 flex-1"
      :class="half
        ? 'min-w-0 flex-1'
        : compact
          ? 'min-w-[9rem] sm:min-w-[11rem]'
          : stretch && inlineActions
            ? 'min-w-0 w-full flex-1'
            : capInputWidth || stretch
              ? 'min-w-0 w-full flex-1'
              : 'sm:min-w-[12rem] md:min-w-[16rem] lg:min-w-[28rem] xl:min-w-[32rem]'"
    >
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
        :aria-label="hideLabel ? placeholder : undefined"
        :class="['input w-full pl-9 pr-8 text-sm placeholder:text-slate-400', inputHeight]"
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
