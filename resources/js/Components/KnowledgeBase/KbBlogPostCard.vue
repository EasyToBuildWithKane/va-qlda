<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import { richContentPlainText } from '@/shared/utils/richContent';
import { date } from '@/composables/useFormat';

const props = defineProps({
    article: { type: Object, required: true },
});

const href = computed(() => `/knowledge-base/articles/${props.article.slug}`);

const excerptText = computed(() => {
    const raw = props.article.excerpt?.trim() || richContentPlainText(props.article.content);
    if (!raw) return '';
    const plain = richContentPlainText(raw);
    return plain.length > 320 ? `${plain.slice(0, 317)}…` : plain;
});
</script>

<template>
  <article class="overflow-hidden rounded-card border border-slate-200/80 bg-white shadow-sm transition hover:border-brand/25 hover:shadow-md">
    <Link
      :href="href"
      class="block"
    >
      <div class="aspect-[2/1] w-full overflow-hidden bg-slate-100 sm:aspect-[21/9]">
        <img
          v-if="article.cover_url"
          :src="article.cover_url"
          alt=""
          class="h-full w-full object-cover transition duration-300 hover:scale-[1.02]"
          loading="lazy"
        >
        <div
          v-else
          class="flex h-full w-full flex-col items-center justify-center gap-2 bg-gradient-to-br from-brand/10 via-slate-50 to-white text-brand/30"
        >
          <AppIcon
            name="knowledge"
            :size="40"
          />
          <span class="text-xs font-medium uppercase tracking-widest text-slate-400">Tri thức</span>
        </div>
      </div>
    </Link>

    <div class="space-y-3 px-5 py-5 sm:px-6 sm:py-6">
      <div class="flex flex-wrap items-center gap-2 text-xs text-slate-400">
        <span
          v-if="article.category"
          class="font-medium text-brand/90"
        >
          {{ article.category.name }}
        </span>
        <span v-if="article.published_at">· {{ date(article.published_at) }}</span>
        <span v-if="article.author?.full_name">· {{ article.author.full_name }}</span>
      </div>

      <h2 class="font-display text-xl font-semibold leading-snug text-slate-900 sm:text-2xl">
        <Link
          :href="href"
          class="hover:text-brand"
        >
          {{ article.title }}
        </Link>
      </h2>

      <p
        v-if="excerptText"
        class="text-sm leading-relaxed text-slate-600"
      >
        {{ excerptText }}
      </p>

      <div
        v-if="article.tags?.length"
        class="flex flex-wrap gap-1.5 pt-1"
      >
        <span
          v-for="t in article.tags.slice(0, 6)"
          :key="t.id"
          class="rounded-sm bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500"
        >
          {{ t.name }}
        </span>
      </div>

      <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-4 text-sm">
        <span class="text-xs text-slate-400">{{ article.view_count ?? 0 }} lượt xem</span>
        <Link
          :href="href"
          class="inline-flex items-center gap-1 font-medium text-brand hover:underline"
        >
          Đọc tiếp
          <AppIcon
            name="chevron-right"
            :size="14"
          />
        </Link>
      </div>
    </div>
  </article>
</template>
