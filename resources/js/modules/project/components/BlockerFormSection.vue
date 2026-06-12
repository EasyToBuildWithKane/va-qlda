<script setup>
defineProps({
    step: { type: [Number, String], default: null },
    title: { type: String, required: true },
    hint: { type: String, default: '' },
    optional: { type: Boolean, default: false },
    dense: { type: Boolean, default: false },
    /** Không viền/header nặng — tiêu đề nhỏ phía trên nội dung */
    plain: { type: Boolean, default: false },
});
</script>

<template>
  <section
    v-if="plain"
    class="flex h-full flex-col space-y-2.5"
  >
    <div
      v-if="title"
      class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5"
    >
      <h3 class="text-xs font-semibold text-slate-800">
        {{ title }}
        <span
          v-if="optional"
          class="ml-1 font-normal text-slate-400"
        >(tuỳ chọn)</span>
      </h3>
      <p
        v-if="hint"
        class="text-[11px] text-slate-500"
      >
        {{ hint }}
      </p>
    </div>
    <div class="flex flex-1 flex-col">
      <slot />
    </div>
  </section>
  <section
    v-else
    class="flex h-full flex-col overflow-hidden rounded-lg border border-slate-200 bg-white"
  >
    <header
      class="flex items-start gap-3 border-b border-slate-100 bg-slate-50/90"
      :class="dense ? 'px-3 py-2.5' : 'px-4 py-3'"
    >
      <span
        v-if="step != null && step !== ''"
        class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-brand font-display text-xs font-bold text-white shadow-sm"
        aria-hidden="true"
      >
        {{ step }}
      </span>
      <div class="min-w-0 flex-1">
        <h3 class="text-sm font-semibold text-slate-900">
          {{ title }}
          <span
            v-if="optional"
            class="ml-1.5 text-xs font-normal text-slate-400"
          >(tuỳ chọn)</span>
        </h3>
        <p
          v-if="hint"
          class="mt-0.5 text-xs leading-relaxed text-slate-500"
        >
          {{ hint }}
        </p>
      </div>
      <slot name="header-action" />
    </header>
    <div
      class="flex flex-1 flex-col"
      :class="dense ? 'p-3' : 'p-4'"
    >
      <slot />
    </div>
  </section>
</template>
