<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import { kbCategoryGradientClass, kbCoverImageUrl } from '@/Components/KnowledgeBase/useKbCategoryGradient.js';

const props = defineProps({
    article: { type: Object, required: true },
});

const coverUrl = computed(() => kbCoverImageUrl(props.article));

const gradientClass = computed(() => {
    const seed = props.article.category?.slug
        || props.article.category?.name
        || props.article.tags?.[0]?.slug
        || props.article.slug
        || '';
    return kbCategoryGradientClass(seed);
});

const tagLabel = computed(() => props.article.tags?.[0]?.name || props.article.category?.name || 'Tri thức');
</script>

<template>
  <div class="mx-auto max-w-5xl px-4 sm:px-6">
    <div
      v-if="coverUrl"
      class="group overflow-hidden rounded-2xl ring-1 ring-slate-200/80 dark:ring-slate-700"
    >
      <img
        :src="coverUrl"
        :alt="article.title"
        class="aspect-[2/1] w-full object-cover transition duration-700 ease-out group-hover:scale-[1.01] sm:aspect-[21/9]"
        loading="lazy"
      >
    </div>
    <div
      v-else
      class="relative overflow-hidden rounded-2xl ring-1 ring-slate-200/80 dark:ring-slate-700"
    >
      <div
        class="aspect-[2/1] w-full bg-gradient-to-br sm:aspect-[21/9]"
        :class="gradientClass"
      />
      <div
        class="pointer-events-none absolute inset-0 opacity-[0.35]"
        style="background-image: radial-gradient(circle at 20% 30%, rgba(154,0,54,0.15) 0%, transparent 45%), radial-gradient(circle at 80% 70%, rgba(99,102,241,0.12) 0%, transparent 40%);"
        aria-hidden="true"
      />
      <div class="absolute inset-0 flex flex-col items-center justify-center gap-2 text-center">
        <AppIcon
          name="knowledge"
          :size="48"
          class="text-brand/40"
        />
        <span class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500/90 dark:text-slate-400">
          {{ tagLabel }}
        </span>
      </div>
    </div>
  </div>
</template>
