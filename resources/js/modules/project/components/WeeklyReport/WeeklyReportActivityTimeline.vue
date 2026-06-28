<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    content: { type: String, default: '' },
});

const items = computed(() =>
    (props.content ?? '')
        .split('\n')
        .map((l) => l.replace(/^•\s*/, '').trim())
        .filter(Boolean),
);
</script>

<template>
  <section class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
    <header class="mb-3 flex items-center gap-2">
      <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-sky-100 text-sky-600 dark:bg-sky-950/60 dark:text-sky-300">
        <AppIcon
          name="report-history"
          :size="15"
        />
      </span>
      <h3 class="font-display text-sm font-semibold uppercase tracking-wide text-slate-700 dark:text-slate-200">
        Sự kiện nổi bật
      </h3>
    </header>

    <ol
      v-if="items.length"
      class="relative space-y-3 border-l border-slate-200 pl-4 dark:border-slate-700"
    >
      <li
        v-for="(it, i) in items"
        :key="i"
        class="relative"
      >
        <span class="absolute -left-[21px] top-1 inline-block h-2.5 w-2.5 rounded-full border-2 border-white bg-brand dark:border-slate-900" />
        <p class="text-sm text-slate-600 dark:text-slate-300">
          {{ it }}
        </p>
      </li>
    </ol>
    <p
      v-else
      class="text-sm italic text-slate-400"
    >
      Không có sự kiện nổi bật được ghi nhận.
    </p>
  </section>
</template>
