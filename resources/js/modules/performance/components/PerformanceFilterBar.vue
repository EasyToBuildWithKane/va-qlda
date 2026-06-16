<script setup>
import { reactive, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';

/**
 * Sticky Filter Bar dùng chung cho cả Executive Dashboard & Audit.
 * Đồng bộ filter ↔ query params (Inertia partial reload). Mọi widget đọc cùng
 * filter từ server nên luôn nhất quán.
 */
const props = defineProps({
    filter: { type: Object, required: true },
    options: { type: Object, required: true },
    // Ẩn các bộ lọc không liên quan (vd màn Audit luôn cần 1 thành viên).
    requireMember: { type: Boolean, default: false },
    processing: { type: Boolean, default: false },
});

const state = reactive({
    period: props.filter.period ?? 'month',
    date: props.filter.date ?? '',
    sprint: props.filter.sprint ?? null,
    department: props.filter.department ?? null,
    team: props.filter.team ?? null,
    member: props.filter.member ?? null,
    project: props.filter.project ?? null,
    status: Array.isArray(props.filter.status) ? [...props.filter.status] : [],
});

let timer = null;
let suppress = false;

function apply() {
    const params = {};
    Object.entries(state).forEach(([k, v]) => {
        if (v === null || v === '' || (Array.isArray(v) && v.length === 0)) return;
        params[k] = v;
    });
    router.get(window.location.pathname, params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

watch(state, () => {
    if (suppress) return;
    clearTimeout(timer);
    timer = setTimeout(apply, 250);
}, { deep: true });

// Đổi kiểu kỳ → dọn các trường không liên quan mà không bắn 2 request.
function setPeriod(p) {
    suppress = true;
    state.period = p;
    if (p !== 'sprint') state.sprint = null;
    suppress = false;
    apply();
}

function toggleStatus(value) {
    const i = state.status.indexOf(value);
    if (i === -1) state.status.push(value);
    else state.status.splice(i, 1);
}

function reset() {
    suppress = true;
    state.period = 'month';
    state.date = new Date().toISOString().slice(0, 10);
    state.sprint = null;
    state.department = null;
    state.team = null;
    state.member = null;
    state.project = null;
    state.status = [];
    suppress = false;
    apply();
}

const selectClass = 'h-9 rounded-lg border border-slate-200 bg-white px-2.5 text-sm text-slate-700 '
    + 'shadow-sm focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand';
</script>

<template>
  <div class="sticky top-0 z-20 -mx-1 mb-4 rounded-card border border-slate-200/80 bg-white/85 px-3 py-3 shadow-sm backdrop-blur supports-[backdrop-filter]:bg-white/70 print:hidden">
    <div class="flex flex-wrap items-center gap-2">
      <!-- Period segmented -->
      <div class="inline-flex rounded-lg border border-slate-200 bg-slate-50 p-0.5">
        <button
          v-for="p in options.periods"
          :key="p.value"
          type="button"
          class="rounded-md px-2.5 py-1 text-xs font-semibold transition-colors"
          :class="state.period === p.value
            ? 'bg-brand text-white shadow-sm'
            : 'text-slate-500 hover:text-slate-800'"
          @click="setPeriod(p.value)"
        >
          {{ p.label }}
        </button>
      </div>

      <!-- Anchor date / sprint -->
      <input
        v-if="state.period !== 'sprint'"
        v-model="state.date"
        type="date"
        :class="selectClass"
        aria-label="Mốc thời gian"
      >
      <select
        v-else
        v-model="state.sprint"
        :class="selectClass"
        aria-label="Sprint"
      >
        <option :value="null">
          — Chọn sprint —
        </option>
        <option
          v-for="s in options.sprints"
          :key="s.value"
          :value="s.value"
        >
          {{ s.label }}
        </option>
      </select>

      <span class="hidden h-5 w-px bg-slate-200 sm:block" />

      <select
        v-model="state.department"
        :class="selectClass"
        aria-label="Phòng ban"
      >
        <option :value="null">
          Tất cả phòng ban
        </option>
        <option
          v-for="d in options.departments"
          :key="d.value"
          :value="d.value"
        >
          {{ d.label }}
        </option>
      </select>

      <select
        v-model="state.team"
        :class="selectClass"
        aria-label="Team"
      >
        <option :value="null">
          Tất cả team
        </option>
        <option
          v-for="t in options.teams"
          :key="t.value"
          :value="t.value"
        >
          {{ t.label }}
        </option>
      </select>

      <select
        v-model="state.member"
        :class="selectClass"
        aria-label="Thành viên"
      >
        <option :value="null">
          {{ requireMember ? '— Chọn thành viên —' : 'Tất cả thành viên' }}
        </option>
        <option
          v-for="m in options.members"
          :key="m.value"
          :value="m.value"
        >
          {{ m.label }}
        </option>
      </select>

      <select
        v-model="state.project"
        :class="selectClass"
        aria-label="Dự án"
      >
        <option :value="null">
          Tất cả dự án
        </option>
        <option
          v-for="p in options.projects"
          :key="p.value"
          :value="p.value"
        >
          {{ p.label }}
        </option>
      </select>

      <button
        type="button"
        class="ml-auto inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-500 hover:bg-slate-50"
        @click="reset"
      >
        <AppIcon
          name="refresh"
          :size="14"
        />
        Đặt lại
      </button>

      <slot name="actions" />
    </div>

    <!-- Status chips -->
    <div
      v-if="!requireMember"
      class="mt-2 flex flex-wrap items-center gap-1.5"
    >
      <span class="text-[11px] font-medium text-slate-400">Trạng thái:</span>
      <button
        v-for="s in options.statuses"
        :key="s.value"
        type="button"
        class="rounded-full border px-2 py-0.5 text-[11px] font-medium transition-colors"
        :class="state.status.includes(s.value)
          ? 'border-brand bg-brand/10 text-brand'
          : 'border-slate-200 text-slate-500 hover:bg-slate-50'"
        @click="toggleStatus(s.value)"
      >
        {{ s.label }}
      </button>
    </div>

    <div
      v-if="processing"
      class="mt-2 h-0.5 w-full overflow-hidden rounded-full bg-slate-100"
    >
      <div class="h-full w-1/3 animate-pulse rounded-full bg-brand" />
    </div>
  </div>
</template>
