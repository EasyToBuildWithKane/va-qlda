<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import { formatVnd, formatCostCell } from '@/modules/aiAccount/utils/formatVnd';

defineProps({
    groups: { type: Array, default: () => [] },
    expanded: { type: Object, required: true },
    loading: { type: Boolean, default: false },
    colVisible: {
        type: Object,
        default: () => ({
            license: true,
            email: true,
            expiry: true,
            cost: true,
            status: true,
        }),
    },
});

const emit = defineEmits(['toggle', 'edit', 'delete', 'renew']);

function statusLabel(row) {
    if (row.status === 'active') return 'Hoạt động';
    if (row.status === 'expiring_soon') return `⚠ ${row.days_until_expiry} ngày`;
    if (row.status === 'expired') return 'Hết hạn';
    if (row.status === 'cancelled') return 'Đã huỷ';
    return row.status_label ?? row.status;
}

function warningInline(g) {
    const parts = [];
    const expiring = (g.accounts ?? []).filter((a) => a.status === 'expiring_soon').length;
    const expired = (g.accounts ?? []).filter((a) => a.status === 'expired').length;
    if (expiring) parts.push(`${expiring} sắp hết hạn`);
    if (expired) parts.push(`${expired} hết hạn`);
    return parts.length ? `⚠ ${parts.join(' · ')}` : '';
}
</script>

<template>
  <div>
    <div
      v-if="loading"
      class="px-5 py-12 text-center text-sm text-slate-500"
    >
      Đang tải danh sách…
    </div>

    <div
      v-else-if="groups.length === 0"
      class="px-5 py-12 text-center text-sm text-slate-500"
    >
      Không có tài khoản phù hợp với bộ lọc hoặc từ khóa tìm kiếm.
    </div>

    <div
      v-else
      class="divide-y divide-slate-100"
    >
      <div
        v-for="g in groups"
        :key="g.group"
      >
        <button
          type="button"
          class="flex w-full items-center gap-3 px-5 py-3.5 text-left transition-colors hover:bg-slate-50/80"
          @click="emit('toggle', g.group)"
        >
          <span
            class="h-2.5 w-2.5 shrink-0 rounded-full"
            :style="{ backgroundColor: g.dot_color }"
          />
          <span class="font-semibold text-slate-800">{{ g.group }}</span>
          <span
            v-if="warningInline(g)"
            class="text-sm text-amber-700"
          >{{ warningInline(g) }}</span>
          <span class="text-sm text-slate-500">· {{ g.total }} tài khoản</span>
          <span class="text-sm font-medium text-slate-700">· {{ formatVnd(g.total_cost_monthly) }}/tháng</span>
          <span class="ml-auto text-slate-400">
            <AppIcon
              name="chevron-down"
              :size="18"
              :class="expanded[g.group] ? 'rotate-180 transition' : 'transition'"
            />
          </span>
        </button>

        <div v-show="expanded[g.group]">
          <div class="overflow-x-auto border-t border-slate-100 bg-white">
            <table class="w-full min-w-[640px] text-left text-sm">
              <thead class="bg-slate-50 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                  <th class="px-5 py-2.5">
                    Công cụ
                  </th>
                  <th
                    v-if="colVisible.license"
                    class="px-5 py-2.5"
                  >
                    License
                  </th>
                  <th
                    v-if="colVisible.email"
                    class="px-5 py-2.5"
                  >
                    Email
                  </th>
                  <th
                    v-if="colVisible.expiry"
                    class="px-5 py-2.5"
                  >
                    Hết hạn
                  </th>
                  <th
                    v-if="colVisible.cost"
                    class="px-5 py-2.5"
                  >
                    Chi phí
                  </th>
                  <th
                    v-if="colVisible.status"
                    class="px-5 py-2.5"
                  >
                    Trạng thái
                  </th>
                  <th class="px-5 py-2.5 text-right">
                    Thao tác
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr
                  v-for="row in g.accounts"
                  :key="row.id"
                  class="hover:bg-slate-50/50"
                >
                  <td class="px-5 py-3 font-medium text-slate-800">
                    {{ row.tool_name }}
                  </td>
                  <td
                    v-if="colVisible.license"
                    class="px-5 py-3 text-slate-600"
                  >
                    {{ row.license_type }}
                  </td>
                  <td
                    v-if="colVisible.email"
                    class="px-5 py-3 text-slate-600"
                  >
                    {{ row.email_registered }}
                  </td>
                  <td
                    v-if="colVisible.expiry"
                    class="px-5 py-3 text-slate-600"
                  >
                    {{ row.expiry_date }}
                  </td>
                  <td
                    v-if="colVisible.cost"
                    class="px-5 py-3 text-slate-600"
                  >
                    {{ formatCostCell(row.cost_amount, row.cost_unit, row.cost_monthly) }}
                  </td>
                  <td
                    v-if="colVisible.status"
                    class="px-5 py-3"
                  >
                    <Badge
                      :label="statusLabel(row)"
                      :color="row.status_color"
                    />
                  </td>
                  <td class="px-5 py-3 text-right whitespace-nowrap">
                    <button
                      v-if="row.can_renew"
                      type="button"
                      class="btn-ghost mr-1 px-2 py-1 text-xs text-brand"
                      @click="emit('renew', row)"
                    >
                      Gia hạn
                    </button>
                    <button
                      type="button"
                      class="btn-ghost mr-1 px-2 py-1 text-xs text-slate-600"
                      @click="emit('edit', row)"
                    >
                      Sửa
                    </button>
                    <button
                      type="button"
                      class="btn-ghost px-2 py-1 text-xs text-rose-600"
                      @click="emit('delete', row)"
                    >
                      Xoá
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
