<script setup>
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';

defineProps({
    hint: { type: Object, default: null },
});

const emit = defineEmits(['dismiss']);
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-300 ease-out"
      enter-from-class="opacity-0 translate-y-3"
      leave-active-class="transition duration-200 ease-in"
      leave-to-class="opacity-0 translate-y-3"
    >
      <div
        v-if="hint"
        class="fixed bottom-5 right-5 z-[55] w-[min(90vw,20rem)] rounded-xl border border-slate-200 bg-white p-4 shadow-elevation-3"
        role="status"
      >
        <button
          type="button"
          class="absolute right-2 top-2 grid h-7 w-7 place-items-center rounded-md text-slate-300 hover:bg-slate-100 hover:text-slate-500"
          aria-label="Đóng gợi ý"
          @click="emit('dismiss')"
        >
          <AppIcon
            name="close"
            :size="15"
          />
        </button>
        <div class="flex items-start gap-3 pr-5">
          <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand/10 text-brand">
            <AppIcon
              :name="hint.icon"
              :size="18"
            />
          </span>
          <div class="min-w-0">
            <p class="text-sm font-semibold text-slate-700">
              {{ hint.title }}
            </p>
            <p class="mt-0.5 text-xs leading-relaxed text-slate-500">
              {{ hint.desc }}
            </p>
          </div>
        </div>
        <Link
          :href="hint.action.href"
          class="btn-primary mt-3 inline-flex w-full items-center justify-center gap-1.5 !py-2 text-[13px]"
          @click="emit('dismiss')"
        >
          {{ hint.action.label }}
          <AppIcon
            name="chevron-right"
            :size="14"
          />
        </Link>
      </div>
    </Transition>
  </Teleport>
</template>
