<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import { richContentPlainText } from '@/shared/utils/richContent';
import { datetime, date } from '@/composables/useFormat';
import { useDialog } from '@/composables/useDialog';
import { useToast } from '@/shared/composables/useToast';

const props = defineProps({
    article: { type: Object, required: true },
    showStatus: { type: Boolean, default: true },
    showCategory: { type: Boolean, default: true },
    showExcerpt: { type: Boolean, default: true },
    showTags: { type: Boolean, default: true },
    showViews: { type: Boolean, default: true },
    showAuthor: { type: Boolean, default: false },
    showUpdated: { type: Boolean, default: false },
});

const dialog = useDialog();
const toast = useToast();
const deleting = ref(false);

const href = computed(() => `/knowledge-base/articles/${props.article.slug}`);

const authorName = computed(() => {
    const a = props.article.author;
    if (!a) return '';
    return (a.name || a.full_name || '').trim();
});

const excerptText = computed(() => {
    const raw = props.article.excerpt?.trim() || richContentPlainText(props.article.content);
    if (!raw) return '';
    const plain = richContentPlainText(raw);
    return plain.length > 160 ? `${plain.slice(0, 157)}…` : plain;
});

const categoryColor = computed(() => props.article.category?.color || null);

async function confirmDelete() {
    if (deleting.value || !props.article.can?.delete) return;
    const ok = await dialog.confirm({
        title: 'Xóa bài viết',
        message: `Bài «${props.article.title}» sẽ bị xóa vĩnh viễn. Tiếp tục?`,
        tone: 'danger',
        confirmText: 'Xóa',
    });
    if (!ok) return;
    deleting.value = true;
    router.delete(route('knowledge-base.articles.destroy', props.article.slug), {
        preserveScroll: true,
        onSuccess: () => toast.success('Đã xóa bài viết.'),
        onError: () => toast.error('Không xóa được bài viết.'),
        onFinish: () => {
            deleting.value = false;
        },
    });
}
</script>

