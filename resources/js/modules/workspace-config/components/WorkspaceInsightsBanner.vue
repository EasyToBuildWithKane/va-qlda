<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    insights: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
});

const emit = defineEmits(['action']);

const levelClass = {
    warning: 'border-amber-200/90 bg-gradient-to-r from-amber-50/90 to-white',
    info: 'border-sky-200/80 bg-gradient-to-r from-sky-50/80 to-white',
    success: 'border-emerald-200/80 bg-gradient-to-r from-emerald-50/80 to-white',
};

const levelIcon = {
    warning: 'amber',
    info: 'sky',
    success: 'emerald',
};

const iconTone = {
    amber: 'text-amber-700 bg-amber-100/80 ring-amber-200/80',
    sky: 'text-sky-700 bg-sky-100/80 ring-sky-200/80',
    emerald: 'text-emerald-700 bg-emerald-100/80 ring-emerald-200/80',
};

const visible = computed(() => props.insights ?? []);

function actionLabel(insight) {
    if (insight.action === 'bulk_ensure' && props.canManage) {
        return `Kích hoạt ${insight.department_codes?.length || 0} phòng ban`;
    }
    if (insight.action === 'filter_empty_active') return 'Lọc đang dùng trống';
    if (insight.action === 'filter_partial') return 'Lọc đang cấu hình';
    return 'Xem';
}

function onAction(insight) {
    emit('action', insight);
}
</script>

<template>
  <div
    v-if="visible.length"
    class="mb-5 space-y-2"
    aria-label="Gợi ý vận hành workspace"
  >
    <article
      v-for="insight in visible"
      :key="insight.code"
      class="flex flex-col gap-3 rounded-card border px-4 py-3 shadow-sm sm:flex-row sm:items-center sm:justify-between"
      :class="levelClass[insight.level] ?? levelClass.info"
    >
      <div class="flex min-w-0 items-start gap-3">
        <span
          class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl ring-1"
          :class="iconTone[levelIcon[insight.level]] ?? iconTone.sky"
        >
          <AppIcon
            name="system-config"
            :size="16"
          />
        </span>
        <div class="min-w-0">
          <h3 class="font-display text-sm font-semibold text-slate-800">
            {{ insight.title }}
          </h3>
          <p class="mt-0.5 text-sm text-slate-600">
            {{ insight.message }}
          </p>
        </div>
      </div>
      <button
        type="button"
        class="btn-primary inline-flex h-9 shrink-0 items-center gap-1.5 self-start px-3 text-xs sm:self-center"
        @click="onAction(insight)"
      >
        <AppIcon
          :name="insight.action === 'bulk_ensure' ? 'plus' : 'filter'"
          :size="15"
        />
        {{ actionLabel(insight) }}
      </button>
    </article>
  </div>
</template>
