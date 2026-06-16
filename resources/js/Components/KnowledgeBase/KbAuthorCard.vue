<script setup>
import { computed } from 'vue';
import Avatar from '@/shared/ui/Avatar.vue';

const props = defineProps({
    author: { type: Object, default: null },
    articleCount: { type: Number, default: null },
});

const displayName = computed(() => {
    if (!props.author) return '';
    return (props.author.full_name || props.author.name || '').trim();
});
</script>

<template>
  <section
    v-if="author && displayName"
    class="mx-auto max-w-[760px] rounded-2xl border border-slate-200/80 bg-gradient-to-br from-slate-50/90 to-white p-6 shadow-sm dark:border-slate-700 dark:from-slate-900/80 dark:to-slate-950"
    aria-label="Thông tin tác giả"
  >
    <p class="mb-4 text-[10px] font-semibold uppercase tracking-[0.14em] text-brand/80">
      Tác giả
    </p>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
      <Avatar
        :name="displayName"
        :src="author.avatar_path"
        :size="64"
      />
      <div class="min-w-0 flex-1">
        <h3 class="font-display text-lg font-semibold text-slate-900 dark:text-slate-50">
          {{ displayName }}
        </h3>
        <p
          v-if="author.role_title"
          class="mt-0.5 text-sm text-slate-500 dark:text-slate-400"
        >
          {{ author.role_title }}
        </p>
        <p
          v-if="articleCount != null"
          class="mt-2 text-xs text-slate-500 dark:text-slate-400"
        >
          {{ articleCount }} bài viết trên Knowledge Base
        </p>
      </div>
    </div>
  </section>
</template>
