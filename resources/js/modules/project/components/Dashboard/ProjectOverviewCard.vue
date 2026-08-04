<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import Badge from '@/shared/ui/Badge.vue';
import RichContentBody from '@/shared/ui/RichContentBody.vue';
import { date } from '@/composables/useFormat';
import { displayOrEmpty, EMPTY_LABELS } from '@/shared/utils/emptyDisplay';

const props = defineProps({
    project: { type: Object, required: true },
    daysLeft: { type: Number, default: null },
});

const deadlineBadge = computed(() => {
    if (props.daysLeft === null || props.daysLeft === undefined) return null;
    const d = props.daysLeft;
    if (d < 0) {
        return {
            label: `Trễ ${Math.abs(d)} ngày`,
            icon: 'flag',
            class: 'bg-rose-50 text-rose-700 ring-rose-200/80 dark:bg-rose-950/40 dark:text-rose-300 dark:ring-rose-800/60',
        };
    }
    if (d === 0) {
        return {
            label: 'Hết hạn hôm nay',
            icon: 'calendar-clock',
            class: 'bg-amber-50 text-amber-800 ring-amber-200/80 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-800/60',
        };
    }
    if (d <= 7) {
        return {
            label: `Còn ${d} ngày`,
            icon: 'calendar-clock',
            class: 'bg-amber-50 text-amber-800 ring-amber-200/80 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-800/60',
        };
    }
    if (d <= 14) {
        return {
            label: `Còn ${d} ngày`,
            icon: 'clock',
            class: 'bg-sky-50 text-sky-700 ring-sky-200/80 dark:bg-sky-950/40 dark:text-sky-300 dark:ring-sky-800/60',
        };
    }
    return {
        label: `Còn ${d} ngày`,
        icon: 'done',
        class: 'bg-emerald-50 text-emerald-700 ring-emerald-200/80 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-800/60',
    };
});

const infoTiles = computed(() => [
    {
        key: 'start',
        label: 'Bắt đầu',
        value: displayOrEmpty(date(props.project.start_date), EMPTY_LABELS.notUpdated),
        icon: 'calendar',
    },
    {
        key: 'end',
        label: 'Kết thúc',
        value: displayOrEmpty(date(props.project.due_date), EMPTY_LABELS.notUpdated),
        icon: 'flag',
        overdue: props.daysLeft !== null && props.daysLeft < 0,
    },
    {
        key: 'type',
        label: 'Loại dự án',
        value: displayOrEmpty(props.project.type?.label, EMPTY_LABELS.notUpdated),
        icon: 'portfolio',
    },
    {
        key: 'dept',
        label: 'Phòng ban',
        value: displayOrEmpty(props.project.department?.name, EMPTY_LABELS.team),
        icon: 'department',
    },
]);
</script>

