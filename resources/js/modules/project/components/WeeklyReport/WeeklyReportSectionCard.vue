<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    section: { type: Object, required: true },
    modelValue: { type: String, default: '' },
    editing: { type: Boolean, default: false },
    accent: { type: String, default: 'brand' },
});

const emit = defineEmits(['update:modelValue']);

const accentClasses = {
    brand: 'text-brand bg-brand/10',
    emerald: 'text-emerald-600 bg-emerald-100 dark:bg-emerald-950/60 dark:text-emerald-300',
    sky: 'text-sky-600 bg-sky-100 dark:bg-sky-950/60 dark:text-sky-300',
};

const lines = computed(() =>
    (props.section.content ?? '')
        .split('\n')
        .map((l) => l.replace(/^•\s*/, '').trim())
        .filter(Boolean),
);
</script>

<template>
  <section class="flex flex-col rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
    <header class="mb-3 flex items-center justify-between">
      <div class="flex items-center gap-2">
        <span
          class="inline-flex h-7 w-7 items-center justify-center rounded-lg"
          :class="accentClasses[accent]"
        >
          <AppIcon
            :name="section.icon"
            :size="15"
          />
        </span>
        <h3 class="font-display text-sm font-semibold uppercase tracking-wide text-slate-700 dark:text-slate-200">
          {{ section.label }}
        </h3>
      </div>
      <span
        v-if="section.is_edited && !editing"
        class="inline-flex items-center gap-1 rounded-full bg-violet-100 px-2 py-0.5 text-[10px] font-medium text-violet-700 dark:bg-violet-950 dark:text-violet-300"
      >
        <AppIcon
          name="edit"
          :size="10"
        /> Đã sửa
      </span>
    </header>

    <textarea
      v-if="editing"
      :value="modelValue"
      rows="6"
      class="w-full flex-1 resize-y rounded-lg border border-slate-300 bg-slate-50 p-2.5 text-sm text-slate-700 focus:border-brand focus:ring-1 focus:ring-brand dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200"
      placeholder="Mỗi dòng một ý, bắt đầu bằng • ..."
      @input="emit('update:modelValue', $event.target.value)"
    />

    <ul
      v-else-if="lines.length"
      class="space-y-1.5"
    >
      <li
        v-for="(line, i) in lines"
        :key="i"
        class="flex gap-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300"
      >
        <span class="mt-1.5 inline-block h-1.5 w-1.5 shrink-0 rounded-full bg-brand/60" />
        <span>{{ line }}</span>
      </li>
    </ul>

    <p
      v-else
      class="text-sm italic text-slate-400"
    >
      Chưa có nội dung.
    </p>
  </section>
</template>
