<script setup>
import AppIcon from '@/Components/AppIcon.vue';

defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: '' },
    width: { type: String, default: 'max-w-md' },
    /** Child controls layout + scroll (no default body padding). */
    flush: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      leave-active-class="transition duration-150 ease-in"
      leave-to-class="opacity-0"
    >
      <div
        v-if="show"
        class="fixed inset-0 z-50 bg-slate-900/40"
        @click.self="emit('close')"
        @keydown.esc="emit('close')"
      >
        <Transition
          enter-active-class="transition duration-200 ease-out"
          enter-from-class="translate-x-full"
          leave-active-class="transition duration-150 ease-in"
          leave-to-class="translate-x-full"
        >
          <aside
            v-if="show"
            class="absolute right-0 top-0 flex h-full w-full flex-col bg-white shadow-elevation-3 dark:bg-slate-900 dark:border-l dark:border-slate-800"
            :class="width"
            role="dialog"
            aria-modal="true"
          >
            <header
              v-if="title"
              class="flex items-center justify-between gap-4 border-b border-slate-100 px-5 py-4 dark:border-slate-800"
            >
              <h2 class="font-display text-base font-semibold text-slate-800 dark:text-slate-100">
                {{ title }}
              </h2>
              <button
                type="button"
                class="grid h-8 w-8 place-items-center rounded-btn text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                aria-label="Đóng"
                @click="emit('close')"
              >
                <AppIcon
                  name="close"
                  :size="18"
                />
              </button>
            </header>
            <div
              class="flex min-h-0 flex-1 flex-col"
              :class="flush ? 'overflow-hidden' : 'overflow-y-auto px-5 py-4'"
            >
              <slot />
            </div>
            <footer
              v-if="$slots.footer"
              class="border-t border-slate-100 px-5 py-3"
            >
              <slot name="footer" />
            </footer>
          </aside>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
