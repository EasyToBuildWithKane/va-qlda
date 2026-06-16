<script setup>
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import KbBlogPanel from '@/Components/KnowledgeBase/KbBlogPanel.vue';
import KbBlogTagSection from '@/Components/KnowledgeBase/KbBlogTagSection.vue';

defineProps({
    sidebar: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['filter-tag']);
</script>

<template>
  <div class="kb-blog-aside flex flex-col gap-3 sm:gap-3.5 xl:sticky xl:top-5">
    <KbBlogPanel
      v-if="sidebar.popularPosts?.length"
      aria-label="Bài xem nhiều"
      title="Xem nhiều"
      flush-body
    >
      <ul class="divide-y divide-slate-100/90">
        <li
          v-for="(post, idx) in sidebar.popularPosts"
          :key="post.id"
        >
          <Link
            :href="`/knowledge-base/articles/${post.slug}`"
            class="group flex gap-2 px-3.5 py-2.5 transition hover:bg-slate-50 sm:px-4"
          >
            <span
              class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-slate-100 font-display text-xs font-semibold tabular-nums text-slate-400 group-hover:bg-brand/[0.08] group-hover:text-brand"
              aria-hidden="true"
            >
              {{ idx + 1 }}
            </span>
            <div
              class="h-11 w-11 shrink-0 overflow-hidden rounded-lg bg-slate-100 ring-1 ring-slate-200/60"
            >
              <img
                v-if="post.cover_url"
                :src="post.cover_url"
                alt=""
                class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                loading="lazy"
              >
              <div
                v-else
                class="flex h-full w-full items-center justify-center bg-gradient-to-br from-brand/10 to-slate-50 text-brand/35"
              >
                <AppIcon
                  name="knowledge"
                  :size="18"
                />
              </div>
            </div>
            <div class="min-w-0 flex-1 py-0.5">
              <p class="line-clamp-2 text-xs font-medium leading-snug text-slate-700 group-hover:text-brand">
                {{ post.title }}
              </p>
              <p class="mt-0.5 text-[10px] tabular-nums text-slate-400">
                {{ post.view_count }} lượt xem
              </p>
            </div>
          </Link>
        </li>
      </ul>
    </KbBlogPanel>

    <KbBlogTagSection
      v-if="sidebar.tags?.length"
      :tags="sidebar.tags"
      :active-slug="filters.tag"
      layout="cloud"
      @filter-tag="(slug) => emit('filter-tag', slug)"
    />
  </div>
</template>
