<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import ProgressBar from '@/shared/ui/ProgressBar.vue';
import EmptyState from '@/shared/ui/EmptyState.vue';

defineProps({
    skills: { type: Object, required: true },
});

const catColor = {
    backend: 'sky',
    frontend: 'violet',
    mobile: 'cyan',
    data: 'amber',
    ai: 'brand',
    devops: 'teal',
    design: 'rose',
    management: 'emerald',
    other: 'slate',
};

const chipTone = {
    sky: 'bg-sky-50 text-sky-700 ring-sky-100',
    violet: 'bg-violet-50 text-violet-700 ring-violet-100',
    cyan: 'bg-cyan-50 text-cyan-700 ring-cyan-100',
    amber: 'bg-amber-50 text-amber-700 ring-amber-100',
    brand: 'bg-brand/5 text-brand ring-brand/10',
    teal: 'bg-teal-50 text-teal-700 ring-teal-100',
    rose: 'bg-rose-50 text-rose-700 ring-rose-100',
    emerald: 'bg-emerald-50 text-emerald-700 ring-emerald-100',
    slate: 'bg-slate-50 text-slate-600 ring-slate-200',
};

function groupHasLevels(group) {
    return group.items.some((i) => i.level != null);
}
</script>

<template>
  <section class="rounded-2xl border border-slate-200/70 bg-white shadow-sm">
    <header class="flex items-center justify-between border-b border-slate-100 px-5 py-3.5">
      <div class="flex items-center gap-2.5">
        <div class="grid h-8 w-8 place-items-center rounded-lg bg-brand/10 text-brand">
          <AppIcon
            name="sparkles"
            :size="16"
          />
        </div>
        <div>
          <h2 class="text-sm font-semibold text-slate-800">
            Ma trận kỹ năng
          </h2>
          <p class="text-[12px] text-slate-400">
            {{ skills.total }} kỹ năng theo {{ skills.groups.length }} lĩnh vực
          </p>
        </div>
      </div>
    </header>

    <div class="p-5">
      <EmptyState
        v-if="skills.total === 0"
        icon="sparkles"
        title="Chưa có kỹ năng"
        description="Thêm kỹ năng để dựng ma trận năng lực."
      />

      <div
        v-else
        class="space-y-6"
      >
        <div
          v-for="group in skills.groups"
          :key="group.key"
        >
          <div class="mb-2.5 flex items-center gap-2">
            <h3 class="text-[12px] font-semibold uppercase tracking-wide text-slate-500">
              {{ group.label }}
            </h3>
            <span class="text-[11px] text-slate-300">{{ group.items.length }}</span>
          </div>

          <!-- Leveled → bars -->
          <div
            v-if="groupHasLevels(group)"
            class="space-y-3"
          >
            <div
              v-for="item in group.items"
              :key="item.name"
            >
              <div class="mb-1 flex items-center justify-between gap-2">
                <span class="flex items-center gap-1.5 text-[13px] font-medium text-slate-700">
                  {{ item.name }}
                  <AppIcon
                    v-if="item.certified"
                    name="certified"
                    :size="13"
                    class="text-emerald-500"
                  />
                </span>
                <span class="text-[11px] text-slate-400">
                  <template v-if="item.years">{{ item.years }} năm</template>
                </span>
              </div>
              <ProgressBar :value="item.percent ?? 0" />
            </div>
          </div>

          <!-- No levels → chips -->
          <div
            v-else
            class="flex flex-wrap gap-1.5"
          >
            <span
              v-for="item in group.items"
              :key="item.name"
              class="inline-flex items-center rounded-lg px-2.5 py-1 text-[12.5px] font-medium ring-1 ring-inset"
              :class="chipTone[catColor[group.key]] || chipTone.slate"
            >
              {{ item.name }}
            </span>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>
