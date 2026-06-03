<script setup>
import { computed } from 'vue';
import { formatVndDisplay } from '@/modules/aiAccount/utils/formatVnd';

const props = defineProps({
    amount: { type: [Number, String], default: 0 },
    /** Ghép sau primary, vd. "/ tháng" */
    suffix: { type: String, default: '' },
    /** Một dòng: 1.000.000 VNĐ (1 triệu VNĐ) */
    inline: { type: Boolean, default: false },
    /** Ẩn dòng đọc ngắn (chỉ số có dấu chấm) */
    compact: { type: Boolean, default: false },
});

const display = computed(() => formatVndDisplay(props.amount));
</script>

<template>
  <span
    v-if="inline"
    class="text-slate-700"
  >
    {{ display.full }}{{ suffix }}
  </span>
  <span
    v-else
    class="inline-block min-w-0"
  >
    <span class="font-medium tabular-nums text-slate-800">
      {{ display.primary }}<span
        v-if="suffix"
        class="font-normal text-slate-600"
      >{{ suffix }}</span>
    </span>
    <span
      v-if="!compact && display.secondary"
      class="mt-0.5 block text-xs leading-snug text-slate-500"
    >
      {{ display.secondary }}
    </span>
  </span>
</template>
