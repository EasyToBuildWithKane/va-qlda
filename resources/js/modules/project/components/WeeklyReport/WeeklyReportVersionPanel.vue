<script setup>
import { computed, ref } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    versions: { type: Array, default: () => [] },
});

const open = ref(false);

const statusLabel = {
    draft: 'Nháp', generated: 'Đã tạo', edited: 'Đã sửa',
    submitted: 'Chờ duyệt', approved: 'Đã duyệt', rejected: 'Bị trả lại',
};

const items = computed(() => props.versions ?? []);

function fmt(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleString('vi-VN', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
  <section
    v-if="items.length"
    class="rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900"
  >
    <button
      type="button"
      class="flex w-full items-center justify-between p-4"
      @click="open = !open"
    >
      <span class="flex items-center gap-2">
        <AppIcon
          name="report-history"
          :size="15"
          class="text-slate-500"
        />
        <span class="font-display text-sm font-semibold text-slate-700 dark:text-slate-200">
          Lịch sử phiên bản ({{ items.length }})
        </span>
      </span>
      <AppIcon
        :name="open ? 'chevron-down' : 'chevron-right'"
        :size="16"
        class="text-slate-400"
      />
    </button>

    <ul
      v-if="open"
      class="space-y-2 border-t border-slate-100 p-4 dark:border-slate-800"
    >
      <li
        v-for="v in items"
        :key="v.version_number"
        class="flex items-start gap-3 text-sm"
      >
        <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-slate-100 text-[11px] font-semibold text-slate-600 dark:bg-slate-800">
          v{{ v.version_number }}
        </span>
        <div class="min-w-0 flex-1">
          <p class="text-slate-700 dark:text-slate-200">
            <span class="font-medium">{{ statusLabel[v.status] || v.status }}</span>
            <span
              v-if="v.note"
              class="text-slate-500"
            > — {{ v.note }}</span>
          </p>
          <p class="text-xs text-slate-400">
            {{ v.created_by || '—' }} · {{ fmt(v.created_at) }}
          </p>
        </div>
      </li>
    </ul>
  </section>
</template>
