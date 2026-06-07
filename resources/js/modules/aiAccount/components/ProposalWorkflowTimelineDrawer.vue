<script setup>
import Drawer from '@/Components/Ui/Drawer.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import { datetime } from '@/composables/useFormat';

defineProps({
    show: { type: Boolean, default: false },
    row: { type: Object, default: null },
});

const emit = defineEmits(['close']);

function phaseLabel(phase) {
    if (phase === 'pdx') return 'PĐX';
    if (phase === 'dntt') return 'ĐNTT';
    if (phase === 'payment') return 'Thanh toán';
    return '';
}

function phaseColor(phase) {
    if (phase === 'pdx') return 'brand';
    if (phase === 'dntt') return 'emerald';
    if (phase === 'payment') return 'blue';
    return 'slate';
}
</script>

<template>
  <Drawer
    :show="show"
    :title="row ? `Tiến trình · ${row.proposal_code ?? ''}` : 'Tiến trình xử lý'"
    width="max-w-md"
    @close="emit('close')"
  >
    <div
      v-if="row"
      class="space-y-4 px-5 py-4"
    >
      <div class="flex flex-wrap items-center gap-2">
        <Badge
          label="PĐX"
          color="brand"
          class="text-[10px]"
        />
        <Badge
          :label="row.status_label"
          :color="row.status_color"
        />
        <template v-if="row.payment_request">
          <span class="text-slate-300">|</span>
          <Badge
            label="ĐNTT"
            color="emerald"
            class="text-[10px]"
          />
          <Badge
            :label="row.payment_request.status_label"
            :color="row.payment_request.status_color"
          />
        </template>
        <span
          v-else
          class="text-xs text-slate-400"
        >Chưa có ĐNTT</span>
      </div>

      <ul
        v-if="row.workflow_timeline?.length"
        class="space-y-3"
      >
        <li
          v-for="ev in row.workflow_timeline"
          :key="ev.id"
          class="flex gap-3"
        >
          <span
            class="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-full bg-slate-100 text-slate-500"
          >
            <AppIcon
              name="report-history"
              :size="14"
            />
          </span>
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <span class="font-medium text-slate-800">{{ ev.title }}</span>
              <Badge
                v-if="phaseLabel(ev.phase)"
                :label="phaseLabel(ev.phase)"
                :color="phaseColor(ev.phase)"
                class="text-[10px]"
              />
            </div>
            <p class="text-xs text-slate-400">
              {{ datetime(ev.at) }}
            </p>
            <p
              v-if="ev.detail"
              class="mt-0.5 text-sm text-slate-600"
            >
              {{ ev.detail }}
            </p>
          </div>
        </li>
      </ul>
      <p
        v-else
        class="text-sm text-slate-500"
      >
        Chưa có sự kiện trên hồ sơ này.
      </p>
    </div>
  </Drawer>
</template>