<template>
  <article
    class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-700/80 dark:bg-slate-900"
  >
    <!-- Header -->
    <header class="flex items-start justify-between gap-3 border-b border-slate-100 bg-gradient-to-b from-slate-50/90 to-white px-4 py-3.5 sm:px-5 dark:border-slate-800 dark:from-slate-800/40 dark:to-slate-900">
      <div class="min-w-0 flex-1">
        <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-brand/80">
          Hồ sơ dự án
        </p>
        <h2 class="mt-0.5 truncate font-display text-lg font-semibold tracking-tight text-slate-900 dark:text-slate-50">
          {{ project.name }}
        </h2>
        <div class="mt-2 flex flex-wrap items-center gap-1.5">
          <span
            class="inline-flex items-center rounded-md bg-white px-2 py-0.5 font-mono text-[11px] font-medium tracking-tight text-slate-600 ring-1 ring-slate-200/80 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700"
          >
            {{ project.code }}
          </span>
          <Badge
            v-if="project.status?.label"
            :label="project.status.label"
            :color="project.status.color || 'slate'"
          />
        </div>
      </div>
      <span
        v-if="deadlineBadge"
        class="inline-flex shrink-0 items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset"
        :class="deadlineBadge.class"
      >
        <AppIcon
          :name="deadlineBadge.icon"
          :size="13"
        />
        {{ deadlineBadge.label }}
      </span>
    </header>

    <div class="space-y-4 px-4 py-4 sm:px-5">
      <!-- Description -->
      <section aria-label="Mô tả dự án">
        <h3 class="mb-1.5 text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
          Mô tả
        </h3>
        <RichContentBody
          :content="project.description"
          empty-text="Chưa có mô tả dự án."
          html-class="prose prose-sm max-w-none text-slate-600 dark:prose-invert dark:text-slate-400"
          plain-class="whitespace-pre-wrap text-sm font-normal leading-relaxed text-slate-600 dark:text-slate-400"
          empty-class="text-sm italic text-slate-400 dark:text-slate-500"
        />
      </section>

      <!-- Info tiles -->
      <section aria-label="Thông tin mốc">
        <h3 class="mb-2 text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
          Thông tin chính
        </h3>
        <div class="grid grid-cols-2 gap-2 lg:grid-cols-4">
          <div
            v-for="tile in infoTiles"
            :key="tile.key"
            class="group flex min-w-0 items-center gap-2.5 rounded-xl border border-slate-200/70 bg-slate-50/60 px-2.5 py-2.5 transition duration-150 hover:border-slate-300 hover:bg-white hover:shadow-sm dark:border-slate-700/70 dark:bg-slate-800/40 dark:hover:border-slate-600 dark:hover:bg-slate-800"
          >
            <span
              class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-white text-slate-500 shadow-sm ring-1 ring-slate-200/60 transition group-hover:text-brand dark:bg-slate-900 dark:text-slate-400 dark:ring-slate-700 dark:group-hover:text-brand-100"
            >
              <AppIcon
                :name="tile.icon"
                :size="15"
              />
            </span>
            <div class="min-w-0 flex-1">
              <p class="truncate text-[10px] font-medium uppercase tracking-wider text-slate-400 dark:text-slate-500">
                {{ tile.label }}
              </p>
              <p
                class="truncate text-sm font-medium text-slate-700 dark:text-slate-200"
                :class="tile.overdue ? 'text-rose-600 dark:text-rose-400' : ''"
              >
                {{ tile.value }}
              </p>
            </div>
          </div>
        </div>
      </section>

      <!-- PM profile -->
      <section
        v-if="project.manager"
        aria-label="Chủ dự án"
      >
        <h3 class="mb-2 text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
          Người phụ trách
        </h3>
        <div
          class="flex items-center gap-3 rounded-xl border border-slate-200/70 bg-gradient-to-r from-brand/[0.04] to-white px-3 py-2.5 dark:border-slate-700/70 dark:from-brand/10 dark:to-slate-900"
        >
          <div class="relative shrink-0">
            <Avatar
              :name="project.manager.name"
              :src="project.manager.avatar_path"
              :size="40"
              class="ring-2 ring-white dark:ring-slate-800"
            />
            <span
              class="absolute -bottom-0.5 -right-0.5 grid h-4 w-4 place-items-center rounded-full bg-brand text-white ring-2 ring-white dark:ring-slate-900"
            >
              <AppIcon
                name="star"
                :size="9"
                :stroke-width="2.5"
              />
            </span>
          </div>
          <div class="min-w-0 flex-1">
            <p class="text-[10px] font-medium uppercase tracking-wider text-slate-400 dark:text-slate-500">
              Chủ dự án (PM)
            </p>
            <p class="truncate font-medium text-slate-800 dark:text-slate-100">
              {{ project.manager.name }}
            </p>
          </div>
          <span
            class="hidden shrink-0 rounded-full bg-brand/10 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-brand sm:inline-flex dark:bg-brand/20 dark:text-brand-100"
          >
            PM
          </span>
        </div>
      </section>
    </div>
  </article>
</template>
