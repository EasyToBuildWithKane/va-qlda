<script setup>
import { computed, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/shared/ui/Avatar.vue';

const props = defineProps({
    lanes: { type: Array, default: () => [] },
    searchQuery: { type: String, default: '' },
});

const emit = defineEmits(['select-member']);

const dot = {
    slate: 'bg-slate-400',
    sky: 'bg-sky-500',
    amber: 'bg-amber-500',
    emerald: 'bg-emerald-500',
    rose: 'bg-rose-500',
    violet: 'bg-violet-500',
    cyan: 'bg-cyan-500',
    brand: 'bg-brand',
};

const COLLAPSE_KEY = 'va-qlda.my-work.team-lanes-collapsed';

function loadCollapsed() {
    try {
        const raw = localStorage.getItem(COLLAPSE_KEY);
        if (raw) return new Set(JSON.parse(raw));
    } catch {
        /* ignore */
    }
    return new Set();
}

const collapsed = ref(loadCollapsed());
watch(collapsed, (v) => {
    localStorage.setItem(COLLAPSE_KEY, JSON.stringify([...v]));
}, { deep: true });

function isOpen(key) {
    return !collapsed.value.has(key);
}
function toggle(key) {
    const s = new Set(collapsed.value);
    if (s.has(key)) s.delete(key);
    else s.add(key);
    collapsed.value = s;
}

const q = computed(() => props.searchQuery.trim().toLowerCase());

function filterProject(project) {
    if (!q.value) return true;
    const pName = (project.project?.name ?? '').toLowerCase();
    const pCode = (project.project?.code ?? '').toLowerCase();
    if (pName.includes(q.value) || pCode.includes(q.value)) return true;
    return (project.members ?? []).some((m) => (m.name ?? '').toLowerCase().includes(q.value));
}

const displayLanes = computed(() =>
    props.lanes
        .map((lane) => ({
            ...lane,
            projects: (lane.projects ?? []).filter(filterProject),
        }))
        .filter((lane) => (lane.projects ?? []).length > 0),
);
</script>

<template>
  <div
    v-if="displayLanes.length === 0"
    class="px-5 py-12 text-center text-sm text-slate-500"
  >
    <AppIcon
      name="task"
      :size="32"
      class="mx-auto mb-2 text-slate-300"
    />
    Không có dự án / việc mở khớp bộ lọc trong phạm vi nhóm.
  </div>

  <div
    v-else
    class="space-y-4 px-4 pb-5 pt-2 sm:px-5"
  >
    <div
      v-for="lane in displayLanes"
      :key="lane.key"
      class="rounded-card bg-slate-100/70 p-3"
    >
      <button
        type="button"
        class="flex w-full flex-wrap items-center gap-2 rounded-md text-left transition hover:bg-white/60"
        :class="isOpen(lane.key) ? 'mb-3 px-1 py-1' : 'px-1 py-1'"
        @click="toggle(lane.key)"
      >
        <AppIcon
          :name="isOpen(lane.key) ? 'chevron-down' : 'chevron-right'"
          :size="16"
          class="shrink-0 text-slate-400"
        />
        <span
          class="h-2.5 w-2.5 shrink-0 rounded-full"
          :class="dot[lane.color] || dot.slate"
        />
        <span class="text-sm font-semibold text-slate-800">{{ lane.label }}</span>
        <span class="text-[11px] text-slate-500">Phòng ban dự án</span>
        <span class="rounded-full bg-white px-2 py-0.5 text-xs font-semibold tabular-nums text-slate-600 shadow-sm">
          {{ lane.projects.length }} dự án
        </span>
        <span
          v-if="lane.overdue > 0"
          class="rounded-full bg-rose-100 px-2 py-0.5 text-[11px] font-semibold text-rose-700"
        >
          {{ lane.overdue }} quá hạn
        </span>
        <span
          v-if="lane.dueToday > 0"
          class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800"
        >
          {{ lane.dueToday }} hôm nay
        </span>
        <span class="ml-auto text-xs tabular-nums text-slate-500">{{ lane.open }} việc mở</span>
      </button>

      <div
        v-show="isOpen(lane.key)"
        class="team-lane-row flex gap-3 overflow-x-auto pb-1"
      >
        <article
          v-for="group in lane.projects"
          :key="group.project.id"
          class="flex w-[min(100%,20rem)] shrink-0 flex-col rounded-xl border border-slate-200/90 bg-white shadow-sm"
        >
          <div class="border-b border-slate-100 px-3.5 py-3">
            <div class="flex items-start gap-2">
              <span
                class="mt-1.5 h-2 w-2 shrink-0 rounded-full"
                :style="{ backgroundColor: group.project.color || '#9A0036' }"
              />
              <div class="min-w-0 flex-1">
                <Link
                  :href="`/projects/${group.project.id}`"
                  class="line-clamp-2 font-display text-sm font-semibold text-slate-800 hover:text-brand"
                >
                  {{ group.project.name }}
                </Link>
                <p
                  v-if="group.project.code"
                  class="text-[11px] text-slate-400"
                >
                  {{ group.project.code }}
                </p>
              </div>
            </div>
            <div class="mt-2 flex flex-wrap gap-1.5 text-[11px] font-medium">
              <span class="rounded-md bg-slate-100 px-1.5 py-0.5 tabular-nums text-slate-600">{{ group.open }} mở</span>
              <span
                v-if="group.overdue > 0"
                class="rounded-md bg-rose-50 px-1.5 py-0.5 text-rose-700"
              >{{ group.overdue }} quá hạn</span>
              <span
                v-if="group.dueToday > 0"
                class="rounded-md bg-amber-50 px-1.5 py-0.5 text-amber-800"
              >{{ group.dueToday }} hôm nay</span>
            </div>
          </div>

          <ul class="max-h-56 flex-1 space-y-0.5 overflow-y-auto p-2">
            <li
              v-for="m in group.members"
              :key="m.id"
            >
              <button
                type="button"
                class="flex w-full items-center gap-2 rounded-lg px-2 py-2 text-left text-sm transition hover:bg-slate-50"
                @click="emit('select-member', m.id)"
              >
                <Avatar
                  :name="m.name"
                  :src="m.avatar_path"
                  :size="28"
                />
                <span class="min-w-0 flex-1 truncate font-medium text-slate-700">{{ m.name }}</span>
                <span class="shrink-0 text-xs tabular-nums text-slate-500">{{ m.open }}</span>
                <span
                  v-if="m.overdue > 0"
                  class="shrink-0 text-[10px] font-semibold text-rose-600"
                >{{ m.overdue }} QH</span>
              </button>
            </li>
          </ul>
        </article>
      </div>
    </div>
  </div>
</template>

<style scoped>
.team-lane-row {
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 transparent;
}
.team-lane-row::-webkit-scrollbar {
    height: 6px;
}
</style>
