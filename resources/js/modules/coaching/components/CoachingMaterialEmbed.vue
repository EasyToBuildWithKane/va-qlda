<script setup>
import { computed } from 'vue';

const props = defineProps({
    url: { type: String, default: '' },
    embedSrc: { type: String, default: '' },
    title: { type: String, default: 'Xem trước' },
});

const src = computed(() => props.embedSrc || null);
</script>

<template>
  <div class="space-y-2">
    <iframe
      v-if="src"
      :src="src"
      :title="title"
      class="aspect-video w-full rounded-lg border border-slate-200 bg-slate-50"
      sandbox="allow-scripts allow-same-origin allow-popups"
      loading="lazy"
      referrerpolicy="no-referrer"
    />
    <a
      v-if="url"
      :href="url"
      target="_blank"
      rel="noopener noreferrer"
      class="inline-block text-sm text-brand hover:underline"
    >
      Mở trong tab mới
    </a>
  </div>
</template>
