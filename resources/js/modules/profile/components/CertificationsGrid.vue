<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import EmptyState from '@/shared/ui/EmptyState.vue';
import { date } from '@/composables/useFormat';

defineProps({
    items: { type: Array, default: () => [] },
});
</script>

<template>
  <section class="rounded-2xl border border-slate-200/70 bg-white shadow-sm">
    <header class="flex items-center gap-2.5 border-b border-slate-100 px-5 py-3.5">
      <div class="grid h-8 w-8 place-items-center rounded-lg bg-brand/10 text-brand">
        <AppIcon
          name="award"
          :size="16"
        />
      </div>
      <h2 class="text-sm font-semibold text-slate-800">
        Chứng chỉ
      </h2>
      <span class="text-[12px] text-slate-300">{{ items.length }}</span>
    </header>

    <div class="p-5">
      <EmptyState
        v-if="!items.length"
        icon="award"
        title="Chưa có chứng chỉ"
        description="Thêm chứng chỉ để minh chứng năng lực."
      />

      <div
        v-else
        class="grid grid-cols-1 gap-3 sm:grid-cols-2"
      >
        <div
          v-for="c in items"
          :key="c.id"
          class="flex items-start gap-3 rounded-xl border border-slate-100 p-3.5"
        >
          <div class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-amber-50 text-amber-500">
            <AppIcon
              name="award"
              :size="20"
            />
          </div>
          <div class="min-w-0 flex-1">
            <div class="flex items-start justify-between gap-2">
              <a
                v-if="c.credential_url"
                :href="c.credential_url"
                target="_blank"
                rel="noopener noreferrer"
                class="truncate text-[13px] font-semibold text-slate-800 hover:text-brand"
              >{{ c.name }}</a>
              <span
                v-else
                class="truncate text-[13px] font-semibold text-slate-800"
              >{{ c.name }}</span>
              <Badge
                :label="c.status.label"
                :color="c.status.color"
              />
            </div>
            <p
              v-if="c.provider"
              class="truncate text-[12px] text-slate-400"
            >
              {{ c.provider }}
            </p>
            <p class="mt-1 flex flex-wrap gap-x-3 text-[11.5px] text-slate-400">
              <span v-if="c.issued_at">Cấp: {{ date(c.issued_at) }}</span>
              <span v-if="c.expires_at">HSD: {{ date(c.expires_at) }}</span>
            </p>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>
