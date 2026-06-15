<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import EmptyState from '@/shared/ui/EmptyState.vue';
import { skillGroupTone, skillLevelLabel } from '../utils/skillGroupTone';

const props = defineProps({
    skills: { type: Object, required: true },
});

const groups = computed(() => props.skills?.groups ?? []);

function barPercent(item) {
    return item.percent ?? Math.round(((item.level ?? 3) / 5) * 100);
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
            {{ skills.total }} kỹ năng · {{ groups.length }} nhóm · thanh = mức độ 1–5
          </p>
        </div>
      </div>
    </header>

    <div class="p-5">
      <EmptyState
        v-if="skills.total === 0"
        icon="sparkles"
        title="Chưa có kỹ năng"
        description="Thêm kỹ năng và nhóm tùy chỉnh trong «Quản lý ma trận kỹ năng»."
      />

      <div
        v-else
        class="space-y-7"
      >
        <section
          v-for="group in groups"
          :key="group.key"
          class="skill-matrix-group"
        >
          <div class="mb-3 flex items-center gap-3">
            <span
              class="h-8 w-1 shrink-0 rounded-full"
              :class="skillGroupTone(group.key).barClass"
            />
            <div class="min-w-0 flex-1">
              <h3 class="font-display text-[13px] font-semibold tracking-tight text-slate-800">
                {{ group.label }}
              </h3>
              <p class="text-[11px] text-slate-400">
                {{ group.items.length }} kỹ năng
              </p>
            </div>
          </div>

          <ul class="space-y-4">
            <li
              v-for="item in group.items"
              :key="item.name"
              class="rounded-xl border border-slate-100 bg-slate-50/40 px-3.5 py-3 ring-1 ring-inset"
              :class="skillGroupTone(group.key).ringClass"
            >
              <div class="mb-2 flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <p class="text-[13px] font-semibold text-slate-800">
                    {{ item.name }}
                  </p>
                  <p
                    v-if="item.note"
                    class="mt-0.5 text-[11.5px] leading-snug text-slate-500"
                  >
                    {{ item.note }}
                  </p>
                </div>
                <div class="shrink-0 text-right">
                  <span class="font-mono text-[11px] font-semibold tabular-nums text-slate-600">
                    {{ skillLevelLabel(item.level) }}
                  </span>
                  <p
                    v-if="item.years"
                    class="text-[10px] text-slate-400"
                  >
                    {{ item.years }} năm
                  </p>
                </div>
              </div>

              <div
                class="relative h-2 overflow-hidden rounded-full bg-slate-200/80"
                role="progressbar"
                :aria-valuenow="barPercent(item)"
                aria-valuemin="0"
                aria-valuemax="100"
                :aria-label="`${item.name} — mức ${skillLevelLabel(item.level)}`"
              >
                <div
                  class="h-full rounded-full transition-[width] duration-500 ease-out"
                  :class="skillGroupTone(group.key).barClass"
                  :style="{ width: `${barPercent(item)}%` }"
                />
              </div>
            </li>
          </ul>
        </section>
      </div>
    </div>
  </section>
</template>
