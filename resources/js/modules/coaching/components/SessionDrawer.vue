<script setup>
import { computed } from 'vue';
import Drawer from '@/Components/Ui/Drawer.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import { statusMeta } from '../composables/useCoachingCalendar.js';
import { date as fmtDate, hours as fmtHours } from '@/composables/useFormat';

const props = defineProps({
    show: { type: Boolean, default: false },
    session: { type: Object, default: null },
    busy: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'edit', 'reschedule', 'status', 'open-detail']);

const meta = computed(() => statusMeta(props.session?.status));

const timeRange = computed(() => {
    const s = props.session;
    if (!s) return null;
    if (s.startTime && s.endTime) return `${s.startTime} – ${s.endTime}`;
    if (s.startTime) return s.startTime;
    return 'Cả ngày';
});

const canManage = computed(() => !!props.session?.canManage);
const isClosed = computed(() => ['completed', 'cancelled'].includes(props.session?.status));

const rows = computed(() => {
    const s = props.session;
    if (!s) return [];
    return [
        { icon: 'members', label: 'Học viên', value: s.studentName || '—' },
        { icon: 'calendar', label: 'Ngày', value: s.date ? fmtDate(s.date) : '—' },
        { icon: 'clock', label: 'Thời gian', value: timeRange.value },
        {
            icon: 'calendar-clock',
            label: 'Thời lượng',
            value: s.durationHours != null ? fmtHours(s.durationHours) : '—',
        },
    ];
});
</script>

<template>
  <Drawer
    :show="show"
    width="max-w-md"
    flush
    @close="emit('close')"
  >
    <template v-if="session">
      <!-- Status header bar -->
      <div
        class="px-5 py-4"
        :style="{ backgroundColor: meta.tint }"
      >
        <div class="flex items-center justify-between gap-3">
          <span
            class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold text-white"
            :style="{ backgroundColor: meta.color }"
          >
            <span class="h-1.5 w-1.5 rounded-full bg-white/90" />
            {{ session.statusLabel || meta.label }}
          </span>
          <button
            type="button"
            class="grid h-8 w-8 place-items-center rounded-btn text-slate-500 hover:bg-white/60"
            aria-label="Đóng"
            @click="emit('close')"
          >
            <AppIcon
              name="close"
              :size="18"
            />
          </button>
        </div>
        <h2 class="mt-3 font-display text-lg font-semibold leading-snug text-slate-800">
          <span class="mr-1.5 font-mono text-sm text-slate-400">#{{ session.sessionNumber }}</span>
          {{ session.title }}
        </h2>
        <p
          v-if="session.courseName"
          class="mt-1 text-xs text-slate-500"
        >
          {{ session.courseCode }} · {{ session.courseName }}
        </p>
      </div>

      <div class="flex-1 overflow-y-auto px-5 py-4">
        <!-- People highlight -->
        <div class="mb-4 flex items-center gap-3 rounded-xl border border-slate-100 bg-slate-50/60 p-3">
          <Avatar
            :name="session.studentName || '?'"
            :size="38"
          />
          <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-semibold text-slate-800">
              {{ session.studentName || 'Chưa có học viên' }}
            </p>
          </div>
        </div>

        <dl class="space-y-0.5">
          <div
            v-for="row in rows"
            :key="row.label"
            class="flex items-center gap-3 rounded-lg px-1 py-2"
          >
            <dt class="flex w-28 shrink-0 items-center gap-2 text-xs font-medium text-slate-400">
              <AppIcon
                :name="row.icon"
                :size="15"
              />
              {{ row.label }}
            </dt>
            <dd class="min-w-0 flex-1 text-sm text-slate-700">
              {{ row.value }}
            </dd>
          </div>
        </dl>

        <div
          v-if="session.topic"
          class="mt-4"
        >
          <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-400">
            Chủ đề
          </p>
          <p class="text-sm text-slate-700">
            {{ session.topic }}
          </p>
        </div>

        <div
          v-if="session.notes"
          class="mt-4"
        >
          <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-400">
            Ghi chú
          </p>
          <p class="whitespace-pre-line text-sm text-slate-600">
            {{ session.notes }}
          </p>
        </div>

        <a
          :href="session.detailUrl"
          class="mt-5 inline-flex items-center gap-1.5 text-sm font-medium text-brand hover:underline"
        >
          <AppIcon
            name="link"
            :size="15"
          />
          Mở trang chi tiết buổi học
        </a>
      </div>

      <!-- Actions -->
      <footer
        v-if="canManage"
        class="border-t border-slate-100 px-5 py-3"
      >
        <div class="grid grid-cols-2 gap-2">
          <button
            type="button"
            class="btn-ghost h-9 justify-center gap-1.5 text-sm"
            :disabled="busy"
            @click="emit('edit', session)"
          >
            <AppIcon
              name="edit"
              :size="15"
            />
            Sửa
          </button>
          <button
            type="button"
            class="btn-ghost h-9 justify-center gap-1.5 text-sm"
            :disabled="busy"
            @click="emit('reschedule', session)"
          >
            <AppIcon
              name="refresh"
              :size="15"
            />
            Đổi lịch
          </button>
          <button
            type="button"
            class="inline-flex h-9 items-center justify-center gap-1.5 rounded-btn bg-emerald-600 text-sm font-medium text-white transition hover:bg-emerald-700 disabled:opacity-50"
            :disabled="busy || isClosed"
            @click="emit('status', 'completed')"
          >
            <AppIcon
              name="check-circle"
              :size="15"
            />
            Hoàn thành
          </button>
          <button
            type="button"
            class="inline-flex h-9 items-center justify-center gap-1.5 rounded-btn border border-rose-200 text-sm font-medium text-rose-600 transition hover:bg-rose-50 disabled:opacity-50"
            :disabled="busy || session.status === 'cancelled'"
            @click="emit('status', 'cancelled')"
          >
            <AppIcon
              name="close"
              :size="15"
            />
            Hủy buổi
          </button>
        </div>
      </footer>
    </template>
  </Drawer>
</template>
