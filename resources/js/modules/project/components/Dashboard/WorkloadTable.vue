<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import { hours } from '@/composables/useFormat';
import { displayOrEmpty, EMPTY_LABELS } from '@/shared/utils/emptyDisplay';

const props = defineProps({
    rows: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
});

const emit = defineEmits(['add-member', 'edit-member']);

const CAPACITY_HOURS = 40;

const LOAD = {
    healthy: {
        label: 'Bình thường',
        badge: 'bg-emerald-50 text-emerald-700 ring-emerald-200/80 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-800/60',
        bar: 'bg-emerald-500',
        tone: 'text-emerald-600 dark:text-emerald-400',
    },
    watch: {
        label: 'Cần theo dõi',
        badge: 'bg-amber-50 text-amber-800 ring-amber-200/80 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-800/60',
        bar: 'bg-amber-500',
        tone: 'text-amber-700 dark:text-amber-400',
    },
    overloaded: {
        label: 'Quá tải',
        badge: 'bg-rose-50 text-rose-700 ring-rose-200/80 dark:bg-rose-950/40 dark:text-rose-300 dark:ring-rose-800/60',
        bar: 'bg-rose-500',
        tone: 'text-rose-600 dark:text-rose-400',
    },
};

const sortedRows = computed(() => {
    const rank = { overloaded: 0, watch: 1, healthy: 2 };
    return [...props.rows].sort((a, b) => {
        const ra = rank[a.load] ?? 2;
        const rb = rank[b.load] ?? 2;
        if (ra !== rb) return ra - rb;
        return (b.activeTasks ?? 0) - (a.activeTasks ?? 0);
    });
});

const stats = computed(() => {
    const list = props.rows;
    const overloaded = list.filter((r) => r.load === 'overloaded').length;
    const watch = list.filter((r) => r.load === 'watch').length;
    const activeTasks = list.reduce((s, r) => s + (r.activeTasks ?? 0), 0);
    const totalHours = list.reduce((s, r) => s + Number(r.totalHours || 0), 0);
    return {
        members: list.length,
        overloaded,
        watch,
        healthy: list.length - overloaded - watch,
        activeTasks,
        totalHours,
    };
});

const summaryTiles = computed(() => [
    {
        key: 'members',
        label: 'Thành viên',
        value: stats.value.members,
        icon: 'members',
        sub: `${stats.value.healthy} ổn định`,
        tone: 'text-brand bg-brand/10 ring-brand/15',
    },
    {
        key: 'active',
        label: 'Việc đang mở',
        value: stats.value.activeTasks,
        icon: 'task',
        sub: 'Chưa hoàn thành',
        tone: 'text-sky-700 bg-sky-50 ring-sky-200/70 dark:text-sky-300 dark:bg-sky-950/40 dark:ring-sky-800/50',
    },
    {
        key: 'hours',
        label: 'Giờ ước lượng',
        value: hours(stats.value.totalHours),
        icon: 'clock',
        sub: `Ngưỡng ${CAPACITY_HOURS}h / người`,
        tone: 'text-violet-700 bg-violet-50 ring-violet-200/70 dark:text-violet-300 dark:bg-violet-950/40 dark:ring-violet-800/50',
    },
    {
        key: 'risk',
        label: 'Cảnh báo tải',
        value: stats.value.overloaded + stats.value.watch,
        icon: 'blockers',
        sub: stats.value.overloaded
            ? `${stats.value.overloaded} quá tải · ${stats.value.watch} theo dõi`
            : (stats.value.watch ? `${stats.value.watch} cần theo dõi` : 'Không có cảnh báo'),
        tone: (stats.value.overloaded || stats.value.watch)
            ? 'text-rose-700 bg-rose-50 ring-rose-200/70 dark:text-rose-300 dark:bg-rose-950/40 dark:ring-rose-800/50'
            : 'text-emerald-700 bg-emerald-50 ring-emerald-200/70 dark:text-emerald-300 dark:bg-emerald-950/40 dark:ring-emerald-800/50',
    },
]);

function loadMeta(row) {
    return LOAD[row.load] || LOAD.healthy;
}

function capacityPct(row) {
    if (row.capacityPct != null) return Math.min(100, Math.max(0, row.capacityPct));
    return Math.min(100, Math.round((Number(row.totalHours || 0) / CAPACITY_HOURS) * 100));
}
</script>

