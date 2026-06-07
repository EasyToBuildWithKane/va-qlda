<script setup>
import { computed } from 'vue';
import Drawer from '@/Components/Ui/Drawer.vue';
import AppIcon from '@/Components/AppIcon.vue';
import {
    formatTimelineDetail,
    formatWorkflowAt,
    sortTimelineAscending,
    timelineEventIcon,
    timelinePhaseStyles,
} from '@/modules/aiAccount/utils/proposalWorkflowFormat';

const props = defineProps({
    show: { type: Boolean, default: false },
    row: { type: Object, default: null },
});

const emit = defineEmits(['close']);

const drawerTitle = computed(() => {
    if (!props.row?.proposal_code) return 'Tiến trình xử lý';
    return `Tiến trình · ${props.row.proposal_code}`;
});

const sortedEvents = computed(() =>
    sortTimelineAscending(props.row?.workflow_timeline ?? []),
);

function phaseLabel(phase) {
    if (phase === 'pdx') return 'PĐX';
    if (phase === 'dntt') return 'ĐNTT';
    if (phase === 'payment') return 'Thanh toán';
    return '';
}
</script>

<template>
  <Drawer
    :show="show"
    :title="drawerTitle"
    width="max-w-md"
    flush
    @close="emit('close')"
  >
    <div
      v-if="row"
      class="flex min-h-0 flex-1 flex-col"
    >
      <div class="border-b border-slate-100 bg-gradient-to-b from-brand/[0.04] to-white px-5 py-4">
        <p
          v-if="row.tool_name"
          class="text-sm font-medium text-slate-800 line-clamp-2"
        >
          {{ row.tool_name }}
        </p>
        <p
          v-if="row.proposer_name"
          class="mt-1 text-xs text-slate-500"
        >
          Người đề xuất: {{ row.proposer_name }}
        </p>

        <div class="mt-4 grid grid-cols-2 gap-2.5">
          <div class="rounded-xl border border-brand/15 bg-white px-3 py-2.5 shadow-sm">
            <p class="text-[10px] font-bold uppercase tracking-wide text-brand/80">
              Phiếu đề xuất
            </p>
            <p class="mt-1.5 text-sm font-semibold text-slate-800">
              {{ row.status_label }}
            </p>
            <p
              v-if="row.reviewed_at"
              class="mt-0.5 text-[11px] text-slate-500"
            >
              {{ formatWorkflowAt(row.reviewed_at) }}
            </p>
          </div>

          <div
            class="rounded-xl border px-3 py-2.5 shadow-sm"
            :class="row.payment_request
              ? 'border-emerald-200/90 bg-white'
              : 'border-dashed border-slate-200 bg-slate-50/80'"
          >
            <p class="text-[10px] font-bold uppercase tracking-wide text-emerald-700/90">
              Đề nghị thanh toán
            </p>
            <p
              v-if="row.payment_request"
              class="mt-1.5 text-sm font-semibold text-slate-800"
            >
              {{ row.payment_request.status_label }}
            </p>
            <p
              v-else
              class="mt-1.5 text-sm font-medium text-slate-400"
            >
              Chưa tạo
            </p>
            <p
              v-if="row.payment_request?.payment_request_code"
              class="mt-0.5 font-mono text-[11px] text-slate-500"
            >
              {{ row.payment_request.payment_request_code }}
            </p>
          </div>
        </div>
      </div>

      <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
        <h3 class="text-[11px] font-bold uppercase tracking-wide text-slate-400">
          Lịch sử xử lý
        </h3>

        <ol
          v-if="sortedEvents.length"
          class="relative mt-4 space-y-0"
        >
          <li
            v-for="(ev, index) in sortedEvents"
            :key="ev.id"
            class="relative flex gap-3 pb-6 last:pb-0"
          >
            <div
              v-if="index < sortedEvents.length - 1"
              class="absolute left-[15px] top-8 bottom-0 w-px bg-slate-200"
              aria-hidden="true"
            />

            <div
              class="relative z-[1] grid h-8 w-8 shrink-0 place-items-center rounded-full ring-4 ring-white"
              :class="timelinePhaseStyles(ev.phase).ring"
            >
              <span
                class="grid h-full w-full place-items-center rounded-full text-white"
                :class="timelinePhaseStyles(ev.phase).dot"
              >
                <AppIcon
                  :name="timelineEventIcon(ev.id)"
                  :size="14"
                  :stroke-width="2"
                />
              </span>
            </div>

            <div class="min-w-0 flex-1 pt-0.5">
              <div class="flex flex-wrap items-center gap-2">
                <span class="text-sm font-semibold text-slate-800">{{ ev.title }}</span>
                <span
                  class="inline-flex rounded-md border px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                  :class="timelinePhaseStyles(ev.phase).chip"
                >
                  {{ phaseLabel(ev.phase) }}
                </span>
              </div>
              <time
                class="mt-1 block text-xs tabular-nums text-slate-500"
                :datetime="ev.at"
              >
                {{ formatWorkflowAt(ev.at) }}
              </time>
              <p
                v-if="formatTimelineDetail(ev)"
                class="mt-1.5 text-sm text-slate-600"
              >
                {{ formatTimelineDetail(ev) }}
              </p>
            </div>
          </li>
        </ol>

        <div
          v-else
          class="mt-6 rounded-xl border border-dashed border-slate-200 bg-slate-50/60 px-4 py-8 text-center"
        >
          <AppIcon
            name="report-history"
            :size="28"
            class="mx-auto text-slate-300"
          />
          <p class="mt-2 text-sm text-slate-500">
            Chưa có sự kiện trên hồ sơ này.
          </p>
        </div>
      </div>
    </div>
  </Drawer>
</template>
