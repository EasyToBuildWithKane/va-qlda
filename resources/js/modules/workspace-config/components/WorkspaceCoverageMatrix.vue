<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    coverage: {
        type: Object,
        default: () => ({ modules: [], rows: [] }),
    },
});

const emit = defineEmits(['select-department']);

const modules = computed(() => props.coverage?.modules ?? []);
const rows = computed(() => props.coverage?.rows ?? []);

function cellLabel(cell) {
    if (!cell) return 'Chưa cấu hình';
    if (cell.configured) {
        return cell.count != null ? String(cell.count) : 'Có';
    }
    return 'Trống';
}
</script>

<template>
  <section
    class="mb-5 overflow-hidden rounded-card border border-slate-200/80 bg-white shadow-sm"
    aria-label="Ma trận phủ module theo phòng ban"
  >
    <header class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-3">
      <div>
        <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-brand/80">
          Phủ sóng
        </p>
        <h2 class="mt-0.5 font-display text-sm font-semibold text-slate-800">
          Ma trận module × phòng ban
        </h2>
      </div>
      <p class="text-xs text-slate-400">
        {{ rows.length }} phòng ban · {{ modules.length }} module live
      </p>
    </header>

    <div
      v-if="rows.length && modules.length"
      class="overflow-x-auto"
    >
      <table class="min-w-full text-left text-sm">
        <thead class="bg-slate-50/90 text-[11px] uppercase tracking-wide text-slate-500">
          <tr>
            <th class="sticky left-0 z-10 bg-slate-50/95 px-4 py-2.5 font-semibold">
              Phòng ban
            </th>
            <th class="px-3 py-2.5 font-semibold">
              Profile
            </th>
            <th
              v-for="mod in modules"
              :key="mod.key"
              class="px-3 py-2.5 font-semibold"
            >
              {{ mod.label }}
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr
            v-for="row in rows"
            :key="row.department_code"
            class="hover:bg-slate-50/70"
          >
            <td class="sticky left-0 z-10 bg-white px-4 py-2.5">
              <button
                type="button"
                class="text-left"
                @click="emit('select-department', row.department_code)"
              >
                <span class="block font-medium text-slate-800 hover:text-brand">
                  {{ row.department_name }}
                </span>
                <span class="font-mono text-[11px] text-slate-400">
                  {{ row.department_code }}
                </span>
              </button>
            </td>
            <td class="px-3 py-2.5">
              <span class="inline-flex rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-slate-600 ring-1 ring-slate-200/80">
                {{ row.status_label }}
              </span>
            </td>
            <td
              v-for="mod in modules"
              :key="`${row.department_code}-${mod.key}`"
              class="px-3 py-2.5"
            >
              <span
                class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-[11px] font-semibold ring-1"
                :class="row.cells?.[mod.key]?.configured
                  ? 'bg-emerald-50 text-emerald-700 ring-emerald-200/80'
                  : 'bg-slate-50 text-slate-500 ring-slate-200/80'"
              >
                <AppIcon
                  :name="row.cells?.[mod.key]?.configured ? 'done' : 'system-config'"
                  :size="12"
                />
                {{ cellLabel(row.cells?.[mod.key]) }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <p
      v-else
      class="px-4 py-8 text-center text-sm text-slate-500"
    >
      Chưa có dữ liệu ma trận để hiển thị.
    </p>
  </section>
</template>
