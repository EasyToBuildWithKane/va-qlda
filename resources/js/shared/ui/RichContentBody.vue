<script setup>
/* eslint-disable vue/no-v-html -- nội dung HTML từ TipTap (dự án, KB, …) */
import { computed } from 'vue';
import { isRichHtmlContent } from '@/shared/utils/richContent';

const props = defineProps({
    content: { type: String, default: '' },
    emptyText: { type: String, default: '' },
    htmlClass: {
        type: String,
        default: 'prose prose-sm max-w-none dark:prose-invert',
    },
    plainClass: {
        type: String,
        default: 'whitespace-pre-wrap text-sm leading-relaxed',
    },
    emptyClass: {
        type: String,
        default: 'text-sm leading-relaxed',
    },
});

const trimmed = computed(() => String(props.content ?? '').trim());
const isHtml = computed(() => isRichHtmlContent(trimmed.value));
</script>

<template>
  <div
    v-if="trimmed && isHtml"
    :class="htmlClass"
    v-html="trimmed"
  />
  <p
    v-else-if="trimmed"
    :class="plainClass"
  >
    {{ trimmed }}
  </p>
  <p
    v-else-if="emptyText"
    :class="emptyClass"
  >
    {{ emptyText }}
  </p>
</template>
