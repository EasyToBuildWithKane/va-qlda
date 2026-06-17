<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import ContractRowActions from '@/modules/contract/components/ContractRowActions.vue';
import DatagridToolbarActionButton from '@/shared/ui/DatagridToolbarActionButton.vue';
import { displayOrEmpty } from '@/shared/utils/emptyDisplay.js';
import { formatMoneyShort, expiryLabel, expiryTone } from '../composables/useContractFormat.js';

const props = defineProps({
    tree: { type: Array, default: () => [] },
});

const emit = defineEmits(['edit', 'delete']);

const COLLAPSE_STORAGE_KEY = 'va-qlda.contracts.collapsed-vendors.v1';

function loadCollapsedGroups() {
    try {
        const raw = localStorage.getItem(COLLAPSE_STORAGE_KEY);
        if (raw) return new Set(JSON.parse(raw));
    } catch {
        /* ignore */
    }
    return new Set();
}

const collapsedGroups = ref(loadCollapsedGroups());

function vendorKey(vendor) {
    return vendor.id != null ? `v-${vendor.id}` : 'v-none';
}

function persistCollapsedGroups() {
    localStorage.setItem(COLLAPSE_STORAGE_KEY, JSON.stringify([...collapsedGroups.value]));
}

function isGroupExpanded(key) {
    return !collapsedGroups.value.has(key);
}

function toggleGroup(key) {
    const next = new Set(collapsedGroups.value);
    if (next.has(key)) next.delete(key);
    else next.add(key);
    collapsedGroups.value = next;
    persistCollapsedGroups();
}

const allGroupsExpanded = computed(() => (
    props.tree.length > 0
    && props.tree.every((v) => isGroupExpanded(vendorKey(v)))
));

function toggleAllGroups() {
    if (allGroupsExpanded.value) {
        collapsedGroups.value = new Set(props.tree.map((v) => vendorKey(v)));
    } else {
        collapsedGroups.value = new Set();
    }
    persistCollapsedGroups();
}

const EXPIRY_TEXT = {
    slate: 'text-slate-400',
    sky: 'text-sky-600',
    emerald: 'text-emerald-600',
    amber: 'text-amber-600',
    rose: 'text-rose-600',
};
const expiryTextClass = (days) => EXPIRY_TEXT[expiryTone(days)] ?? EXPIRY_TEXT.slate;

function roleLabel(c) {
    return c.root_contract_id == null ? 'Gốc' : 'Gia hạn / phụ lục';
}

function roleClass(c) {
    return c.root_contract_id == null
        ? 'bg-emerald-50 text-emerald-700'
        : 'bg-sky-50 text-sky-700';
}

function openDetail(c) {
    router.visit(`/contracts/${c.id}`);
}

function categoryName(c) {
    return displayOrEmpty(c.category?.name, 'Chưa phân nhóm');
}
</script>

