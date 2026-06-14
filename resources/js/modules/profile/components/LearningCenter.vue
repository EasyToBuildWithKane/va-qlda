<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import ProgressBar from '@/shared/ui/ProgressBar.vue';
import EmptyState from '@/shared/ui/EmptyState.vue';

const props = defineProps({
    items: { type: Array, default: () => [] },
});

// Fixed column order so the board reads completed → in progress → up next.
const columns = [
    { status: 'completed', label: 'Đã hoàn thành', color: 'emerald' },
    { status: 'in_progress', label: 'Đang học', color: 'sky' },
    { status: 'recommended', label: 'Đề xuất', color: 'amber' },
    { status: 'planned', label: 'Dự kiến', color: 'slate' },
];

const grouped = computed(() => {
    const map = {};
    for (const c of columns) map[c.status] = [];
    for (const it of props.items) {
        const key = it.status?.value;
        if (map[key]) map[key].push(it);
    }
    return map;
});

const dot = {
    emerald: 'bg-emerald-500',
    sky: 'bg-sky-500',
    amber: 'bg-amber-500',
    slate: 'bg-slate-400',
};
</script>

<template>
  <section class="rounded-2xl border border-slate-200/70 bg-white shadow-sm">
    <header class="flex items-center gap-2.5 border-b border-slate-100 px-5 py-3.5">
      <div class="grid h-8 w-8 place-items-center rounded-lg bg-brand/10 text-brand">
        <AppIcon
          name="learning"
          :size="16"
        />
      </div>
      <h2 class="text-sm font-semibold text-slate-800">
        Trung tâm học tập
      </h2>
    </header>

    <div class="p-5">
      <EmptyState
        v-if="!items.length"
        icon="learning"
        title="Chưa có khoá học"
        description="Khoá học đã/đang học và đề xuất sẽ hiển thị tại đây."
      />

      <div
        v-else
        class="grid grid-cols-1 gap-4 sm:grid-cols-2"
      >
        <div
          v-for="col in columns"
          :key="col.status"
        >
          <div class="mb-2 flex items-center gap-1.5">
            <span
              class="h-2 w-2 rounded-full"
              :class="dot[col.color]"
            />
            <h3 class="text-[12px] font-semibold uppercase tracking-wide text-slate-500">
              {{ col.label }}
            </h3>
            <span class="text-[11px] text-slate-300">{{ grouped[col.status].length }}</span>
          </div>

          <div
            v-if="grouped[col.status].length"
            class="space-y-2"
          >
            <div
              v-for="l in grouped[col.status]"
              :key="l.id"
              class="rounded-xl border border-slate-100 p-3"
            >
              <a
                v-if="l.url"
                :href="l.url"
                target="_blank"
                rel="noopener noreferrer"
                class="block truncate text-[13px] font-medium text-slate-700 hover:text-brand"
              >{{ l.title }}</a>
              <p
                v-else
                class="truncate text-[13px] font-medium text-slate-700"
              >
                {{ l.title }}
              </p>
              <p
                v-if="l.provider"
                class="truncate text-[11.5px] text-slate-400"
              >
                {{ l.provider }}
              </p>
              <div
                v-if="l.status.value === 'in_progress'"
                class="mt-2"
              >
                <ProgressBar
                  :value="l.progress"
                  height="h-1.5"
                />
              </div>
            </div>
          </div>
          <p
            v-else
            class="rounded-lg border border-dashed border-slate-200 py-3 text-center text-[12px] text-slate-300"
          >
            —
          </p>
        </div>
      </div>
    </div>
  </section>
</template>
