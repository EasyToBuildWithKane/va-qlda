<script setup>
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import { evidenceLinkHostname, resolveEvidencePreviewImage } from '@/composables/useEvidenceLinkPreview';

const props = defineProps({
    url: { type: String, required: true },
    label: { type: String, default: '' },
});

const cardRef = ref(null);
const previewRef = ref(null);
const hover = ref(false);
const previewUrl = ref(null);
const previewLoading = ref(false);
const previewFailed = ref(false);
const previewStyle = ref({});

let loadToken = 0;

const displayLabel = () => props.label?.trim() || evidenceLinkHostname(props.url);

async function ensurePreview() {
    if (previewUrl.value || previewLoading.value || previewFailed.value) return;

    const token = ++loadToken;
    previewLoading.value = true;
    const resolved = await resolveEvidencePreviewImage(props.url);
    if (token !== loadToken) return;

    previewLoading.value = false;
    if (resolved) {
        previewUrl.value = resolved;
    } else {
        previewFailed.value = true;
    }
}

async function positionPreview() {
    await nextTick();
    const card = cardRef.value;
    const tip = previewRef.value;
    if (!card || !tip) return;

    const rect = card.getBoundingClientRect();
    const tipRect = tip.getBoundingClientRect();
    const gap = 10;
    const pad = 12;
    const maxW = Math.min(320, window.innerWidth - pad * 2);

    let left = rect.left;
    left = Math.max(pad, Math.min(left, window.innerWidth - maxW - pad));

    let top = rect.bottom + gap;
    if (top + tipRect.height > window.innerHeight - pad) {
        top = Math.max(pad, rect.top - tipRect.height - gap);
    }

    previewStyle.value = {
        position: 'fixed',
        left: `${left}px`,
        top: `${top}px`,
        width: `${maxW}px`,
        zIndex: 210,
    };
}

function onEnter() {
    hover.value = true;
    ensurePreview().then(() => {
        if (hover.value && previewUrl.value) positionPreview();
    });
    positionPreview();
}

function onLeave() {
    hover.value = false;
}

function onPreviewLoad() {
    positionPreview();
}

function onPreviewError() {
    previewFailed.value = true;
    previewUrl.value = null;
}

watch(hover, (open) => {
    if (open && previewUrl.value) {
        positionPreview();
        window.addEventListener('scroll', positionPreview, true);
        window.addEventListener('resize', positionPreview);
    } else {
        window.removeEventListener('scroll', positionPreview, true);
        window.removeEventListener('resize', positionPreview);
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', positionPreview, true);
    window.removeEventListener('resize', positionPreview);
});
</script>

<template>
  <div
    ref="cardRef"
    class="evidence-link-card group relative rounded-lg border border-slate-200/90 bg-white transition hover:border-brand/25 hover:shadow-sm"
    @mouseenter="onEnter"
    @mouseleave="onLeave"
    @focusin="onEnter"
    @focusout="onLeave"
  >
    <a
      :href="url"
      target="_blank"
      rel="noopener noreferrer"
      class="flex min-w-0 items-start gap-3 px-3 py-2.5"
    >
      <span class="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-md bg-brand-50 text-brand">
        <AppIcon
          name="dependency"
          :size="15"
        />
      </span>
      <span class="min-w-0 flex-1">
        <span class="block truncate text-sm font-medium text-slate-800 group-hover:text-brand">
          {{ displayLabel() }}
        </span>
        <span class="mt-0.5 block truncate text-[11px] text-slate-400">
          {{ url }}
        </span>
      </span>
      <AppIcon
        name="link"
        :size="14"
        class="mt-1 shrink-0 text-slate-300 group-hover:text-brand"
      />
    </a>
    <p
      v-if="hover && previewLoading && !previewUrl"
      class="border-t border-slate-100 px-3 py-1.5 text-[10px] text-slate-400"
    >
      Đang tải xem trước…
    </p>

    <Teleport to="body">
      <div
        v-if="hover && previewUrl"
        ref="previewRef"
        :style="previewStyle"
        class="pointer-events-none overflow-hidden rounded-lg border border-slate-200 bg-white shadow-elevation-3"
        role="img"
        :aria-label="`Xem trước ${displayLabel()}`"
      >
        <img
          :src="previewUrl"
          alt=""
          class="max-h-64 w-full object-contain bg-slate-50"
          loading="lazy"
          @load="onPreviewLoad"
          @error="onPreviewError"
        >
        <p class="border-t border-slate-100 px-2 py-1 text-center text-[10px] text-slate-400">
          Rê chuột để xem · Bấm link để mở tab mới
        </p>
      </div>
    </Teleport>
  </div>
</template>
