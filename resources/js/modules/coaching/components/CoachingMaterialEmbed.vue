<script setup>
import { computed } from 'vue';

const props = defineProps({
    url: { type: String, default: '' },
    embedSrc: { type: String, default: '' },
    title: { type: String, default: 'Xem trước' },
    /** Chiếm chiều cao lớn trong card tài liệu buổi học */
    tall: { type: Boolean, default: false },
    /** Ẩn link «Mở trong tab mới» khi parent đã có nút mở */
    hideExternalLink: { type: Boolean, default: false },
});

const src = computed(() => props.embedSrc || null);

const isDocumentPreview = computed(() => {
    const s = src.value ?? '';
    return s.includes('docs.google.com') || s.includes('drive.google.com');
});
</script>

<template>
  <div
    class="space-y-2"
    :class="tall ? 'flex min-h-0 flex-1 flex-col' : ''"
  >
    <iframe
      v-if="src"
      :src="src"
      :title="title"
      class="w-full rounded-lg border border-slate-200 bg-slate-50"
      :class="tall && isDocumentPreview
        ? 'min-h-[calc(100dvh-22rem)] flex-1'
        : tall
          ? 'aspect-video min-h-[20rem] flex-1'
          : isDocumentPreview
            ? 'min-h-[28rem] h-[min(58vh,40rem)]'
            : 'aspect-video min-h-[16rem]'"
      sandbox="allow-scripts allow-same-origin allow-popups"
      loading="lazy"
      referrerpolicy="no-referrer"
    />
    <a
      v-if="url && !hideExternalLink"
      :href="url"
      target="_blank"
      rel="noopener noreferrer"
      class="inline-block text-sm text-brand hover:underline"
    >
      Mở trong tab mới
    </a>
  </div>
</template>
