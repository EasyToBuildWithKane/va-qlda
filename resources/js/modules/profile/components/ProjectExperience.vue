<script setup>
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import EmptyState from '@/shared/ui/EmptyState.vue';
import { date } from '@/composables/useFormat';

defineProps({
    items: { type: Array, default: () => [] },
});
</script>

<template>
  <section class="rounded-2xl border border-slate-200/70 bg-white shadow-sm">
    <header class="flex items-center gap-2.5 border-b border-slate-100 px-5 py-3.5">
      <div class="grid h-8 w-8 place-items-center rounded-lg bg-brand/10 text-brand">
        <AppIcon
          name="briefcase"
          :size="16"
        />
      </div>
      <div>
        <h2 class="text-sm font-semibold text-slate-800">
          Kinh nghiệm dự án
        </h2>
        <p class="text-[12px] text-slate-400">
          {{ items.length }} dự án đã tham gia
        </p>
      </div>
    </header>

    <div class="p-5">
      <EmptyState
        v-if="!items.length"
        icon="projects"
        title="Chưa tham gia dự án"
        description="Khi được thêm vào dự án, lịch sử sẽ hiển thị tại đây."
      />

      <ol
        v-else
        class="relative space-y-4 before:absolute before:left-[15px] before:top-2 before:bottom-2 before:w-px before:bg-slate-100"
      >
        <li
          v-for="p in items"
          :key="p.id"
          class="relative flex gap-3.5"
        >
          <span
            class="z-10 mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-full ring-4 ring-white"
            :style="{ backgroundColor: (p.color || '#9A0036') + '1a', color: p.color || '#9A0036' }"
          >
            <AppIcon
              name="projects"
              :size="14"
            />
          </span>
          <div class="min-w-0 flex-1 rounded-xl border border-slate-100 p-3.5 transition-colors hover:border-slate-200">
            <div class="flex flex-wrap items-start justify-between gap-2">
              <div class="min-w-0">
                <Link
                  :href="`/projects/${p.id}`"
                  class="truncate text-[14px] font-semibold text-slate-800 hover:text-brand"
                >
                  {{ p.name }}
                </Link>
                <p class="mt-0.5 text-[12px] text-slate-400">
                  {{ p.code }}<template v-if="p.role">
                    · {{ p.role }}
                  </template>
                </p>
              </div>
              <div class="flex shrink-0 items-center gap-1.5">
                <Badge
                  v-if="p.status"
                  :label="p.status.label"
                  :color="p.status.color"
                />
                <Badge
                  v-if="!p.is_active"
                  label="Đã rời"
                  color="slate"
                />
              </div>
            </div>

            <div class="mt-2.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-[12px] text-slate-500">
              <span
                v-if="p.joined_at"
                class="inline-flex items-center gap-1"
              >
                <AppIcon
                  name="calendar"
                  :size="12"
                  class="text-slate-300"
                />
                Từ {{ date(p.joined_at) }}
              </span>
              <span
                v-if="p.allocation"
                class="inline-flex items-center gap-1"
              >
                <AppIcon
                  name="clock"
                  :size="12"
                  class="text-slate-300"
                />
                {{ p.allocation }}% phân bổ
              </span>
              <span class="inline-flex items-center gap-1">
                <AppIcon
                  name="task"
                  :size="12"
                  class="text-slate-300"
                />
                {{ p.tasks_done }}/{{ p.tasks_total }} việc hoàn thành
              </span>
            </div>
          </div>
        </li>
      </ol>
    </div>
  </section>
</template>
