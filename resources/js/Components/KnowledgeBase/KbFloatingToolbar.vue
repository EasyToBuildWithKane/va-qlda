<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import HoverTooltip from '@/shared/ui/HoverTooltip.vue';
import { useToast } from '@/shared/composables/useToast';

const props = defineProps({
    isFavorite: { type: Boolean, default: false },
    favoriting: { type: Boolean, default: false },
    isRead: { type: Boolean, default: false },
    markingRead: { type: Boolean, default: false },
    shareUrl: { type: String, required: true },
    shareTitle: { type: String, default: '' },
});

const emit = defineEmits(['toggle-favorite', 'mark-read']);

const toast = useToast();

async function shareArticle() {
    const payload = { title: props.shareTitle, url: props.shareUrl };
    try {
        if (typeof navigator !== 'undefined' && navigator.share) {
            await navigator.share(payload);
            return;
        }
    } catch {
        /* user cancelled */
    }
    await copyLink();
}

async function copyLink() {
    try {
        await navigator.clipboard.writeText(props.shareUrl);
        toast.success('Đã sao chép liên kết.');
    } catch {
        toast.error('Không sao chép được liên kết.');
    }
}

function printArticle() {
    window.print();
}
</script>

<template>
  <aside
    class="pointer-events-none fixed right-4 top-1/2 z-40 hidden -translate-y-1/2 xl:block"
    aria-label="Thanh công cụ bài viết"
  >
    <div class="pointer-events-auto flex flex-col gap-2">
      <HoverTooltip
        v-if="!isRead"
        label="Đánh dấu đã đọc"
        placement="left"
      >
        <button
          type="button"
          class="grid h-11 w-11 place-items-center rounded-full border border-slate-200/80 bg-white/90 text-slate-600 shadow-sm backdrop-blur-sm transition hover:border-brand/30 hover:text-brand dark:border-slate-700 dark:bg-slate-900/90 dark:text-slate-300"
          :disabled="markingRead"
          @click="emit('mark-read')"
        >
          <AppIcon
            name="check"
            :size="18"
          />
        </button>
      </HoverTooltip>
      <HoverTooltip
        :label="isFavorite ? 'Đã lưu yêu thích' : 'Lưu bài viết'"
        placement="left"
      >
        <button
          type="button"
          class="grid h-11 w-11 place-items-center rounded-full border border-slate-200/80 bg-white/90 text-slate-600 shadow-sm backdrop-blur-sm transition hover:border-brand/30 hover:text-brand dark:border-slate-700 dark:bg-slate-900/90 dark:text-slate-300"
          :class="isFavorite ? 'border-amber-300/80 text-amber-600' : ''"
          :disabled="favoriting"
          @click="emit('toggle-favorite')"
        >
          <AppIcon
            name="star"
            :size="18"
          />
        </button>
      </HoverTooltip>
      <HoverTooltip
        label="Chia sẻ bài viết"
        placement="left"
      >
        <button
          type="button"
          class="grid h-11 w-11 place-items-center rounded-full border border-slate-200/80 bg-white/90 text-slate-600 shadow-sm backdrop-blur-sm transition hover:border-brand/30 hover:text-brand dark:border-slate-700 dark:bg-slate-900/90 dark:text-slate-300"
          @click="shareArticle"
        >
          <AppIcon
            name="link"
            :size="18"
          />
        </button>
      </HoverTooltip>
      <HoverTooltip
        label="Sao chép liên kết"
        placement="left"
      >
        <button
          type="button"
          class="grid h-11 w-11 place-items-center rounded-full border border-slate-200/80 bg-white/90 text-slate-600 shadow-sm backdrop-blur-sm transition hover:border-brand/30 hover:text-brand dark:border-slate-700 dark:bg-slate-900/90 dark:text-slate-300"
          @click="copyLink"
        >
          <AppIcon
            name="documents"
            :size="18"
          />
        </button>
      </HoverTooltip>
      <HoverTooltip
        label="In bài viết"
        placement="left"
      >
        <button
          type="button"
          class="grid h-11 w-11 place-items-center rounded-full border border-slate-200/80 bg-white/90 text-slate-600 shadow-sm backdrop-blur-sm transition hover:border-brand/30 hover:text-brand dark:border-slate-700 dark:bg-slate-900/90 dark:text-slate-300"
          @click="printArticle"
        >
          <AppIcon
            name="export"
            :size="18"
          />
        </button>
      </HoverTooltip>
      <HoverTooltip
        label="Bình luận"
        placement="left"
      >
        <a
          href="#comments"
          class="grid h-11 w-11 place-items-center rounded-full border border-slate-200/80 bg-white/90 text-slate-600 shadow-sm backdrop-blur-sm transition hover:border-brand/30 hover:text-brand dark:border-slate-700 dark:bg-slate-900/90 dark:text-slate-300"
        >
          <AppIcon
            name="comment"
            :size="18"
          />
        </a>
      </HoverTooltip>
    </div>
  </aside>
</template>
