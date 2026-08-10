<script setup>
import { provide } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import { useConfirmClose } from '@/composables/useConfirmClose';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: '' },
    maxWidth: { type: String, default: 'max-w-lg' },
    dirty: { type: Boolean, default: false },
    closeConfirmTitle: { type: String, default: 'Huỷ thao tác?' },
    closeConfirmMessage: { type: String, default: '' },
    /** Gọi khi đóng modal còn dirty — thường lưu localStorage qua useModalFormDraft */
    onSaveDraft: { type: Function, default: null },
});

const emit = defineEmits(['close']);

const requestClose = useConfirmClose(() => emit('close'));

const tryClose = () => requestClose(props.dirty, {
    title: props.closeConfirmTitle,
    message: props.closeConfirmMessage || undefined,
    onSaveDraft: props.onSaveDraft,
});

provide('modalClose', tryClose);

defineExpose({ tryClose });
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="opacity-0"
      leave-active-class="transition duration-100 ease-in"
      leave-to-class="opacity-0"
    >
      <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/50 p-4 py-10"
        @click.self="tryClose"
        @keydown.esc="tryClose"
      >
        <div
          class="card w-full p-6 shadow-elevation-3"
          :class="maxWidth"
        >
          <div class="mb-4 flex items-start justify-between gap-4">
            <h2 class="min-w-0 flex-1 break-words font-display text-lg font-semibold leading-snug text-slate-800">
              {{ title }}
            </h2>
            <button
              type="button"
              class="grid h-8 w-8 shrink-0 place-items-center rounded-btn text-slate-400 hover:bg-slate-100 hover:text-slate-600"
              @click="tryClose"
            >
              <AppIcon
                name="close"
                :size="18"
              />
            </button>
          </div>
          <slot />
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
