<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import { formatMoneyShort, expiryLabel, expiryTone } from '../composables/useContractFormat.js';

defineProps({
    // tree: [{ type:'vendor', id, name, code, count, annualCost, categories:[{id,name,contracts,annualCost}], looseContracts:[] }]
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
</script>

<template>
  <div class="space-y-2">
    <div
      v-for="vendor in tree"
      :key="`v-${vendor.id ?? 'none'}`"
      class="overflow-hidden rounded-card border border-slate-200 bg-white"
    >
      <!-- Vendor row -->
      <button
        type="button"
        class="flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-slate-50"
        @click="toggle(`v-${vendor.id ?? 'none'}`)"
      >
        <AppIcon
          :name="isOpen(`v-${vendor.id ?? 'none'}`) ? 'chevron-down' : 'chevron-right'"
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
          <span class="text-xs text-slate-400">{{ vendor.count }} hợp đồng</span>
        </span>
        <span class="shrink-0 text-sm font-semibold text-slate-700">{{ formatMoneyShort(vendor.annualCost) }}<span class="text-xs font-normal text-slate-400">/năm</span></span>
      </button>

      <!-- Vendor children -->
      <div
        v-if="isOpen(`v-${vendor.id ?? 'none'}`)"
        class="border-t border-slate-100 px-3 py-2"
      >
        <!-- Categories -->
        <div
          v-for="cat in vendor.categories"
          :key="`c-${cat.id}`"
          class="mb-1"
        >
          <button
            type="button"
            class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left hover:bg-slate-50"
            @click="toggle(`c-${cat.id}`)"
          >
            <AppIcon
              :name="isOpen(`c-${cat.id}`) ? 'chevron-down' : 'chevron-right'"
              :size="14"
              class="shrink-0 text-slate-300"
            />
            <AppIcon
              name="documents"
              :size="14"
              class="shrink-0 text-slate-400"
            />
            <span class="flex-1 truncate text-sm font-medium text-slate-600">{{ cat.name }}</span>
            <span class="text-xs text-slate-400">{{ cat.contracts.length }}</span>
          </button>

          <ul
            v-if="isOpen(`c-${cat.id}`)"
            class="ml-7 space-y-0.5 border-l border-slate-100 pl-2"
          >
            <li
              v-for="c in cat.contracts"
              :key="c.id"
            >
              <Link
                :href="`/contracts/${c.id}`"
                class="flex items-center gap-2 rounded-md px-2 py-1.5 hover:bg-brand/5"
              >
                <AppIcon
                  name="contract"
                  :size="13"
                  class="shrink-0 text-slate-300"
                />
                <span class="min-w-0 flex-1 truncate text-sm text-slate-700">{{ c.name }}</span>
                <Badge
                  :label="c.status.label"
                  :color="c.status.color"
                />
                <span
                  class="shrink-0 text-xs"
                  :class="expiryTextClass(c.days_until_expiry)"
                >{{ expiryLabel(c.days_until_expiry) }}</span>
                <span class="shrink-0 text-xs font-medium text-slate-500">{{ formatMoneyShort(c.annual_cost) }}</span>
              </Link>
            </li>
          </ul>
        </div>

        <!-- Loose contracts (no category) -->
        <ul class="space-y-0.5">
          <li
            v-for="c in vendor.looseContracts"
            :key="c.id"
          >
            <Link
              :href="`/contracts/${c.id}`"
              class="flex items-center gap-2 rounded-md px-2 py-1.5 hover:bg-brand/5"
            >
              <AppIcon
                name="contract"
                :size="13"
                class="ml-5 shrink-0 text-slate-300"
              />
              <span class="min-w-0 flex-1 truncate text-sm text-slate-700">{{ c.name }}</span>
              <Badge
                :label="c.status.label"
                :color="c.status.color"
              />
              <span class="shrink-0 text-xs font-medium text-slate-500">{{ formatMoneyShort(c.annual_cost) }}</span>
            </Link>
          </li>
        </ul>
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
