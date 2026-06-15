<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    sidebar: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['filter-tag']);

const tagSizeClass = (count, max) => {
    if (max <= 0) return 'text-[11px]';
    const ratio = count / max;
    if (ratio >= 0.75) return 'text-sm font-semibold';
    if (ratio >= 0.4) return 'text-xs font-medium';
    return 'text-[11px]';
};

const maxTagCount = computed(() => {
    const counts = (props.sidebar.tags ?? []).map((t) => t.articles_count ?? 0);
    return counts.length ? Math.max(...counts) : 0;
});
</script>

<template>
  <div class="space-y-4">
    <section
      v-if="sidebar.popularPosts?.length"
      class="rounded-card border border-slate-200/80 bg-white p-4 shadow-sm"
      aria-label="Bài xem nhiều"
    >
      <p class="mb-3 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-400">
        Xem nhiều
      </p>
      <ul class="space-y-3">
        <li
          v-for="post in sidebar.popularPosts"
          :key="post.id"
        >
          <Link
            :href="`/knowledge-base/articles/${post.slug}`"
            class="group flex gap-2.5"
          >
            <div
              class="h-14 w-14 shrink-0 overflow-hidden rounded-md bg-slate-100 ring-1 ring-slate-200/80"
            >
              <img
                v-if="post.cover_url"
                :src="post.cover_url"
                alt=""
                class="h-full w-full object-cover"
                loading="lazy"
              >
              <div
                v-else
                class="flex h-full w-full items-center justify-center bg-gradient-to-br from-brand/10 to-slate-50 text-brand/35"
              >
                <AppIcon
                  name="knowledge"
                  :size="20"
                />
              </div>
            </div>
            <div class="min-w-0 flex-1">
              <p class="line-clamp-2 text-xs font-medium leading-snug text-slate-700 group-hover:text-brand">
                {{ post.title }}
              </p>
              <p class="mt-1 text-[10px] text-slate-400">
                {{ post.view_count }} lượt xem
              </p>
            </div>
          </Link>
        </li>
      </ul>
    </section>

    <section
      v-if="sidebar.tags?.length"
      class="rounded-card border border-slate-200/80 bg-white p-4 shadow-sm"
      aria-label="Đám mây thẻ"
    >
      <p class="mb-3 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-400">
        Thẻ
      </p>
      <div class="flex flex-wrap gap-x-2 gap-y-2">
        <button
          v-for="tag in sidebar.tags"
          :key="tag.id"
          type="button"
          class="text-slate-600 transition hover:text-brand"
          :class="[
            tagSizeClass(tag.articles_count, maxTagCount),
            filters.tag === tag.slug ? 'text-brand underline decoration-brand/40' : '',
          ]"
          @click="emit('filter-tag', tag.slug)"
        >
          {{ tag.name }}
        </button>
      </div>
    </section>
  </div>
</template>
