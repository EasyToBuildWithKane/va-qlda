<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import { date } from '@/composables/useFormat';
import { EMPTY_LABELS } from '@/shared/utils/emptyDisplay.js';

const props = defineProps({
    activities: { type: Array, default: () => [] },
});

const EVENT_DOT = {
    created: 'bg-emerald-500',
    updated: 'bg-sky-500',
    deleted: 'bg-rose-500',
    renewed: 'bg-violet-500',
    vendor_review_created: 'bg-amber-500',
    vendor_review_updated: 'bg-amber-400',
    vendor_review_deleted: 'bg-rose-400',
    attachment: 'bg-slate-500',
    attachment_removed: 'bg-slate-400',
    status_synced: 'bg-slate-300',
    finance: 'bg-brand',
};

const items = computed(() =>
    (props.activities ?? []).filter((a) => a && a.id != null),
);

function dotClass(event) {
    if (!event) return 'bg-slate-400';
    const key = String(event);
    if (EVENT_DOT[key]) return EVENT_DOT[key];
    if (key.startsWith('vendor_review_')) return 'bg-amber-500';
    if (key.startsWith('finance')) return 'bg-brand';

    return 'bg-slate-400';
}

function actorLabel(actor) {
    return actor?.name ?? 'Hệ thống';
}

function whenLabel(at) {
    return at ? date(at) : EMPTY_LABELS.period;
}

function scoreHint(meta) {
    const score = meta?.total_score;
    if (score == null) return '';
    return ` · Điểm TB ${score}/10`;
}
</script>

<template>
  <div class="card overflow-hidden">
    <div class="border-b border-slate-100 px-5 py-3">
      <h3 class="font-display text-sm font-semibold text-slate-800">
        Nhật ký thao tác
      </h3>
      <p class="mt-0.5 text-xs text-slate-500">
        Tạo/sửa hợp đồng, gia hạn, đánh giá NCC, hồ sơ đính kèm và thay đổi tài chính.
      </p>
    </div>

    <ul
      v-if="items.length"
      class="divide-y divide-slate-100 px-5 py-2"
    >
      <li
        v-for="ev in items"
        :key="ev.id"
        class="flex gap-3 py-3"
      >
        <span
          class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full ring-2 ring-white"
          :class="dotClass(ev.event)"
          aria-hidden="true"
        />
        <div class="min-w-0 flex-1">
          <p class="text-sm text-slate-800">
            {{ ev.description }}
            <span
              v-if="ev.meta?.total_score != null"
              class="font-medium text-slate-600"
            >{{ scoreHint(ev.meta) }}</span>
          </p>
          <p class="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs text-slate-500">
            <span>{{ whenLabel(ev.at) }}</span>
            <span class="text-slate-300">·</span>
            <span>{{ actorLabel(ev.actor) }}</span>
          </p>
        </div>
      </li>
    </ul>

    <div
      v-else
      class="px-5 py-14 text-center"
    >
      <AppIcon
        name="timeline"
        :size="32"
        class="mx-auto mb-3 text-slate-300"
      />
      <p class="text-sm font-medium text-slate-600">
        Chưa có nhật ký thao tác
      </p>
      <p class="mt-1 text-xs text-slate-500">
        Các thay đổi sau khi triển khai bản cập nhật sẽ hiển thị tại đây. Hành động trước đó có thể chỉ thấy trên trang Audit (admin).
      </p>
    </div>
  </div>
</template>
