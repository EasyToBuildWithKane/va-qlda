<script setup>
import { ref, watch, nextTick } from 'vue';
import { dialogState, onDialogConfirm, onDialogCancel } from '@/composables/useDialog';

const inputEl = ref(null);
const confirmEl = ref(null);

// Focus the input (prompt) or the confirm button when the dialog opens.
watch(() => dialogState.open, async (open) => {
    if (!open) return;
    await nextTick();
    if (dialogState.type === 'prompt') {
        inputEl.value?.focus();
        inputEl.value?.select();
    } else {
        confirmEl.value?.focus();
    }
});
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
        v-if="dialogState.open"
        class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/50 p-4"
        @click.self="onDialogCancel"
        @keydown.esc="onDialogCancel"
      >
        <div class="card w-full max-w-md p-6 shadow-elevation-3">
          <h2
            v-if="dialogState.title"
            class="font-display text-lg font-semibold text-slate-800"
          >
            {{ dialogState.title }}
          </h2>
          <p
            v-if="dialogState.message"
            class="mt-1 text-sm leading-relaxed text-slate-600"
          >
            {{ dialogState.message }}
          </p>

          <input
            v-if="dialogState.type === 'prompt'"
            ref="inputEl"
            v-model="dialogState.inputValue"
            type="text"
            class="input mt-4"
            :placeholder="dialogState.placeholder"
            @keyup.enter="onDialogConfirm"
          >

          <div class="mt-6 flex justify-end gap-2">
            <button
              v-if="dialogState.type !== 'alert'"
              type="button"
              class="btn-ghost"
              @click="onDialogCancel"
            >
              {{ dialogState.cancelText }}
            </button>
            <button
              ref="confirmEl"
              type="button"
              class="btn"
              :class="dialogState.tone === 'danger'
                ? 'bg-danger text-white hover:bg-rose-600'
                : 'btn-primary'"
              @click="onDialogConfirm"
            >
              {{ dialogState.confirmText }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
