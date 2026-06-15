<script setup>
import { ref } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    title: { type: String, required: true },
    hint: { type: String, default: '' },
    defaultOpen: { type: Boolean, default: false },
    badge: { type: String, default: '' },
    step: { type: [Number, String], default: null },
    optional: { type: Boolean, default: false },
});

const open = ref(props.defaultOpen);
</script>

<template>
  <section class="overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-sm">
    <button
      type="button"
      class="flex w-full items-start gap-3 px-4 py-3.5 text-left transition-colors hover:bg-slate-50/80 sm:px-5"
      :aria-expanded="open"
      @click="open = !open"
    >
      <span
        v-if="step != null && step !== ''"
        class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-brand/10 font-display text-sm font-bold text-brand"
        aria-hidden="true"
      >
        {{ step }}
      </span>
      <AppIcon
        v-else
        :name="open ? 'chevron-down' : 'chevron-right'"
        :size="16"
        class="mt-1 shrink-0 text-slate-400"
      />
      <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2">
          <h3 class="text-sm font-semibold text-slate-900">
            {{ title }}
            <span
              v-if="optional"
              class="ml-1 text-xs font-normal text-slate-400"
            >(không bắt buộc)</span>
          </h3>
          <span
            v-if="badge && !open"
            class="rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600"
          >
            {{ badge }}
          </span>
        </div>
        <p
          v-if="hint"
          class="mt-0.5 text-xs leading-relaxed text-slate-500"
        >
          {{ hint }}
        </p>
      </div>
      <AppIcon
        v-if="step != null && step !== ''"
        :name="open ? 'chevron-down' : 'chevron-right'"
        :size="16"
        class="mt-1 shrink-0 text-slate-400"
      />
      <slot name="header-trailing" />
    </button>
    <div
      v-show="open"
      class="border-t border-slate-100 px-4 py-4 sm:px-5"
    >
      <slot />
    </div>
  </section>
</template>