<template>
  <article
    class="group relative flex h-full flex-col overflow-hidden rounded-card border border-slate-200/90 bg-white shadow-sm ring-1 ring-slate-100/80 transition hover:-translate-y-0.5 hover:border-brand/30 hover:shadow-lg"
  >
    <div
      class="pointer-events-none absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-brand via-brand/70 to-brand/30"
      aria-hidden="true"
    />

    <div
      v-if="article.can?.delete || article.can?.update"
      class="absolute right-2 top-2 z-20 flex gap-1 opacity-100 sm:opacity-0 sm:transition sm:group-hover:opacity-100 sm:focus-within:opacity-100"
    >
      <Link
        v-if="article.can?.update"
        :href="`/knowledge-base/articles/${article.slug}/edit`"
        class="pointer-events-auto inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200/90 bg-white/95 text-slate-600 shadow-sm backdrop-blur hover:border-brand/30 hover:text-brand"
        title="Chỉnh sửa"
        aria-label="Chỉnh sửa bài viết"
        @click.stop
      >
        <AppIcon
          name="edit"
          :size="15"
        />
      </Link>
      <button
        v-if="article.can?.delete"
        type="button"
        class="pointer-events-auto inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200/90 bg-white/95 text-slate-500 shadow-sm backdrop-blur hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600 disabled:opacity-50"
        title="Xóa bài"
        aria-label="Xóa bài viết"
        :disabled="deleting"
        @click.stop="confirmDelete"
      >
        <AppIcon
          name="delete"
          :size="15"
        />
      </button>
    </div>

    <Link
      :href="href"
      class="block shrink-0 overflow-hidden"
    >
      <div class="relative aspect-[16/10] w-full bg-slate-100">
        <img
          v-if="article.cover_url"
          :src="article.cover_url"
          alt=""
          class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]"
          loading="lazy"
        >
        <div
          v-else
          class="flex h-full w-full flex-col items-center justify-center gap-2 bg-gradient-to-br from-brand/[0.08] via-slate-50 to-white"
        >
          <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/80 text-brand/40 shadow-sm ring-1 ring-slate-200/80">
            <AppIcon
              name="knowledge"
              :size="26"
            />
          </div>
        </div>
        <div
          v-if="showCategory && article.category"
          class="absolute bottom-2 left-2 max-w-[85%] truncate rounded-md px-2 py-1 text-[11px] font-semibold text-white shadow-sm backdrop-blur-sm"
          :class="categoryColor ? '' : 'bg-slate-900/75'"
          :style="categoryColor ? { backgroundColor: `${categoryColor}dd` } : undefined"
        >
          {{ article.category.name }}
        </div>
      </div>
    </Link>

    <div class="flex min-h-0 flex-1 flex-col p-4 pt-3.5">
      <div
        v-if="showStatus && article.status"
        class="mb-2"
      >
        <Badge
          :label="article.status.label"
          color="slate"
        />
      </div>

      <h3 class="font-display text-base font-semibold leading-snug text-slate-900">
        <Link
          :href="href"
          class="line-clamp-2 transition hover:text-brand"
        >
          {{ article.title }}
        </Link>
      </h3>

      <p
        v-if="showExcerpt && excerptText"
        class="mt-2 line-clamp-3 flex-1 text-sm leading-relaxed text-slate-500"
      >
        {{ excerptText }}
      </p>

      <div
        v-if="showAuthor && authorName"
        class="mt-3 flex items-start gap-2.5 rounded-lg border border-slate-100/90 bg-gradient-to-br from-slate-50/90 to-white px-3 py-2.5"
      >
        <div
          v-if="article.author?.avatar_path"
          class="h-9 w-9 shrink-0 overflow-hidden rounded-full ring-2 ring-white shadow-sm"
        >
          <img
            :src="article.author.avatar_path"
            alt=""
            class="h-full w-full object-cover"
            loading="lazy"
          >
        </div>
        <div
          v-else
          class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand/[0.08] text-brand/70 ring-1 ring-brand/10"
        >
          <AppIcon
            name="user"
            :size="16"
          />
        </div>
        <dl class="min-w-0 flex-1">
          <dt class="text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-400">
            Tác giả
          </dt>
          <dd class="font-display text-sm font-medium italic leading-snug text-slate-800">
            {{ authorName }}
          </dd>
          <dd
            v-if="article.author?.role_title"
            class="mt-0.5 text-[11px] leading-snug text-slate-500"
          >
            <span class="font-medium not-italic text-slate-400">Chức danh ·</span>
            {{ article.author.role_title }}
          </dd>
          <dd
            v-if="article.author?.code"
            class="mt-0.5 font-mono text-[10px] text-slate-400"
          >
            <span class="font-sans font-medium not-italic text-slate-400">Mã NV ·</span>
            {{ article.author.code }}
          </dd>
        </dl>
      </div>

      <div
        v-if="showTags && article.tags?.length"
        class="mt-3 flex flex-wrap gap-1"
      >
        <span
          v-for="t in article.tags.slice(0, 3)"
          :key="t.id"
          class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-600"
        >
          #{{ t.name }}
        </span>
      </div>

      <div class="mt-auto flex flex-wrap items-center gap-x-3 gap-y-1 border-t border-slate-100 pt-3 text-[11px] text-slate-400">
        <span
          v-if="showViews"
          class="inline-flex items-center gap-1"
        >
          <AppIcon
            name="eye"
            :size="12"
          />
          <span class="font-medium text-slate-500">Lượt xem ·</span>
          {{ article.view_count ?? 0 }}
        </span>
        <span
          v-if="article.published_at"
          class="inline-flex items-center gap-1"
        >
          <AppIcon
            name="calendar"
            :size="12"
          />
          <span class="font-medium text-slate-500">Ngày đăng ·</span>
          {{ date(article.published_at) }}
        </span>
        <span v-if="showUpdated && article.updated_at">
          <span class="font-medium text-slate-500">Cập nhật ·</span>
          {{ datetime(article.updated_at) }}
        </span>
      </div>
    </div>
  </article>
</template>
