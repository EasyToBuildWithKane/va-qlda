<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import { date as formatDate, datetime } from '@/composables/useFormat';
import { displayOrEmpty, EMPTY_LABELS } from '@/shared/utils/emptyDisplay';

const props = defineProps({
    members: { type: Array, default: () => [] },
    totals: {
        type: Object,
        default: () => ({ reports: 0, members: 0 }),
    },
    activeEmployeeId: { type: Number, default: null },
    searchQuery: { type: String, default: '' },
});

function dateRangeLabel(member) {
    const oldest = member.oldest_date;
    const newest = member.newest_date;
    if (!oldest && !newest) return EMPTY_LABELS.generic;
    if (oldest === newest) return formatDate(oldest);
    return `${formatDate(oldest)} – ${formatDate(newest)}`;
}

const filteredMembers = computed(() => {
    const q = props.searchQuery.trim().toLowerCase();
    if (!q) return props.members;
    return props.members.filter((m) =>
        (m.name ?? '').toLowerCase().includes(q) ||
        (m.role_title ?? '').toLowerCase().includes(q),
    );
});

function navigate(employeeId) {
    const params = {};
    if (employeeId != null) {
        params.employee_id = employeeId;
    }
    router.get(route('daily-reports.review'), params, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
}

function isActive(id) {
    return props.activeEmployeeId === id;
}
</script>

<template>
  <section
    class="card min-w-0 overflow-hidden"
    aria-label="Danh sách thành viên chờ duyệt báo cáo"
  >
    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 px-4 py-3 sm:px-5">
      <div class="min-w-0">
        <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-brand/80">
          Hàng chờ
        </p>
        <h2 class="font-display text-base font-semibold text-slate-800">
          Thành viên chờ duyệt
        </h2>
        <p class="mt-0.5 text-xs text-slate-500">
          {{ totals.members }} thành viên · {{ totals.reports }} báo cáo đang chờ
        </p>
      </div>
      <button
        v-if="activeEmployeeId"
        type="button"
        class="btn-ghost h-9 shrink-0 gap-1.5 text-xs"
        @click="navigate(null)"
      >
        <AppIcon
          name="x"
          :size="14"
        />
        Bỏ lọc thành viên
      </button>
    </div>

    <div
      v-if="members.length === 0"
      class="px-5 py-10 text-center text-sm text-slate-400"
    >
      Không có thành viên nào trong hàng chờ.
    </div>

    <div
      v-else-if="filteredMembers.length === 0"
      class="px-5 py-10 text-center text-sm text-slate-400"
    >
      Không có thành viên khớp từ khoá tìm kiếm.
    </div>

    <div
      v-else
      class="overflow-x-auto"
    >
      <table class="w-full min-w-[640px] text-left text-sm">
        <thead>
          <tr class="border-b border-slate-100 bg-slate-50/80 text-xs uppercase tracking-wide text-slate-500">
            <th
              scope="col"
              class="px-4 py-2.5 font-semibold sm:px-5"
            >
              Thành viên
            </th>
            <th
              scope="col"
              class="px-4 py-2.5 font-semibold sm:px-5"
            >
              Ngày báo cáo
            </th>
            <th
              scope="col"
              class="px-4 py-2.5 font-semibold sm:px-5"
            >
              Nộp lúc
            </th>
            <th
              scope="col"
              class="px-4 py-2.5 text-right font-semibold sm:px-5"
            >
              Chờ duyệt
            </th>
            <th
              scope="col"
              class="w-28 px-4 py-2.5 sm:px-5"
            >
              <span class="sr-only">Thao tác</span>
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr
            v-for="member in filteredMembers"
            :key="member.employee_id"
            class="transition-colors"
            :class="isActive(member.employee_id) ? 'bg-brand-50/60' : 'hover:bg-slate-50/80'"
          >
            <td class="px-4 py-3 sm:px-5">
              <div class="flex min-w-0 items-center gap-2.5">
                <Avatar
                  :name="member.name"
                  :src="member.avatar_path"
                  size="sm"
                />
                <div class="min-w-0">
                  <p class="truncate font-medium text-slate-800">
                    {{ member.name }}
                  </p>
                  <p class="truncate text-xs text-slate-400">
                    {{ displayOrEmpty(member.role_title, EMPTY_LABELS.role) }}
                  </p>
                </div>
              </div>
            </td>
            <td class="whitespace-nowrap px-4 py-3 text-slate-600 sm:px-5">
              {{ dateRangeLabel(member) }}
            </td>
            <td class="whitespace-nowrap px-4 py-3 text-slate-600 sm:px-5">
              {{
                member.latest_submitted_at
                  ? datetime(member.latest_submitted_at)
                  : EMPTY_LABELS.notUpdated
              }}
            </td>
            <td class="px-4 py-3 text-right sm:px-5">
              <span
                class="inline-flex min-w-[2rem] items-center justify-center rounded-full px-2.5 py-0.5 text-xs font-semibold tabular-nums"
                :class="member.pending_count > 1 ? 'bg-amber-100 text-amber-800' : 'bg-sky-100 text-sky-800'"
              >
                {{ member.pending_count }}
              </span>
            </td>
            <td class="px-4 py-3 sm:px-5">
              <button
                type="button"
                class="btn-ghost h-9 w-full justify-center gap-1 text-xs sm:w-auto"
                :class="isActive(member.employee_id) ? 'ring-1 ring-brand/30' : ''"
                :aria-pressed="isActive(member.employee_id)"
                @click="navigate(isActive(member.employee_id) ? null : member.employee_id)"
              >
                {{ isActive(member.employee_id) ? 'Đang xem' : 'Xem báo cáo' }}
                <AppIcon
                  name="chevron-right"
                  :size="14"
                />
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>
