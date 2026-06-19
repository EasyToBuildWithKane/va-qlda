<script setup>
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';

defineProps({
    category: { type: Object, default: null },
    title: { type: String, default: '' },
});
</script>

<template>
  <nav
    class="mb-4 text-sm text-slate-500 dark:text-slate-400"
    aria-label="Đường dẫn"
  >
    <ol class="flex flex-wrap items-center gap-1.5">
      <li>
        <Link
          href="/knowledge-base/blog"
          class="inline-flex items-center gap-1 transition hover:text-brand"
        >
          <AppIcon
            name="knowledge"
            :size="14"
            class="text-slate-400"
          />
          Tri thức
        </Link>
      </li>
      <li
        class="text-slate-300 dark:text-slate-600"
        aria-hidden="true"
      >
        /
      </li>
      <li v-if="category">
        <Link
          :href="`/knowledge-base/blog?category_id=${category.id}`"
          class="transition hover:text-brand"
        >
          {{ category.name }}
        </Link>
      </li>
      <template v-if="category && title">
        <li
          class="text-slate-300 dark:text-slate-600"
          aria-hidden="true"
        >
          /
        </li>
      </template>
      <li
        v-if="title"
        class="min-w-0 max-w-full truncate font-medium text-slate-700 dark:text-slate-300"
        aria-current="page"
      >
        {{ title }}
      </li>
    </ol>
  </nav>
</template>
