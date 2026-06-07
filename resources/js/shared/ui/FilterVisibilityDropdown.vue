<script setup>
const props = defineProps({
    show: { type: Boolean, default: false },
    controls: { type: Array, required: true },
    modelValue: { type: Object, required: true },
});

const emit = defineEmits(['update:modelValue', 'persist']);

function onToggle(key, checked) {
    emit('update:modelValue', { ...props.modelValue, [key]: checked });
    emit('persist');
}
</script>

<template>
  <Transition
    enter-active-class="transition duration-150 ease-out"
    enter-from-class="opacity-0 scale-95 -translate-y-1"
    leave-active-class="transition duration-100 ease-in"
    leave-to-class="opacity-0 scale-95 -translate-y-1"
  >
    <div
      v-if="show"
      class="absolute left-0 top-full z-30 mt-1.5 w-56 origin-top-left rounded-xl border border-slate-200 bg-white shadow-elevation-2 sm:left-auto sm:right-0 sm:origin-top-right"
    >
      <div class="border-b border-slate-100 px-4 py-2.5">
        <span class="text-xs font-bold uppercase tracking-wide text-slate-500">Hiển thị trên thanh công cụ</span>
      </div>
      <div class="px-2 py-2">
        <label
          v-for="f in controls"
          :key="f.key"
          class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-1.5 hover:bg-slate-50"
        >
          <input
            type="checkbox"
            class="rounded border-slate-300 text-brand focus:ring-brand/30"
            :checked="modelValue[f.key]"
            @change="onToggle(f.key, $event.target.checked)"
          >
          <span class="text-sm text-slate-700">{{ f.label }}</span>
        </label>
      </div>
      <div class="border-t border-slate-100 px-4 py-2.5">
        <p class="text-[11px] leading-snug text-slate-400">
          Ô tìm kiếm luôn hiển thị. Bật/tắt bộ lọc bạn muốn thấy ở dòng dưới.
        </p>
      </div>
    </div>
  </Transition>
</template>
