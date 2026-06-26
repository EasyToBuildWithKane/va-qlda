<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import { displayOrEmpty } from '@/shared/utils/emptyDisplay';

const props = defineProps({
    members: { type: Array, default: () => [] },
    teamOpenTotal: { type: Number, default: 0 },
});

const emit = defineEmits(['select', 'quick-view']);

function shareOfOpen(open) {
    const total = props.teamOpenTotal;
    if (!total || !open) return 0;
    return Math.round((open / total) * 100);
}

const sortedMembers = computed(() =>
    [...props.members].sort((a, b) => {
        const riskA = (a.overdue ?? 0) * 100 + (a.dueToday ?? 0) * 10 + (a.open ?? 0);
        const riskB = (b.overdue ?? 0) * 100 + (b.dueToday ?? 0) * 10 + (b.open ?? 0);
        if (riskB !== riskA) return riskB - riskA;
        return (a.name ?? '').localeCompare(b.name ?? '', 'vi');
    }),
);
</script>

<template>
  <div class="overflow-x-auto">
    <table class="w-full min-w-[52rem] text-left text-sm">
      <thead>
        <tr class="border-b border-slate-100 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
          <th class="px-5 py-3 font-semibold">
            Thành viên
          </th>
          <th class="px-3 py-3 font-semibold">
            Nhóm tổ chức
          </th>
          <th class="px-3 py-3 font-semibold">
            Phòng ban
          </th>
          <th class="px-3 py-3 font-semibold">
            Vai trò
          </th>
          <th class="px-3 py-3 text-right font-semibold tabular-nums">
            Việc mở
          </th>
          <th class="px-3 py-3 text-right font-semibold tabular-nums">
            Quá hạn
          </th>
          <th class="px-3 py-3 text-right font-semibold tabular-nums">
            Hôm nay
          </th>
          <th class="px-3 py-3 text-right font-semibold tabular-nums">
            Sắp tới
          </th>
          <th class="px-3 py-3 text-right font-semibold tabular-nums">
            Đang làm
          </th>
          <th class="px-3 py-3 text-right font-semibold tabular-nums">
            Chưa có hạn
          </th>
          <th class="px-3 py-3 text-right font-semibold tabular-nums">
            Tải nhóm
          </th>
          <th class="px-5 py-3 text-right font-semibold">
            Thao tác
          </th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <tr v-if="sortedMembers.length === 0">
          <td
            colspan="12"
            class="px-5 py-12 text-center text-sm text-slate-500"
          >
            <AppIcon
              name="people"
              :size="28"
              class="mx-auto mb-2 text-slate-300"
            />
            Không có thành viên khớp bộ lọc.
          </td>
        </tr>
        <tr
          v-for="m in sortedMembers"
          :key="m.id"
          class="group transition-colors hover:bg-slate-50/80"
          :class="m.overdue > 0 ? 'border-l-[3px] border-l-rose-500' : (m.dueToday > 0 ? 'border-l-[3px] border-l-amber-400' : 'border-l-[3px] border-l-transparent')"
        >
          <td class="px-5 py-3">
            <div class="flex min-w-0 items-center gap-3">
              <Avatar
                :name="m.name"
                :src="m.avatar_path"
                :size="36"
              />
              <div class="min-w-0">
                <p class="truncate font-semibold text-slate-800">
                  {{ m.name }}
                </p>
                <p
                  v-if="m.overdue > 0"
                  class="text-[11px] font-medium text-rose-600"
                >
                  Có việc quá hạn
                </p>
                <p
                  v-else-if="m.dueToday > 0"
                  class="text-[11px] font-medium text-amber-700"
                >
                  Có việc đến hạn hôm nay
                </p>
                <p
                  v-else-if="m.open === 0"
                  class="text-[11px] text-emerald-600"
                >
                  Không còn việc mở
                </p>
              </div>
            </div>
          </td>
          <td class="max-w-[9rem] px-3 py-3 text-[13px] text-slate-600">
            <span class="line-clamp-2">{{ displayOrEmpty(m.org_team_name ?? m.org_unit_name, 'Chưa gán đơn vị') }}</span>
          </td>
          <td class="max-w-[9rem] px-3 py-3 text-[13px] text-slate-600">
            <span class="line-clamp-2">
              {{
                (m.departments?.[0]?.name)
                  ?? m.org_section_title
                  ?? displayOrEmpty(m.group_department, 'Chưa gán đơn vị')
              }}
            </span>
          </td>
          <td class="max-w-[10rem] px-3 py-3 text-slate-600">
            <span class="line-clamp-2 text-[13px]">
              {{ displayOrEmpty(m.role_title, 'Chưa cập nhật') }}
            </span>
          </td>
          <td class="px-3 py-3 text-right tabular-nums font-medium text-slate-800">
            {{ m.open ?? 0 }}
          </td>
          <td class="px-3 py-3 text-right tabular-nums">
            <span
              class="font-medium"
              :class="m.overdue > 0 ? 'text-rose-600' : 'text-slate-400'"
            >{{ m.overdue ?? 0 }}</span>
          </td>
          <td class="px-3 py-3 text-right tabular-nums">
            <span
              class="font-medium"
              :class="m.dueToday > 0 ? 'text-amber-700' : 'text-slate-400'"
            >{{ m.dueToday ?? 0 }}</span>
          </td>
          <td class="px-3 py-3 text-right tabular-nums text-slate-600">
            {{ m.upcoming ?? 0 }}
          </td>
          <td class="px-3 py-3 text-right tabular-nums text-sky-700">
            {{ m.inProgress ?? 0 }}
          </td>
          <td class="px-3 py-3 text-right tabular-nums text-slate-500">
            {{ m.noDue ?? 0 }}
          </td>
          <td class="px-3 py-3 text-right">
            <div class="inline-flex min-w-[4.5rem] flex-col items-end gap-1">
              <span class="text-xs font-semibold tabular-nums text-slate-600">{{ shareOfOpen(m.open) }}%</span>
              <div class="h-1.5 w-16 overflow-hidden rounded-full bg-slate-100">
                <div
                  class="h-full rounded-full bg-brand/70 transition-all"
                  :style="{ width: `${Math.min(100, shareOfOpen(m.open))}%` }"
                />
              </div>
            </div>
          </td>
          <td class="px-5 py-3 text-right">
            <div class="inline-flex items-center gap-1">
              <button
                type="button"
                class="inline-flex h-9 items-center gap-1.5 rounded-lg px-2.5 text-xs font-medium text-slate-500 transition hover:bg-slate-100 hover:text-brand"
                title="Xem nhanh trong cửa sổ"
                @click="emit('quick-view', m)"
              >
                <AppIcon
                  name="eye"
                  :size="15"
                />
                Xem nhanh
              </button>
              <button
                type="button"
                class="btn-ghost inline-flex h-9 items-center gap-1.5 px-2.5 text-xs font-medium opacity-90 group-hover:opacity-100"
                title="Mở trang đầy đủ"
                @click="emit('select', m.id)"
              >
                Xem việc
                <AppIcon
                  name="chevron-right"
                  :size="14"
                />
              </button>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
