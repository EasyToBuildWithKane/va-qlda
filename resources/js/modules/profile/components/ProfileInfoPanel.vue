<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import { useProfileSectionCollapse } from '../composables/useProfileSectionCollapse';

const props = defineProps({
    title: { type: String, required: true },
    icon: { type: String, required: true },
    subtitle: { type: String, default: null },
    /** Bật thu gọn; cần sectionKey để nhớ trạng thái */
    collapsible: { type: Boolean, default: true },
    sectionKey: { type: String, default: null },
    defaultOpen: { type: Boolean, default: true },
    /** Hiển thị khi đang thu gọn (vd. số lượng) */
    collapsedBadge: { type: String, default: null },
});

const { open, toggle } = useProfileSectionCollapse(
    props.collapsible ? (props.sectionKey ?? props.title) : null,
    props.defaultOpen,
);
</script>

<template>
  <section class="profile-panel overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
    <button
      v-if="collapsible"
      type="button"
      class="profile-panel__head flex w-full items-start gap-3 border-b border-slate-100 px-5 py-4 text-left transition-colors cursor-pointer hover:bg-slate-50/90"
      :aria-expanded="open"
      @click="toggle"
    >
      <div
        class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-slate-900 text-white shadow-sm ring-1 ring-slate-900/10"
        aria-hidden="true"
      >
        <AppIcon
          :name="icon"
          :size="16"
        />
      </div>
      <div class="min-w-0 flex-1 pt-0.5">
        <div class="flex flex-wrap items-center gap-2">
          <h2 class="font-display text-sm font-semibold tracking-tight text-slate-900">
            {{ title }}
          </h2>
          <span
            v-if="collapsedBadge && !open"
            class="rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600"
          >
            {{ collapsedBadge }}
          </span>
        </div>
        <p
          v-if="subtitle && open"
          class="mt-0.5 text-[12px] leading-snug text-slate-500"
        >
          {{ subtitle }}
        </p>
      </div>
      <AppIcon
        :name="open ? 'chevron-down' : 'chevron-right'"
        :size="18"
        class="mt-1 shrink-0 text-slate-400"
        aria-hidden="true"
      />
    </button>
    <header
      v-else
      class="profile-panel__head flex items-start gap-3 border-b border-slate-100 px-5 py-4"
    >
      <div
        class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-slate-900 text-white shadow-sm ring-1 ring-slate-900/10"
        aria-hidden="true"
      >
        <AppIcon
          :name="icon"
          :size="16"
        />
      </div>
      <div class="min-w-0 pt-0.5">
        <h2 class="font-display text-sm font-semibold tracking-tight text-slate-900">
          {{ title }}
        </h2>
        <p
          v-if="subtitle"
          class="mt-0.5 text-[12px] leading-snug text-slate-500"
        >
          {{ subtitle }}
        </p>
      </div>
    </header>
    <div
      v-show="!collapsible || open"
      class="profile-panel__body"
    >
      <slot />
    </div>
  </section>
</template>

<style scoped>
.profile-panel__head {
    background: linear-gradient(180deg, rgb(248 250 252 / 0.95) 0%, rgb(255 255 255) 100%);
}

button.profile-panel__head {
    appearance: none;
    border: none;
    font: inherit;
    color: inherit;
}
</style>