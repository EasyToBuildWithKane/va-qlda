<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import EmptyState from '@/shared/ui/EmptyState.vue';
import { datetime } from '@/composables/useFormat';

defineProps({
    items: { type: Array, default: () => [] },
});

const tone = {
    emerald: 'bg-emerald-50 text-emerald-600',
    sky: 'bg-sky-50 text-sky-600',
    violet: 'bg-violet-50 text-violet-600',
    slate: 'bg-slate-100 text-slate-500',
};
</script>

<template>
  <section class="rounded-2xl border border-slate-200/70 bg-white shadow-sm">
    <header class="flex items-center gap-2.5 border-b border-slate-100 px-5 py-3.5">
      <div class="grid h-8 w-8 place-items-center rounded-lg bg-brand/10 text-brand">
        <AppIcon
          name="report-history"
          :size="16"
        />
      </div>
      <h2 class="text-sm font-semibold text-slate-800">
        Nhật ký hoạt động
      </h2>
    </header>

    <div class="p-5">
      <EmptyState
        v-if="!items.length"
        icon="report-history"
        title="Chưa có hoạt động"
        description="Hoàn thành công việc hoặc ghi nhận giờ để tạo nhật ký."
      />

      <ol
        v-else
        class="relative space-y-3.5 before:absolute before:left-[13px] before:top-2 before:bottom-2 before:w-px before:bg-slate-100"
      >
        <li
          v-for="ev in items"
          :key="ev.id"
          class="relative flex items-start gap-3"
        >
          <span
            class="z-10 mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-full ring-4 ring-white"
            :class="tone[ev.color] || tone.slate"
          >
            <AppIcon
              :name="ev.icon"
              :size="13"
            />
          </span>
          <div class="min-w-0 flex-1">
            <p class="text-[13px] text-slate-700">
              <span class="font-medium">{{ ev.title }}</span>
              <template v-if="ev.subject">
                : {{ ev.subject }}
              </template>
            </p>
            <p class="mt-0.5 flex flex-wrap items-center gap-x-2 text-[11.5px] text-slate-400">
              <span v-if="ev.project">{{ ev.project }}</span>
              <span
                v-if="ev.project"
                class="text-slate-300"
              >·</span>
              <span>{{ datetime(ev.at) }}</span>
            </p>
          </div>
        </li>
      </ol>
    </div>
  </section>
</template>
