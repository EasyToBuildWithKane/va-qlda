<script setup>
import { Link } from '@inertiajs/vue3';
import Avatar from '@/shared/ui/Avatar.vue';
import AppIcon from '@/Components/AppIcon.vue';
import ProgressRing from './ProgressRing.vue';
import { tailwindToHex } from '../composables/useChartTheme.js';

defineProps({
    people: { type: Array, default: () => [] },
});

const gradeTone = {
    S: 'bg-brand/10 text-brand',
    A: 'bg-emerald-50 text-emerald-600',
    B: 'bg-sky-50 text-sky-600',
    C: 'bg-amber-50 text-amber-600',
    D: 'bg-rose-50 text-rose-600',
};

function scoreColor(score) {
    if (score >= 80) return tailwindToHex('emerald');
    if (score >= 50) return tailwindToHex('brand');
    return tailwindToHex('rose');
}
</script>

<template>
  <div class="overflow-x-auto">
    <table
      v-if="people.length"
      class="w-full min-w-[680px] border-collapse text-sm"
    >
      <thead>
        <tr class="border-b border-slate-200 text-left text-[11px] uppercase tracking-wide text-slate-400">
          <th class="px-2 py-2 font-semibold">
            #
          </th>
          <th class="px-2 py-2 font-semibold">
            Thành viên
          </th>
          <th class="px-2 py-2 text-center font-semibold">
            Giao
          </th>
          <th class="px-2 py-2 text-center font-semibold">
            Xong
          </th>
          <th class="px-2 py-2 text-center font-semibold">
            Đúng hạn
          </th>
          <th class="px-2 py-2 text-center font-semibold">
            SP
          </th>
          <th class="px-2 py-2 text-center font-semibold">
            Giờ
          </th>
          <th class="px-2 py-2 text-center font-semibold">
            Điểm
          </th>
          <th class="px-2 py-2 text-center font-semibold" />
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="(p, i) in people"
          :key="p.id"
          class="border-b border-slate-100 transition-colors hover:bg-slate-50/70"
        >
          <td class="px-2 py-2 text-center">
            <span
              class="inline-grid h-6 w-6 place-items-center rounded-full text-[11px] font-bold"
              :class="i < 3 ? 'bg-brand text-white' : 'bg-slate-100 text-slate-500'"
            >{{ i + 1 }}</span>
          </td>
          <td class="px-2 py-2">
            <div class="flex items-center gap-2.5">
              <Avatar
                :name="p.name"
                :src="p.avatar"
                :size="30"
              />
              <div class="min-w-0">
                <p class="truncate font-medium text-slate-800">
                  {{ p.name }}
                </p>
                <p
                  v-if="p.role"
                  class="truncate text-[11px] text-slate-400"
                >
                  {{ p.role }}
                </p>
              </div>
            </div>
          </td>
          <td class="px-2 py-2 text-center tabular-nums text-slate-600">
            {{ p.committed }}
          </td>
          <td class="px-2 py-2 text-center tabular-nums font-medium text-slate-800">
            {{ p.done }}
          </td>
          <td class="px-2 py-2 text-center tabular-nums">
            <span :class="p.onTimeRate >= 80 ? 'text-emerald-600' : p.onTimeRate >= 50 ? 'text-amber-600' : 'text-rose-600'">
              {{ p.onTimeRate }}%
            </span>
          </td>
          <td class="px-2 py-2 text-center tabular-nums text-slate-600">
            {{ p.storyPoints }}
          </td>
          <td class="px-2 py-2 text-center tabular-nums text-slate-600">
            {{ p.hoursLogged }}
          </td>
          <td class="px-2 py-2">
            <div class="flex items-center justify-center gap-2">
              <ProgressRing
                :value="p.score"
                :size="34"
                :stroke="4"
                :color="scoreColor(p.score)"
                :show-label="false"
              >
                <span class="text-[11px] font-bold tabular-nums text-slate-700">{{ p.score }}</span>
              </ProgressRing>
              <span
                class="rounded-md px-1.5 py-0.5 text-[11px] font-bold"
                :class="gradeTone[p.grade]"
              >{{ p.grade }}</span>
            </div>
          </td>
          <td class="px-2 py-2 text-center">
            <Link
              :href="`/performance/audit?member=${p.id}`"
              class="inline-flex items-center gap-1 text-[11px] font-medium text-brand hover:underline"
            >
              Audit
              <AppIcon
                name="chevron-right"
                :size="13"
              />
            </Link>
          </td>
        </tr>
      </tbody>
    </table>
    <p
      v-else
      class="py-6 text-center text-sm text-slate-400"
    >
      Không có nhân sự trong phạm vi đang chọn
    </p>
  </div>
</template>
