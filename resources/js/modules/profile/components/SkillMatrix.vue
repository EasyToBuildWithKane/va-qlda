<script setup>
import { computed, ref } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import EmptyState from '@/shared/ui/EmptyState.vue';
import ProfileInfoPanel from './ProfileInfoPanel.vue';
import { skillGroupTone, skillLevelLabel } from '../utils/skillGroupTone';

const props = defineProps({
    skills: { type: Object, required: true },
});

const groups = computed(() => props.skills?.groups ?? []);

const collapsedGroups = ref(new Set());

function toggleGroup(key) {
    const next = new Set(collapsedGroups.value);
    if (next.has(key)) {
        next.delete(key);
    } else {
        next.add(key);
    }
    collapsedGroups.value = next;
}

function isGroupOpen(key) {
    return !collapsedGroups.value.has(key);
}

function barPercent(item) {
    return item.percent ?? Math.round(((item.level ?? 3) / 5) * 100);
}

const matrixBadge = computed(() => {
    const t = props.skills?.total ?? 0;
    return t ? `${t} kỹ năng` : null;
});
</script>

<template>
  <ProfileInfoPanel
    title="Ma trận kỹ năng"
    icon="sparkles"
    :subtitle="`${skills.total} kỹ năng · ${groups.length} nhóm · thanh = mức độ 1–5`"
    section-key="profile-skill-matrix"
    :collapsed-badge="matrixBadge"
  >
    <div class="p-5">
      <EmptyState
        v-if="skills.total === 0"
        icon="sparkles"
        title="Chưa có kỹ năng"
        description="Thêm kỹ năng và nhóm tùy chỉnh trong «Quản lý ma trận kỹ năng»."
      />

      <div
        v-else
        class="space-y-3"
      >
        <section
          v-for="group in groups"
          :key="group.key"
          class="overflow-hidden rounded-xl border border-slate-200/80"
        >
          <button
            type="button"
            class="flex w-full items-center gap-3 bg-slate-50/80 px-3.5 py-2.5 text-left transition-colors hover:bg-slate-100/80"
            :aria-expanded="isGroupOpen(group.key)"
            @click="toggleGroup(group.key)"
          >
            <span
              class="h-7 w-1 shrink-0 rounded-full"
              :class="skillGroupTone(group.key).barClass"
            />
            <div class="min-w-0 flex-1">
              <h3 class="font-display text-[13px] font-semibold text-slate-800">
                {{ group.label }}
              </h3>
              <p class="text-[11px] text-slate-400">
                {{ group.items.length }} kỹ năng
              </p>
            </div>
            <AppIcon
              :name="isGroupOpen(group.key) ? 'chevron-down' : 'chevron-right'"
              :size="16"
              class="shrink-0 text-slate-400"
            />
          </button>

          <ul
            v-show="isGroupOpen(group.key)"
            class="space-y-3 border-t border-slate-100 p-3.5"
          >
            <li
              v-for="item in group.items"
              :key="item.name"
              class="rounded-xl border border-slate-100 bg-white px-3.5 py-3 ring-1 ring-inset"
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
  </ProfileInfoPanel>
</template>
