<script setup>
import { computed, ref } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import RichContentBody from '@/shared/ui/RichContentBody.vue';
import { dateAtMidnight } from '@/composables/useFormat';
import ProgressRing from './ProgressRing.vue';
import KanbanSnapshot from './KanbanSnapshot.vue';
import { tailwindToHex } from '../composables/useChartTheme.js';

const props = defineProps({
    week: { type: Object, required: true },
});

const open = ref(false);

// Tuần không có công việc cam kết → không có hiệu suất khách quan để chấm.
// Hiển thị trạng thái trung tính thay vì điểm/xếp loại gây hiểu nhầm.
const hasCommitment = computed(() => (props.week.summary.committed ?? 0) > 0);

const rateColor = computed(() => {
    const r = props.week.summary.commitmentRate;
    if (r >= 80) return tailwindToHex('emerald');
    if (r >= 50) return tailwindToHex('amber');
    return tailwindToHex('rose');
});

function fmtDate(d) {
    return dateAtMidnight(d);
}

const gradeTone = {
    S: 'bg-brand/10 text-brand',
    A: 'bg-emerald-50 text-emerald-600',
    B: 'bg-sky-50 text-sky-600',
    C: 'bg-amber-50 text-amber-600',
    D: 'bg-rose-50 text-rose-600',
};
</script>

<template>
  <div class="card overflow-hidden">
    <!-- Header -->
    <button
      type="button"
      class="flex w-full items-center gap-4 p-4 text-left transition-colors hover:bg-slate-50/70"
      @click="open = !open"
    >
      <ProgressRing
        v-if="hasCommitment"
        :value="week.summary.commitmentRate"
        :size="56"
        :stroke="5"
        :color="rateColor"
      />
      <span
        v-else
        class="inline-flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-slate-50 ring-2 ring-slate-200/80"
        aria-hidden="true"
      >
        <AppIcon
          name="calendar-clock"
          :size="26"
          class="text-slate-400"
        />
      </span>

      <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2">
          <h3 class="font-display text-sm font-semibold text-slate-800">
            {{ week.label }}
          </h3>
          <span class="text-[11px] text-slate-400">{{ week.range }}</span>
          <span
            v-if="hasCommitment"
            class="rounded-md px-1.5 py-0.5 text-[11px] font-bold"
            :class="gradeTone[week.grade]"
          >{{ week.grade }}</span>
          <span
            v-else
            class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[11px] font-medium text-slate-400"
          >Chưa có cam kết</span>
        </div>
        <p
          v-if="hasCommitment"
          class="mt-0.5 text-[12px] text-slate-500"
        >
          Hoàn thành <span class="font-semibold text-slate-700">{{ week.summary.done }}</span>/{{ week.summary.committed }} cam kết
          · Hiệu suất <span class="font-semibold text-slate-700">{{ week.scores.performance }}%</span>
          · Đúng hạn {{ week.scores.onTime }}%
        </p>
        <p
          v-else
          class="mt-0.5 text-[12px] text-slate-400"
        >
          Tuần này chưa có công việc cam kết — chỉ ghi nhận báo cáo ngày.
        </p>
      </div>

      <AppIcon
        :name="open ? 'chevron-down' : 'chevron-right'"
        :size="18"
        class="shrink-0 text-slate-400"
      />
    </button>

    <!-- Plan vs result -->
    <div class="border-t border-slate-100 px-4 py-3">
      <div class="grid gap-3 sm:grid-cols-2">
        <div>
          <p class="mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-slate-400">
            Kế hoạch / Cam kết
          </p>
          <ul
            v-if="week.plan.length"
            class="space-y-1"
          >
            <li
              v-for="t in week.plan"
              :key="t.id"
              class="flex items-start gap-2 text-[13px]"
            >
              <span
                class="mt-0.5 shrink-0"
                :class="t.result === 'done' ? 'text-emerald-500' : 'text-rose-400'"
              >
                <AppIcon
                  :name="t.result === 'done' ? 'check-circle' : 'close'"
                  :size="15"
                />
              </span>
              <span
                class="min-w-0"
                :class="t.result === 'done' ? 'text-slate-700' : 'text-slate-500'"
              >
                <span class="truncate">{{ t.title }}</span>
                <span
                  v-if="t.project"
                  class="ml-1 text-[11px] text-slate-400"
                >· {{ t.project.name }}</span>
              </span>
            </li>
          </ul>
          <p
            v-else
            class="text-[12px] leading-relaxed text-slate-400"
          >
            Không có công việc cam kết. Cam kết được lấy từ
            <span class="font-medium text-slate-500">Công việc (Task)</span>
            được giao cho thành viên, có hạn hoặc bắt đầu trong tuần.
          </p>
        </div>

        <div>
          <p class="mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-slate-400">
            Ghi chú báo cáo ngày
          </p>
          <ul
            v-if="week.reports.length"
            class="space-y-2"
          >
            <li
              v-for="(r, i) in week.reports"
              :key="i"
              class="rounded-md bg-slate-50 px-2.5 py-2"
            >
              <p class="mb-1 text-[11px] font-semibold text-slate-500">
                {{ fmtDate(r.date) }}
              </p>
              <RichContentBody
                :content="r.goals || r.plan"
                empty-text="Không có nội dung."
                html-class="prose prose-sm max-w-none text-[12px] leading-snug text-slate-600 [&_p]:my-0.5 [&_ul]:my-1 [&_ol]:my-1 [&_li]:my-0"
                plain-class="whitespace-pre-line text-[12px] leading-snug text-slate-600"
                empty-class="text-[12px] text-slate-400"
              />
            </li>
          </ul>
          <p
            v-else
            class="text-[12px] text-slate-400"
          >
            Không có báo cáo ngày trong tuần.
          </p>
        </div>
      </div>

      <!-- Drill-down: Kanban thực tế -->
      <div
        v-if="open"
        class="mt-3 border-t border-slate-100 pt-3"
      >
        <p class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400">
          Toàn bộ công việc thực tế (Kanban snapshot)
        </p>
        <KanbanSnapshot :columns="week.kanban" />
      </div>
    </div>
  </div>
</template>
