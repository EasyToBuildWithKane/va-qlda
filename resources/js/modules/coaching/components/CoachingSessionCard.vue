<script setup>
import { Link } from '@inertiajs/vue3';
import Badge from '@/shared/ui/Badge.vue';
import CoachingSessionRowActions from '@/modules/coaching/components/CoachingSessionRowActions.vue';
import {
    sessionStatusColor,
    displaySessionDate,
    displaySessionHours,
    displaySessionTimeRange,
    displaySessionTitle,
    displayMaterialsCount,
    displayAssignmentsCount,
} from '@/composables/coachingSessionDisplay';

defineProps({
    session: { type: Object, required: true },
    statusOptions: { type: Array, default: () => [] },
    statusUpdating: { type: Boolean, default: false },
});

const emit = defineEmits(['update-status', 'detail', 'delete']);
</script>

<template>
  <article
    class="flex h-full w-full min-h-0 flex-col rounded-card border border-slate-200 bg-white shadow-sm transition hover:border-brand/20 hover:shadow-md"
  >
    <div class="border-b border-slate-100 px-3.5 py-2.5">
      <div class="flex items-start justify-between gap-2">
        <div class="min-w-0">
          <p class="font-mono text-[10px] font-semibold uppercase tracking-wide text-brand">
            Buổi {{ session.session_number }}
          </p>
          <h3 class="mt-0.5 line-clamp-2 text-sm font-semibold text-slate-800">
            {{ session.title }}
          </h3>
        </div>
        <CoachingSessionRowActions
          :session="session"
          @detail="emit('detail', session)"
          @delete="emit('delete', session)"
        />
      </div>
    </div>
    <dl class="flex flex-1 flex-col gap-2 px-3.5 py-3 text-xs text-slate-600">
      <div class="flex justify-between gap-2">
        <dt class="text-slate-400">
          Ngày
        </dt>
        <dd class="text-right font-medium text-slate-700">
          {{ displaySessionDate(session.date) }}
        </dd>
      </div>
      <div class="flex justify-between gap-2">
        <dt class="text-slate-400">
          Giờ học
        </dt>
        <dd class="text-right">
          {{ displaySessionTimeRange(session) }}
        </dd>
      </div>
      <div class="flex justify-between gap-2">
        <dt class="text-slate-400">
          Tổng giờ
        </dt>
        <dd class="text-right">
          {{ displaySessionHours(session.total_hours) }}
        </dd>
      </div>
      <div class="flex flex-col gap-1">
        <dt class="text-slate-400">
          Trạng thái
        </dt>
        <dd>
          <select
            v-if="session.can?.update"
            :value="session.status?.value"
            class="input h-8 w-full px-2 py-0 text-xs"
            :disabled="statusUpdating"
            @change="emit('update-status', session, $event.target.value)"
          >
            <option
              v-for="o in statusOptions"
              :key="o.value"
              :value="o.value"
            >
              {{ o.label }}
            </option>
          </select>
          <Badge
            v-else-if="session.status"
            :label="session.status.label"
            :color="sessionStatusColor(session.status.value)"
          />
        </dd>
      </div>
      <div>
        <dt class="text-slate-400">
          Tên buổi
        </dt>
        <dd class="mt-0.5 line-clamp-2 text-slate-700">
          {{ displaySessionTitle(session) }}
        </dd>
      </div>
      <div class="flex justify-between gap-2">
        <dt class="text-slate-400">
          Tài liệu
        </dt>
        <dd>{{ displayMaterialsCount(session) }}</dd>
      </div>
      <div class="flex justify-between gap-2">
        <dt class="text-slate-400">
          Bài tập
        </dt>
        <dd>{{ displayAssignmentsCount(session) }}</dd>
      </div>
    </dl>
    <div class="mt-auto border-t border-slate-100 px-3.5 py-2">
      <Link
        :href="route('coaching.sessions.show', { session: session.id })"
        class="text-xs font-medium text-brand hover:underline"
      >
        Mở chi tiết buổi học
      </Link>
    </div>
  </article>
</template>
