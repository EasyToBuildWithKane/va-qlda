<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    section: { type: Object, required: true },
    modelValue: { type: String, default: '' },
    editing: { type: Boolean, default: false },
    canEdit: { type: Boolean, default: false },
    showSaveCancel: { type: Boolean, default: false },
    dirty: { type: Boolean, default: false },
    processing: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'edit', 'save', 'cancel']);

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
        <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
          <AppIcon
            :name="section.icon"
            :size="15"
          />
        </span>
        <h3 class="font-display text-sm font-semibold uppercase tracking-wide text-slate-700 dark:text-slate-200">
          {{ section.label }}
        </h3>
      </div>
      <div
        v-if="canEdit"
        class="flex items-center gap-1"
      >
        <template v-if="editing && showSaveCancel">
          <span
            v-if="dirty"
            class="mr-1 hidden text-[10px] text-amber-600 sm:inline"
          >Chưa lưu</span>
          <button
            type="button"
            class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 dark:hover:bg-slate-800"
            title="Hủy chỉnh sửa"
            @click="emit('cancel')"
          >
            <AppIcon
              name="close"
              :size="15"
            />
          </button>
          <button
            type="button"
            :disabled="processing || !dirty"
            class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-brand transition hover:bg-brand/10 disabled:opacity-40"
            title="Lưu thay đổi"
            @click="emit('save')"
          >
            <AppIcon
              name="save"
              :size="15"
            />
          </button>
        </template>
        <button
          v-else-if="!editing"
          type="button"
          class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-brand dark:hover:bg-slate-800"
          title="Chỉnh sửa nội dung"
          @click="emit('edit')"
        >
          <AppIcon
            name="edit"
            :size="15"
          />
        </button>
      </div>
    </header>

    <textarea
      v-if="editing"
      :value="modelValue"
      rows="6"
      class="w-full flex-1 resize-y rounded-lg border border-slate-300 bg-slate-50 p-2.5 text-sm text-slate-700 focus:border-brand focus:ring-1 focus:ring-brand dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200"
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
        <span class="mt-1.5 inline-block h-1.5 w-1.5 shrink-0 rounded-full bg-slate-400" />
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