<template>
  <section
    class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-700/80 dark:bg-slate-900"
    aria-label="Phân bổ công việc thành viên"
  >
    <header class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 px-4 py-3.5 sm:px-5 dark:border-slate-800">
      <div class="min-w-0">
        <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-brand/80">
          Workload
        </p>
        <h2 class="mt-0.5 font-display text-base font-semibold tracking-tight text-slate-900 dark:text-slate-50 sm:text-lg">
          Phân bổ công việc
        </h2>
        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
          Theo dõi tải việc, giờ ước lượng và tiến độ cá nhân trên dự án
        </p>
      </div>
      <div class="flex shrink-0 flex-wrap items-center gap-2">
        <span
          class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold tabular-nums text-slate-600 ring-1 ring-slate-200/80 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700"
        >
          <AppIcon
            name="members"
            :size="13"
          />
          {{ rows.length }} thành viên
        </span>
        <button
          v-if="canManage"
          type="button"
          class="btn-primary inline-flex h-9 items-center gap-1.5 px-3 text-xs font-semibold"
          aria-label="Thêm thành viên vào dự án"
          @click="emit('add-member')"
        >
          <AppIcon
            name="add"
            :size="15"
          />
          Thêm thành viên
        </button>
      </div>
    </header>

    <!-- Summary -->
    <div
      v-if="rows.length"
      class="grid grid-cols-2 gap-px border-b border-slate-100 bg-slate-100 sm:grid-cols-4 dark:border-slate-800 dark:bg-slate-800"
    >
      <div
        v-for="tile in summaryTiles"
        :key="tile.key"
        class="flex items-start gap-2.5 bg-white px-3.5 py-3 sm:px-4 dark:bg-slate-900"
      >
        <span
          class="grid h-9 w-9 shrink-0 place-items-center rounded-lg ring-1"
          :class="tile.tone"
        >
          <AppIcon
            :name="tile.icon"
            :size="15"
          />
        </span>
        <div class="min-w-0">
          <p class="text-[10px] font-medium uppercase tracking-wider text-slate-400 dark:text-slate-500">
            {{ tile.label }}
          </p>
          <p class="mt-0.5 font-display text-lg font-semibold tabular-nums leading-none text-slate-900 dark:text-slate-50">
            {{ tile.value }}
          </p>
          <p class="mt-1 truncate text-[11px] text-slate-500 dark:text-slate-400">
            {{ tile.sub }}
          </p>
        </div>
      </div>
    </div>

    <!-- Legend -->
    <div
      v-if="rows.length"
      class="flex flex-wrap items-center gap-x-4 gap-y-1.5 border-b border-slate-100 bg-slate-50/80 px-4 py-2 text-[11px] text-slate-500 sm:px-5 dark:border-slate-800 dark:bg-slate-800/40 dark:text-slate-400"
    >
      <span class="font-medium text-slate-600 dark:text-slate-300">Mức tải:</span>
      <span
        v-for="(meta, key) in LOAD"
        :key="key"
        class="inline-flex items-center gap-1.5"
      >
        <span
          class="h-2 w-2 rounded-full"
          :class="meta.bar"
        />
        {{ meta.label }}
      </span>
      <span class="ml-auto hidden sm:inline">Quá tải khi &gt; 3 việc mở hoặc &gt; {{ CAPACITY_HOURS }}h</span>
    </div>

    <!-- Member rows -->
    <ul
      v-if="sortedRows.length"
      class="divide-y divide-slate-100 dark:divide-slate-800"
    >
      <li
        v-for="row in sortedRows"
        :key="row.member.id"
        class="px-4 py-3.5 transition-colors hover:bg-slate-50/80 sm:px-5 dark:hover:bg-slate-800/40"
      >
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:gap-5">
          <!-- Person -->
          <div class="flex min-w-0 flex-1 items-center gap-3 lg:max-w-[16rem] lg:shrink-0">
            <Avatar
              :name="row.member.name"
              :src="row.member.avatar_path"
              :size="40"
              class="ring-2 ring-white dark:ring-slate-800"
            />
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-1.5">
                <p class="truncate font-medium text-slate-800 dark:text-slate-100">
                  {{ row.member.name }}
                </p>
                <span
                  class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold ring-1 ring-inset"
                  :class="loadMeta(row).badge"
                >
                  {{ loadMeta(row).label }}
                </span>
              </div>
              <p class="mt-0.5 truncate text-xs text-slate-400 dark:text-slate-500">
                {{ displayOrEmpty(row.member.project_role, EMPTY_LABELS.role) }}
              </p>
            </div>
          </div>

          <!-- Metrics -->
          <div class="grid flex-1 grid-cols-3 gap-2 sm:gap-3 lg:max-w-sm">
            <div class="rounded-xl border border-slate-200/70 bg-slate-50/70 px-2.5 py-2 text-center dark:border-slate-700/70 dark:bg-slate-800/40">
              <p class="text-[10px] font-medium uppercase tracking-wider text-slate-400">
                Đang mở
              </p>
              <p class="mt-0.5 font-display text-base font-semibold tabular-nums text-slate-800 dark:text-slate-100">
                {{ row.activeTasks }}
              </p>
            </div>
            <div class="rounded-xl border border-slate-200/70 bg-slate-50/70 px-2.5 py-2 text-center dark:border-slate-700/70 dark:bg-slate-800/40">
              <p class="text-[10px] font-medium uppercase tracking-wider text-slate-400">
                Hoàn thành
              </p>
              <p class="mt-0.5 font-display text-base font-semibold tabular-nums text-slate-800 dark:text-slate-100">
                {{ row.doneTasks ?? 0 }}<span class="text-xs font-normal text-slate-400">/{{ row.totalAssigned ?? 0 }}</span>
              </p>
            </div>
            <div class="rounded-xl border border-slate-200/70 bg-slate-50/70 px-2.5 py-2 text-center dark:border-slate-700/70 dark:bg-slate-800/40">
              <p class="text-[10px] font-medium uppercase tracking-wider text-slate-400">
                Giờ EL
              </p>
              <p
                class="mt-0.5 font-display text-base font-semibold tabular-nums"
                :class="loadMeta(row).tone"
              >
                {{ hours(row.totalHours) }}
              </p>
            </div>
          </div>

          <!-- Bars -->
          <div class="min-w-0 flex-1 space-y-2.5 lg:max-w-xs">
            <div>
              <div class="mb-1 flex items-center justify-between gap-2 text-[11px]">
                <span class="font-medium text-slate-500 dark:text-slate-400">Dung lượng</span>
                <span class="tabular-nums text-slate-500">{{ capacityPct(row) }}% · {{ hours(row.totalHours) }}/{{ CAPACITY_HOURS }}h</span>
              </div>
              <div class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-700">
                <div
                  class="h-full rounded-full transition-all duration-500"
                  :class="loadMeta(row).bar"
                  :style="{ width: capacityPct(row) + '%' }"
                />
              </div>
            </div>
            <div>
              <div class="mb-1 flex items-center justify-between gap-2 text-[11px]">
                <span class="font-medium text-slate-500 dark:text-slate-400">Tiến độ</span>
                <span class="tabular-nums text-slate-500">{{ row.personalProgress }}%</span>
              </div>
              <div class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-700">
                <div
                  class="h-full rounded-full transition-all duration-500"
                  :class="row.progressColor"
                  :style="{ width: row.personalProgress + '%' }"
                />
              </div>
            </div>
          </div>

          <button
            v-if="canManage"
            type="button"
            class="btn-ghost inline-flex h-9 shrink-0 items-center gap-1.5 self-start px-2.5 text-xs lg:self-center"
            :aria-label="`Cập nhật thành viên ${row.member.name}`"
            @click="emit('edit-member', row.member)"
          >
            <AppIcon
              name="edit"
              :size="14"
            />
            <span class="hidden sm:inline">Sửa</span>
          </button>
        </div>
      </li>
    </ul>

    <div
      v-else
      class="flex flex-col items-center justify-center gap-3 px-4 py-12 text-center"
    >
      <span class="grid h-12 w-12 place-items-center rounded-2xl bg-slate-100 text-slate-400 dark:bg-slate-800">
        <AppIcon
          name="members"
          :size="22"
        />
      </span>
      <div class="space-y-1">
        <p class="text-sm font-medium text-slate-600 dark:text-slate-300">
          Chưa có thành viên
        </p>
        <p class="mx-auto max-w-xs text-xs text-slate-400">
          Thêm nhân sự vào dự án để theo dõi phân bổ công việc và dung lượng
        </p>
      </div>
      <button
        v-if="canManage"
        type="button"
        class="btn-primary inline-flex h-9 items-center gap-1.5 px-3 text-xs font-semibold"
        aria-label="Thêm thành viên vào dự án"
        @click="emit('add-member')"
      >
        <AppIcon
          name="add"
          :size="15"
        />
        Thêm thành viên
      </button>
    </div>
  </section>
</template>
