<script setup>
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import { usePermission } from '@/shared/composables/usePermission';

defineProps({
    embedded: { type: Boolean, default: false },
});

const emit = defineEmits(['navigate']);

const page = usePage();
const { isAtLeast } = usePermission();
const canCreate = computed(() => isAtLeast('lead'));

const sidebar = computed(() => page.props.coaching?.sidebar ?? {
    courses: [], sessions: [], activeCourseId: null, activeSessionId: null,
});
const courses = computed(() => sidebar.value.courses ?? []);
const sessions = computed(() => sidebar.value.sessions ?? []);
const activeCourseId = computed(() => sidebar.value.activeCourseId ?? null);
const activeSessionId = computed(() => sidebar.value.activeSessionId ?? null);

const q = ref('');
const statusFilter = ref('');

const COURSE_DOT = {
    planning: 'bg-slate-300',
    active: 'bg-emerald-500',
    completed: 'bg-sky-500',
    cancelled: 'bg-rose-400',
};
const SESSION_DOT = {
    pending: 'bg-slate-300',
    in_progress: 'bg-amber-500',
    completed: 'bg-emerald-500',
    cancelled: 'bg-rose-400',
};

const statusOptions = computed(() => {
    const seen = new Map();
    courses.value.forEach((c) => { if (c.status) seen.set(c.status.value, c.status.label); });
    return [...seen.entries()].map(([value, label]) => ({ value, label }));
});

const filteredCourses = computed(() => {
    const term = q.value.trim().toLowerCase();
    return courses.value.filter((c) => {
        if (statusFilter.value && c.status?.value !== statusFilter.value) return false;
        if (!term) return true;
        return [c.name, c.code, c.student_display]
            .filter(Boolean)
            .some((s) => String(s).toLowerCase().includes(term));
    });
});

const onNavigate = () => emit('navigate');
</script>

<template>
  <div
    class="flex h-full flex-col bg-white"
    :class="embedded ? '' : 'card overflow-hidden'"
  >
    <!-- Header: title + create + filters -->
    <div class="shrink-0 border-b border-slate-100 px-3 py-3">
      <div class="mb-2 flex items-center justify-between">
        <span class="text-xs font-bold uppercase tracking-wide text-slate-500">Khóa học</span>
        <Link
          v-if="canCreate"
          href="/coaching/courses?create=1"
          class="inline-flex h-7 items-center gap-1 rounded-btn border border-brand/25 bg-brand/5 px-2 text-[11px] font-medium text-brand transition hover:bg-brand/10"
          @click="onNavigate"
        >
          <AppIcon
            name="add"
            :size="13"
          />
          Khóa
        </Link>
      </div>
      <div class="relative">
        <AppIcon
          name="search"
          :size="14"
          class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400"
        />
        <input
          v-model="q"
          type="search"
          placeholder="Tìm khóa học…"
          class="input h-8 w-full pl-8 text-xs"
          aria-label="Tìm khóa học"
        >
      </div>
      <select
        v-if="statusOptions.length"
        v-model="statusFilter"
        class="input mt-2 h-8 w-full text-xs"
        aria-label="Lọc trạng thái khóa học"
      >
        <option value="">
          Tất cả trạng thái
        </option>
        <option
          v-for="s in statusOptions"
          :key="s.value"
          :value="s.value"
        >
          {{ s.label }}
        </option>
      </select>
    </div>

    <!-- Course list -->
    <div class="min-h-0 flex-1 overflow-y-auto px-2 py-2">
      <p
        v-if="!filteredCourses.length"
        class="px-2 py-6 text-center text-xs text-slate-400"
      >
        Không có khóa học phù hợp.
      </p>
      <div
        v-for="c in filteredCourses"
        :key="c.id"
      >
        <Link
          :href="route('coaching.courses.show', { course: c.id })"
          class="group flex items-center gap-2 rounded-lg px-2.5 py-2 text-sm transition"
          :class="c.id === activeCourseId
            ? 'bg-brand/10 text-brand'
            : 'text-slate-700 hover:bg-slate-50'"
          @click="onNavigate"
        >
          <span
            class="h-2 w-2 shrink-0 rounded-full"
            :class="COURSE_DOT[c.status?.value] || 'bg-slate-300'"
          />
          <span class="min-w-0 flex-1">
            <span class="block truncate font-medium leading-tight">{{ c.name }}</span>
            <span class="block truncate text-[11px] text-slate-400">
              {{ c.code }}<template v-if="c.student_display"> · {{ c.student_display }}</template>
            </span>
          </span>
          <span class="shrink-0 rounded-full bg-slate-100 px-1.5 text-[10px] font-semibold tabular-nums text-slate-500">
            {{ c.sessions_count }}
          </span>
        </Link>

        <!-- Sessions of the active course -->
        <div
          v-if="c.id === activeCourseId && sessions.length"
          class="mb-1 ml-3.5 mt-0.5 space-y-0.5 border-l border-slate-100 pl-2"
        >
          <Link
            v-for="s in sessions"
            :key="s.id"
            :href="route('coaching.sessions.show', { session: s.id })"
            class="flex items-center gap-2 rounded-md px-2 py-1.5 text-xs transition"
            :class="s.id === activeSessionId
              ? 'bg-brand/5 font-medium text-brand'
              : 'text-slate-600 hover:bg-slate-50'"
            @click="onNavigate"
          >
            <span
              class="h-1.5 w-1.5 shrink-0 rounded-full"
              :class="SESSION_DOT[s.status?.value] || 'bg-slate-300'"
            />
            <span class="shrink-0 font-mono text-[10px] text-slate-400">#{{ s.session_number }}</span>
            <span class="min-w-0 flex-1 truncate">{{ s.title }}</span>
          </Link>
        </div>
        <p
          v-else-if="c.id === activeCourseId && !sessions.length"
          class="mb-1 ml-3.5 mt-0.5 border-l border-slate-100 py-1.5 pl-3 text-[11px] text-slate-400"
        >
          Chưa có buổi học.
        </p>
      </div>
    </div>
  </div>
</template>
