<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import { formatMoneyShort, expiryLabel, expiryTone } from '../composables/useContractFormat.js';

defineProps({
    // tree: vendor → categories[] → sets[] → contracts[]
    tree: { type: Array, default: () => [] },
});

const EXPIRY_TEXT = {
    slate: 'text-slate-400',
    sky: 'text-sky-600',
    emerald: 'text-emerald-600',
    amber: 'text-amber-600',
    rose: 'text-rose-600',
};
const expiryTextClass = (days) => EXPIRY_TEXT[expiryTone(days)] ?? EXPIRY_TEXT.slate;

const collapsed = ref(new Set());
function toggle(key) {
    const next = new Set(collapsed.value);
    next.has(key) ? next.delete(key) : next.add(key);
    collapsed.value = next;
}
const isOpen = (key) => !collapsed.value.has(key);

function roleLabel(c) {
    return c.root_contract_id == null ? 'Gốc' : 'Gia hạn / phụ lục';
}

function vendorKey(vendor) {
    return `v-${vendor.id ?? 'none'}`;
}
function categoryKey(vendor, category) {
    return `c-${vendorKey(vendor)}-${category.key}`;
}
function setKey(vendor, category, set) {
    return `s-${vendorKey(vendor)}-${category.key}-${set.key}`;
}
</script>

<template>
  <div class="space-y-2">
    <div
      v-for="vendor in tree"
      :key="vendorKey(vendor)"
      class="overflow-hidden rounded-card border border-slate-200 bg-white"
    >
      <button
        type="button"
        class="flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-slate-50"
        @click="toggle(vendorKey(vendor))"
      >
        <AppIcon
          :name="isOpen(vendorKey(vendor)) ? 'chevron-down' : 'chevron-right'"
          :size="16"
          class="shrink-0 text-slate-400"
        />
        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-brand/10 text-brand">
          <AppIcon
            name="vendor"
            :size="16"
          />
        </span>
        <span class="min-w-0 flex-1">
          <span class="block truncate text-sm font-semibold text-slate-800">{{ vendor.name }}</span>
          <span class="text-xs text-slate-400">{{ vendor.categoryCount }} nhóm · {{ vendor.setCount }} bộ · {{ vendor.count }} hợp đồng</span>
        </span>
        <span class="shrink-0 text-sm font-semibold text-slate-700">{{ formatMoneyShort(vendor.annualCost) }}<span class="text-xs font-normal text-slate-400">/năm</span></span>
      </button>

      <div
        v-if="isOpen(vendorKey(vendor))"
        class="border-t border-slate-100 px-3 py-2"
      >
        <div
          v-for="category in vendor.categories"
          :key="categoryKey(vendor, category)"
          class="mb-2 rounded-md border border-slate-100"
        >
          <button
            type="button"
            class="flex w-full items-center gap-2 rounded-md px-2 py-2 text-left hover:bg-slate-50"
            @click="toggle(categoryKey(vendor, category))"
          >
            <AppIcon
              :name="isOpen(categoryKey(vendor, category)) ? 'chevron-down' : 'chevron-right'"
              :size="14"
              class="shrink-0 text-slate-300"
            />
            <AppIcon
              name="portfolio"
              :size="14"
              class="shrink-0 text-brand/70"
            />
            <span class="min-w-0 flex-1 truncate text-sm font-medium text-slate-700">{{ category.name }}</span>
            <span class="shrink-0 text-xs text-slate-400">{{ category.setCount }} bộ · {{ category.count }} HĐ</span>
            <span class="shrink-0 text-xs font-medium text-slate-500">{{ formatMoneyShort(category.annualCost) }}</span>
          </button>

          <div
            v-if="isOpen(categoryKey(vendor, category))"
            class="ml-4 space-y-1 border-l border-slate-100 pl-2 pb-1"
          >
            <div
              v-for="set in category.sets"
              :key="setKey(vendor, category, set)"
              class="rounded-md border border-slate-50"
            >
              <button
                type="button"
                class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left hover:bg-slate-50"
                @click="toggle(setKey(vendor, category, set))"
              >
                <AppIcon
                  :name="isOpen(setKey(vendor, category, set)) ? 'chevron-down' : 'chevron-right'"
                  :size="12"
                  class="shrink-0 text-slate-300"
                />
                <AppIcon
                  name="documents"
                  :size="12"
                  class="shrink-0 text-slate-400"
                />
                <span class="min-w-0 flex-1 truncate text-xs font-medium text-slate-600">{{ set.name }}</span>
                <span class="shrink-0 rounded-full bg-brand/10 px-1.5 py-0.5 text-[10px] font-semibold text-brand">{{ set.signingCount }} lần ký</span>
              </button>

              <ul
                v-if="isOpen(setKey(vendor, category, set))"
                class="ml-5 space-y-0.5 border-l border-slate-100 pl-2 pb-1"
              >
                <li
                  v-for="c in set.contracts"
                  :key="c.id"
                >
                  <div class="flex items-center gap-2 rounded-md px-2 py-1.5 hover:bg-brand/5">
                    <span
                      class="shrink-0 rounded px-1.5 py-0.5 text-[10px] font-medium"
                      :class="c.root_contract_id == null ? 'bg-emerald-50 text-emerald-700' : 'bg-sky-50 text-sky-700'"
                    >{{ roleLabel(c) }}</span>
                    <Link
                      :href="`/contracts/${c.id}`"
                      class="min-w-0 flex-1 truncate text-sm text-slate-700 hover:text-brand"
                    >
                      {{ c.name }}
                    </Link>
                    <Link
                      v-if="c.attachments_count"
                      :href="`/contracts/${c.id}`"
                      class="inline-flex shrink-0 items-center gap-0.5 text-[11px] text-slate-400 hover:text-brand"
                      title="Tài liệu đính kèm"
                    >
                      <AppIcon
                        name="file"
                        :size="12"
                      />{{ c.attachments_count }}
                    </Link>
                    <Badge
                      :label="c.status.label"
                      :color="c.status.color"
                    />
                    <span
                      class="shrink-0 text-xs"
                      :class="expiryTextClass(c.days_until_expiry)"
                    >{{ expiryLabel(c.days_until_expiry) }}</span>
                    <span class="shrink-0 text-xs font-medium text-slate-500">{{ formatMoneyShort(c.annual_cost_resolved ?? c.annual_cost) }}</span>
                  </div>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>

    <p
      v-if="!tree.length"
      class="rounded-card border border-dashed border-slate-200 py-10 text-center text-sm text-slate-400"
    >
      Chưa có hợp đồng nào. Hãy tạo hợp đồng hoặc nhập từ Excel.
    </p>
  </div>
</template>
