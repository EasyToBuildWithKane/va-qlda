<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    report: { type: Object, required: true },
    processing: { type: Boolean, default: false },
    /** Nhúng trên toolbar — không bọc card riêng */
    inline: { type: Boolean, default: false },
});

const emit = defineEmits(['submit', 'approve', 'reject']);

const STEPS = [
    { key: 'draft', label: 'Nháp', color: 'slate' },
    { key: 'generated', label: 'Tạo', color: 'sky' },
    { key: 'edited', label: 'Chỉnh sửa', color: 'violet' },
    { key: 'submitted', label: 'Chờ duyệt', color: 'amber' },
    { key: 'approved', label: 'Đã duyệt', color: 'emerald' },
];

const DOT_CLASS = {
    slate: 'bg-slate-400',
    sky: 'bg-sky-500',
    violet: 'bg-violet-500',
    amber: 'bg-amber-500',
    emerald: 'bg-emerald-500',
    rose: 'bg-rose-500',
};

const statusValue = computed(() => props.report.status?.value);
const statusMeta = computed(() => {
    if (statusValue.value === 'rejected') return { label: 'Bị trả lại', color: 'rose' };
    return STEPS.find((s) => s.key === statusValue.value) ?? { label: 'Nháp', color: 'slate' };
});
const statusDotClass = computed(() => DOT_CLASS[statusMeta.value.color] ?? DOT_CLASS.slate);

const can = computed(() => props.report.can ?? {});
const canSubmit = computed(() => can.value.submit && ['draft', 'generated', 'edited', 'rejected'].includes(statusValue.value));
const canApprove = computed(() => can.value.approve && statusValue.value === 'submitted');

const rejecting = ref(false);
const reason = ref('');
const rejectWrap = ref(null);

function doReject() {
    if (reason.value.trim().length < 3) return;
    emit('reject', reason.value.trim());
    rejecting.value = false;
    reason.value = '';
}

function onRejectClickOutside(e) {
    if (rejectWrap.value && !rejectWrap.value.contains(e.target)) {
        rejecting.value = false;
    }
}

onMounted(() => document.addEventListener('mousedown', onRejectClickOutside));
onBeforeUnmount(() => document.removeEventListener('mousedown', onRejectClickOutside));
</script>

<template>
  <component
    :is="inline ? 'div' : 'section'"
    :class="inline
      ? 'flex min-w-0 shrink-0 items-center gap-2.5'
      : 'flex items-center gap-2.5 rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900'"
  >
    <span class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-700 dark:text-slate-200">
      <span
        class="h-1.5 w-1.5 shrink-0 rounded-full"
        :class="statusDotClass"
        aria-hidden="true"
      />
      {{ statusMeta.label }}
    </span>

    <button
      v-if="canSubmit"
      type="button"
      :disabled="processing"
      class="btn-primary inline-flex h-10 shrink-0 items-center gap-1.5 px-3 py-0 text-xs disabled:opacity-60"
      @click="emit('submit')"
    >
      <AppIcon
        name="send"
        :size="15"
      /> Gửi duyệt
    </button>
    <template v-if="canApprove">
      <div
        ref="rejectWrap"
        class="relative shrink-0"
      >
        <button
          type="button"
          :disabled="processing"
          class="inline-flex h-10 items-center gap-1.5 rounded-btn border border-rose-200 bg-white px-3 text-xs font-medium text-rose-600 hover:bg-rose-50 disabled:opacity-60 dark:border-rose-900 dark:bg-slate-900"
          @click="rejecting = !rejecting"
        >
          <AppIcon
            name="close"
            :size="15"
          /> Trả lại
        </button>
        <div
          v-if="rejecting"
          class="absolute left-0 top-full z-20 mt-1 flex w-80 max-w-[min(20rem,calc(100vw-2rem))] items-center gap-2 rounded-lg border border-slate-200 bg-white p-2 shadow-lg dark:border-slate-700 dark:bg-slate-900"
        >
          <input
            v-model="reason"
            type="text"
            placeholder="Lý do trả lại báo cáo…"
            class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm focus:border-brand focus:ring-1 focus:ring-brand dark:border-slate-600 dark:bg-slate-800"
            @keyup.enter="doReject"
          >
          <button
            type="button"
            :disabled="reason.trim().length < 3 || processing"
            class="shrink-0 rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-rose-700 disabled:opacity-50"
            @click="doReject"
          >
            Xác nhận
          </button>
        </div>
      </div>
      <button
        type="button"
        :disabled="processing"
        class="inline-flex h-10 shrink-0 items-center gap-1.5 rounded-btn bg-emerald-600 px-3 text-xs font-medium text-white hover:bg-emerald-700 disabled:opacity-60"
        @click="emit('approve')"
      >
        <AppIcon
          name="check"
          :size="15"
        /> Duyệt
      </button>
    </template>

    <p
      v-if="statusValue === 'rejected' && report.reject_reason"
      class="max-w-[14rem] truncate text-xs text-rose-600"
      :title="report.reject_reason"
    >
      Lý do: {{ report.reject_reason }}
    </p>
  </component>
</template>
