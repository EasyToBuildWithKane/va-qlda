<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    executiveSummary: { type: String, default: '' },
    aiSummary: { type: String, default: '' },
    modelValue: { type: String, default: '' },
    editing: { type: Boolean, default: false },
    canEdit: { type: Boolean, default: false },
    engine: { type: String, default: 'heuristic' },
});

const emit = defineEmits(['update:modelValue', 'edit']);

const engineLabel = computed(() => {
    if (props.engine === 'llm') return 'Viết bởi AI từ dữ liệu Sprint';
    if (props.engine === 'heuristic_fallback') return 'AI lỗi — dùng tổng hợp nội bộ';
    return 'Tổng hợp từ dữ liệu Sprint';
});

const displayExecutive = computed(() => (props.editing ? props.modelValue : props.executiveSummary));
</script>

<template>
  <section
    class="overflow-hidden rounded-xl border border-brand/15 bg-white shadow-sm dark:border-brand/30 dark:bg-slate-900"
    aria-label="Tóm tắt điều hành"
  >
    <div class="border-b border-brand/10 bg-gradient-to-r from-brand/[0.07] via-violet-500/[0.04] to-transparent px-4 py-3 dark:border-slate-800">
      <div class="flex items-start justify-between gap-3">
        <div class="flex min-w-0 items-center gap-2.5">
          <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-brand/10 text-brand">
            <AppIcon
              name="sparkles"
              :size="16"
            />
          </span>
          <div class="min-w-0">
            <h3 class="font-display text-sm font-semibold text-slate-800 dark:text-slate-100">
              Tóm tắt điều hành
            </h3>
            <p class="text-[11px] text-slate-500">
              {{ engineLabel }}
            </p>
          </div>
        </div>
        <button
          v-if="canEdit && !editing"
          type="button"
          class="inline-flex h-8 items-center gap-1 rounded-lg px-2 text-xs font-medium text-slate-500 transition hover:bg-white hover:text-slate-700 dark:hover:bg-slate-800"
          @click="emit('edit')"
        >
          <AppIcon
            name="edit"
            :size="13"
          />
          Sửa
        </button>
      </div>
    </div>

    <div class="space-y-3 p-4">
      <textarea
        v-if="editing"
        :value="modelValue"
        rows="4"
        class="input min-h-[6rem] w-full resize-y text-sm leading-relaxed"
        aria-label="Tóm tắt điều hành"
        @input="emit('update:modelValue', $event.target.value)"
      />
      <p
        v-else
        class="text-sm leading-relaxed text-slate-700 dark:text-slate-200"
      >
        {{ displayExecutive || 'Chưa có tóm tắt điều hành.' }}
      </p>

      <div
        v-if="aiSummary"
        class="rounded-lg border border-violet-200/80 bg-violet-50/70 px-3 py-2.5 dark:border-violet-900/60 dark:bg-violet-950/40"
      >
        <p class="mb-1 text-[10px] font-semibold uppercase tracking-[0.12em] text-violet-600 dark:text-violet-300">
          Nhận định
        </p>
        <p class="text-sm leading-relaxed text-slate-700 dark:text-slate-200">
          {{ aiSummary }}
        </p>
      </div>
    </div>
  </section>
</template>