<template>
  <div>
    <div
      v-if="tree.length"
      class="mb-3 flex justify-end"
    >
      <DatagridToolbarActionButton
        icon="chevron-down"
        :title="allGroupsExpanded ? 'Thu gọn tất cả nhóm NCC' : 'Mở tất cả nhóm NCC'"
        @click="toggleAllGroups"
      >
        <span class="hidden sm:inline">{{ allGroupsExpanded ? 'Thu nhóm' : 'Mở nhóm' }}</span>
        <span class="sm:hidden">{{ allGroupsExpanded ? 'Thu' : 'Mở' }}</span>
      </DatagridToolbarActionButton>
    </div>

    <div
      v-if="tree.length"
      class="overflow-x-auto rounded-lg border border-slate-200"
    >
      <table class="w-full min-w-[52rem] border-collapse text-sm">
        <thead>
          <tr class="border-b border-slate-200 bg-slate-50/90 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
            <th
              class="w-8 px-1 py-2.5"
              aria-hidden="true"
            />
            <th class="px-2 py-2.5 text-left">
              Mã
            </th>
            <th class="px-2 py-2.5 text-left">
              Hợp đồng
            </th>
            <th class="px-2 py-2.5 text-left">
              Nhóm dịch vụ
            </th>
            <th class="px-2 py-2.5 text-left">
              Loại
            </th>
            <th class="px-2 py-2.5 text-left">
              Trạng thái
            </th>
            <th class="px-2 py-2.5 text-left">
              Hết hạn
            </th>
            <th class="px-2 py-2.5 text-right">
              Chi phí / năm
            </th>
            <th
              class="w-11 px-1 py-2.5 text-center"
              aria-label="Chi tiết"
            >
              <span class="sr-only">Chi tiết</span>
            </th>
            <th
              class="w-11 px-1 py-2.5 text-center"
              aria-label="Thao tác"
            >
              <span class="sr-only">Thao tác</span>
            </th>
          </tr>
        </thead>
        <tbody>
          <template
            v-for="vendor in tree"
            :key="vendorKey(vendor)"
          >
            <tr
              class="cursor-pointer border-y border-slate-200 bg-slate-100/70 transition hover:bg-slate-100"
              @click="toggleGroup(vendorKey(vendor))"
            >
              <td class="px-1 py-2 text-center align-middle">
                <AppIcon
                  name="chevron-down"
                  :size="15"
                  class="inline-block text-slate-500 transition-transform"
                  :class="isGroupExpanded(vendorKey(vendor)) ? '' : '-rotate-90'"
                />
              </td>
              <td
                colspan="9"
                class="px-2 py-2 align-middle"
              >
                <div class="flex flex-wrap items-center gap-2">
                  <span class="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-brand/10 text-brand">
                    <AppIcon
                      name="vendor"
                      :size="14"
                    />
                  </span>
                  <span class="min-w-0 flex-1 break-words text-sm font-semibold text-slate-800">{{ vendor.name }}</span>
                  <span class="shrink-0 text-xs text-slate-500">{{ formatMoneyShort(vendor.annualCost) }}/năm</span>
                  <span class="shrink-0 rounded-full bg-white px-2 py-0.5 text-[10px] font-semibold tabular-nums text-slate-600 ring-1 ring-slate-200/90">
                    {{ vendor.count }} HĐ
                  </span>
                </div>
              </td>
            </tr>

            <template v-if="isGroupExpanded(vendorKey(vendor))">
              <tr
                v-if="!vendor.items.length"
                class="border-b border-slate-100 bg-white"
              >
                <td class="px-1 py-3" />
                <td
                  colspan="9"
                  class="px-2 py-3 text-center text-xs text-slate-400"
                >
                  Chưa có hợp đồng thuộc nhà cung cấp này.
                </td>
              </tr>
              <tr
                v-for="c in vendor.items"
                :key="c.id"
                class="border-b border-slate-100 bg-white transition hover:bg-brand/[0.03]"
              >
                <td class="px-1 py-2 align-middle">
                  <span
                    class="mx-auto block h-6 w-1 rounded-full bg-slate-200/80"
                    aria-hidden="true"
                  />
                </td>
                <td class="px-2 py-2.5 align-top">
                  <span class="font-mono text-xs font-semibold text-brand">{{ c.code }}</span>
                </td>
                <td class="max-w-[14rem] px-2 py-2.5 align-top">
                  <button
                    type="button"
                    class="block w-full break-words text-left text-sm font-medium leading-snug text-slate-800 underline-offset-2 hover:text-brand hover:underline"
                    title="Xem chi tiết"
                    @click.stop="openDetail(c)"
                  >
                    {{ c.name }}
                  </button>
                  <Link
                    v-if="c.attachments_count"
                    :href="`/contracts/${c.id}`"
                    class="mt-0.5 inline-flex items-center gap-0.5 text-[11px] text-slate-400 hover:text-brand"
                    title="Tài liệu đính kèm"
                    @click.stop
                  >
                    <AppIcon
                      name="file"
                      :size="12"
                    />{{ c.attachments_count }} hồ sơ
                  </Link>
                </td>
                <td class="px-2 py-2.5 align-top text-xs text-slate-600">
                  {{ categoryName(c) }}
                </td>
                <td class="px-2 py-2.5 align-top">
                  <span
                    class="rounded px-1.5 py-0.5 text-[10px] font-medium"
                    :class="roleClass(c)"
                  >{{ roleLabel(c) }}</span>
                </td>
                <td class="px-2 py-2.5 align-top">
                  <Badge
                    :label="c.status.label"
                    :color="c.status.color"
                  />
                </td>
                <td
                  class="px-2 py-2.5 align-top text-xs"
                  :class="expiryTextClass(c.days_until_expiry)"
                >
                  {{ expiryLabel(c.days_until_expiry) }}
                </td>
                <td class="px-2 py-2.5 align-top text-right text-xs font-medium tabular-nums text-slate-600">
                  {{ formatMoneyShort(c.annual_cost_resolved ?? c.annual_cost) }}
                </td>
                <td class="px-1 py-2 align-top">
                  <button
                    type="button"
                    class="mx-auto grid h-8 w-8 place-items-center rounded-lg border border-transparent text-slate-400 transition hover:border-slate-200 hover:bg-white hover:text-brand"
                    title="Xem chi tiết"
                    @click.stop="openDetail(c)"
                  >
                    <AppIcon
                      name="eye"
                      :size="16"
                    />
                  </button>
                </td>
                <td class="px-1 py-2 align-top">
                  <ContractRowActions
                    :contract="c"
                    @detail="openDetail"
                    @edit="emit('edit', $event)"
                    @delete="emit('delete', $event)"
                  />
                </td>
              </tr>
            </template>
          </template>
        </tbody>
      </table>
    </div>

    <p
      v-else
      class="rounded-card border border-dashed border-slate-200 py-10 text-center text-sm text-slate-400"
    >
      Chưa có hợp đồng nào phù hợp bộ lọc. Hãy tạo hợp đồng hoặc nhập từ Excel.
    </p>
  </div>
</template>
