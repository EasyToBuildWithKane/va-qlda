<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import { richContentPlainText } from '@/shared/utils/richContent';
import { date } from '@/composables/useFormat';

const props = defineProps({
    article: { type: Object, required: true },
    /** featured | compact | standard */
    variant: { type: String, default: 'standard' },
});

const href = computed(() => `/knowledge-base/articles/${props.article.slug}`);

const excerptLimit = computed(() => {
    if (props.variant === 'compact') return 120;
    if (props.variant === 'featured') return 280;
    return 320;
});

const excerptText = computed(() => {
    const raw = props.article.excerpt?.trim() || richContentPlainText(props.article.content);
    if (!raw) return '';
    const plain = richContentPlainText(raw);
    const limit = excerptLimit.value;
    return plain.length > limit ? `${plain.slice(0, limit - 1)}…` : plain;
});

const isFeatured = computed(() => props.variant === 'featured');
const isCompact = computed(() => props.variant === 'compact');

const authorName = computed(() => {
    const a = props.article.author;
    if (!a) return '';
    return (a.name || a.full_name || '').trim();
});
</script>

<template>
  <article
    class="kb-post-card group overflow-hidden rounded-card border border-slate-200/70 bg-white shadow-sm transition duration-300 ease-out hover:border-brand/20 hover:shadow-[0_12px_40px_-12px_rgba(154,0,54,0.12)]"
    :class="{
      'kb-post-card--featured lg:flex lg:min-h-[280px]': isFeatured,
      'kb-post-card--compact flex h-full flex-col': isCompact,
    }"
  >
    <Link
      :href="href"
      class="kb-post-card__media relative block shrink-0 overflow-hidden bg-slate-100"
      :class="isFeatured
        ? 'aspect-[16/10] lg:aspect-auto lg:w-[42%] lg:min-h-[280px]'
        : isCompact
          ? 'aspect-[16/10]'
          : 'aspect-[2/1] sm:aspect-[21/9]'"
    >
      <img
        v-if="article.cover_url"
        :src="article.cover_url"
        alt=""
        class="h-full w-full object-cover transition duration-500 ease-out group-hover:scale-[1.03]"
        loading="lazy"
      >
      <div
        v-else
        class="flex h-full w-full flex-col items-center justify-center gap-2 bg-gradient-to-br from-brand/[0.08] via-slate-50 to-white text-brand/25"
      >
        <AppIcon
          name="knowledge"
          :size="isCompact ? 28 : isFeatured ? 48 : 40"
        />
        <span
          v-if="!isCompact"
          class="text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-400/90"
        >
          Tri thức
        </span>
      </div>
      <div
        class="pointer-events-none absolute inset-0 bg-gradient-to-t from-slate-900/25 via-transparent to-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100"
        aria-hidden="true"
      />
      <span
        v-if="isFeatured"
        class="absolute left-4 top-4 rounded-full bg-white/95 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-brand shadow-sm backdrop-blur-sm"
      >
        Nổi bật
      </span>
    </Link>

    <div
      class="flex min-w-0 flex-1 flex-col"
      :class="isCompact ? 'gap-2 p-4' : isFeatured ? 'justify-center gap-3 p-5 sm:p-6 lg:p-8' : 'gap-3 p-5 sm:px-6 sm:py-6'"
    >
      <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-slate-400">
        <span
          v-if="article.category"
          class="rounded-full bg-brand/[0.06] px-2 py-0.5 font-medium text-brand/90"
        >
          {{ article.category.name }}
        </span>
        <span
          v-if="article.published_at"
          class="tabular-nums"
        >
          <span class="font-semibold not-italic text-slate-400">Ngày ·</span>
          {{ date(article.published_at) }}
        </span>
      </div>

      <p
        v-if="authorName"
        class="text-xs leading-snug text-slate-500"
      >
        <span class="font-semibold uppercase tracking-wide text-slate-400">Tác giả ·</span>
        <span class="font-display italic text-slate-700">{{ authorName }}</span>
        <template v-if="article.author?.role_title">
          <span class="text-slate-300"> — </span>
          <span class="not-italic">{{ article.author.role_title }}</span>
        </template>
      </p>

      <h2
        class="font-display font-semibold leading-snug text-slate-900 transition-colors group-hover:text-brand/95"
        :class="isFeatured ? 'text-xl sm:text-2xl lg:text-[1.65rem]' : isCompact ? 'text-base' : 'text-xl sm:text-2xl'"
      >
        <Link :href="href">
          {{ article.title }}
        </Link>
      </h2>

      <p
        v-if="excerptText"
        class="leading-relaxed text-slate-600"
        :class="isCompact ? 'line-clamp-2 text-xs' : 'text-sm'"
      >
        {{ excerptText }}
      </p>

      <div
        v-if="article.tags?.length && !isCompact"
        class="flex flex-wrap gap-1.5"
      >
        <span
          v-for="t in article.tags.slice(0, isFeatured ? 8 : 6)"
          :key="t.id"
          class="rounded-full border border-slate-100 bg-slate-50/80 px-2 py-0.5 text-[10px] font-medium text-slate-500"
        >
          {{ t.name }}
        </span>
      </div>

      <div
        class="mt-auto flex flex-wrap items-center justify-between gap-2 border-t border-slate-100/80 pt-3"
        :class="isCompact ? 'text-xs' : 'text-sm'"
      >
        <span class="text-slate-400">{{ article.view_count ?? 0 }} lượt xem</span>
        <Link
          :href="href"
          class="inline-flex items-center gap-1 font-medium text-brand transition group-hover:gap-1.5"
        >
          Đọc tiếp
          <AppIcon
            name="chevron-right"
            :size="14"
            class="transition group-hover:translate-x-0.5"
          />
        </Link>
      </div>
    </div>
  </article>
</template>

