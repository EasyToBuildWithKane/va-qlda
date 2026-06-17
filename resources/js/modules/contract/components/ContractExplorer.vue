<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import ContractRowActions from '@/modules/contract/components/ContractRowActions.vue';
import { displayOrEmpty } from '@/shared/utils/emptyDisplay.js';
import { formatMoneyShort, formatDate, expiryTone } from '../composables/useContractFormat.js';
import { CONTRACT_EXPLORER_COLUMNS, CONTRACT_EXPLORER_COLUMN_KEYS } from '../config/explorerColumns.js';

const props = defineProps({
    tree: { type: Array, default: () => [] },
    isColVisible: { type: Function, required: true },
});

const emit = defineEmits(['edit', 'delete']);

const COLLAPSE_STORAGE_KEY = 'va-qlda.contracts.collapsed-vendors.v2';
const COLLAPSE_CATEGORIES_KEY = 'va-qlda.contracts.collapsed-categories.v1';
const COLLAPSE_SETS_KEY = 'va-qlda.contracts.collapsed-sets.v1';

function loadCollapsed(key) {
    try {
        const raw = localStorage.getItem(key);
        if (raw) return new Set(JSON.parse(raw));
    } catch {
        /* ignore */
    }
    return new Set();
}

const collapsedVendors = ref(loadCollapsed(COLLAPSE_STORAGE_KEY));
const collapsedCategories = ref(loadCollapsed(COLLAPSE_CATEGORIES_KEY));
const collapsedSets = ref(loadCollapsed(COLLAPSE_SETS_KEY));

function vendorKey(vendor) {
    return vendor.id != null ? `v-${vendor.id}` : 'v-none';
}

function setCollapseKey(vendor, set) {
    return `${vendorKey(vendor)}-s-${set.key}`;
}

function categoryCollapseKey(vendor, category) {
    return `${vendorKey(vendor)}-c-${category.key}`;
}

function persist(key, setRef) {
    localStorage.setItem(key, JSON.stringify([...setRef.value]));
}

function isVendorOpen(vendor) {
    return !collapsedVendors.value.has(vendorKey(vendor));
}

function isSetOpen(vendor, set) {
    return !collapsedSets.value.has(setCollapseKey(vendor, set));
}

function isCategoryOpen(vendor, category) {
    return !collapsedCategories.value.has(categoryCollapseKey(vendor, category));
}

function toggleVendor(vendor) {
    const key = vendorKey(vendor);
    const next = new Set(collapsedVendors.value);
    if (next.has(key)) next.delete(key);
    else next.add(key);
    collapsedVendors.value = next;
    persist(COLLAPSE_STORAGE_KEY, collapsedVendors);
}

function toggleSet(vendor, set) {
    const key = setCollapseKey(vendor, set);
    const next = new Set(collapsedSets.value);
    if (next.has(key)) next.delete(key);
    else next.add(key);
    collapsedSets.value = next;
    persist(COLLAPSE_SETS_KEY, collapsedSets);
}

function toggleCategory(vendor, category) {
    const key = categoryCollapseKey(vendor, category);
    const next = new Set(collapsedCategories.value);
    if (next.has(key)) next.delete(key);
    else next.add(key);
    collapsedCategories.value = next;
    persist(COLLAPSE_CATEGORIES_KEY, collapsedCategories);
}

const visibleColumnKeys = computed(() =>
    CONTRACT_EXPLORER_COLUMN_KEYS.filter((k) => props.isColVisible(k)),
);

const visibleDataColCount = computed(() => visibleColumnKeys.value.length);

const MONEY_KEYS = new Set(['monthly_cost', 'annual_cost', 'lifecycle_cost']);

function thAlign(key) {
    return MONEY_KEYS.has(key) ? 'text-right' : 'text-left';
}

function colLabel(key) {
    return CONTRACT_EXPLORER_COLUMNS.find((c) => c.key === key)?.label ?? key;
}

const EXPIRY_TEXT = {
    slate: 'text-slate-500',
    sky: 'text-sky-600',
    emerald: 'text-emerald-600',
    amber: 'text-amber-600',
    rose: 'text-rose-600',
};
const expiryTextClass = (days) => EXPIRY_TEXT[expiryTone(days)] ?? EXPIRY_TEXT.slate;

function roleLabel(c) {
    return c.root_contract_id == null ? 'Hợp đồng gốc' : 'Bản gia hạn / Phụ lục';
}

function roleClass(c) {
    return c.root_contract_id == null
        ? 'text-emerald-700'
        : 'text-sky-700';
}

function openDetail(c) {
    router.visit(`/contracts/${c.id}`);
}

function vendorLabel(vendor, contract) {
    return contract?.vendor?.name ?? vendor.name;
}

function moneyShort(value) {
    const s = formatMoneyShort(value);
    return s.replace(/\s+VND$/i, '');
}

function dateCell(value) {
    if (!value) return displayOrEmpty(null, 'Chưa cập nhật');
    const formatted = formatDate(value);
    return formatted === '—' ? displayOrEmpty(null, 'Chưa cập nhật') : formatted;
}

function expiryDisplay(days) {
    if (days === null || days === undefined) return 'Không hạn';
    if (days < 0) return `Hết hạn ${Math.abs(days)} ngày`;
    if (days === 0) return 'Hết hạn hôm nay';
    return `${days} ngày`;
}

function ownerName(c) {
    return displayOrEmpty(c.owner?.name, 'Chưa cập nhật');
}
</script>

<template>
  <div>
    <div
      v-if="tree.length"
      class="overflow-x-auto rounded-lg border border-slate-200"
    >
      <table class="w-full min-w-[48rem] border-collapse text-sm">
        <thead>
          <tr class="border-b border-slate-200 bg-slate-50/90 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
            <th
              class="w-8 px-1 py-2.5"
              aria-hidden="true"
            />
            <th
              v-for="key in visibleColumnKeys"
              :key="`th-${key}`"
              class="px-2 py-2.5"
              :class="thAlign(key)"
            >
              {{ colLabel(key) }}
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
              class="cursor-pointer border-y border-slate-200 bg-slate-100/80 transition hover:bg-slate-100"
              @click="toggleVendor(vendor)"
            >
              <td class="px-1 py-2 text-center align-middle">
                <AppIcon
                  name="chevron-down"
                  :size="15"
                  class="inline-block text-brand transition-transform"
                  :class="isVendorOpen(vendor) ? '' : '-rotate-90'"
                />
              </td>
              <td
                :colspan="visibleDataColCount"
                class="px-2 py-2 align-middle"
              >
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                  <span class="text-sm font-semibold text-brand">{{ vendor.name }}</span>
                  <span class="text-xs text-slate-500">{{ vendor.count }} HĐ</span>
                  <span
                    v-if="isColVisible('monthly_cost')"
                    class="text-xs tabular-nums text-slate-600"
                  >
                    CP/tháng: {{ moneyShort(vendor.monthlyCost) }}
                  </span>
                  <span
                    v-if="isColVisible('lifecycle_cost')"
                    class="text-xs tabular-nums text-slate-600"
                  >
                    Tổng: {{ moneyShort(vendor.lifecycleCost) }}
                  </span>
                </div>
              </td>
              <td class="px-1 py-2" />
            </tr>

            <template v-if="isVendorOpen(vendor)">
              <tr
                v-if="!vendor.categories.length"
                class="border-b border-slate-100 bg-white"
              >
                <td />
                <td
                  :colspan="visibleDataColCount"
                  class="px-2 py-3 text-center text-xs text-slate-400"
                >
                  Chưa có hợp đồng thuộc nhà cung cấp này.
                </td>
                <td />
              </tr>

              <template
                v-for="category in vendor.categories"
                :key="`${vendorKey(vendor)}-${category.key}`"
              >
                <tr
                  class="cursor-pointer border-b border-slate-100 bg-sky-50/40 transition hover:bg-sky-50/70"
                  @click="toggleCategory(vendor, category)"
                >
                  <td class="px-1 py-2 text-center align-middle">
                    <AppIcon
                      name="chevron-down"
                      :size="14"
                      class="inline-block text-sky-600/80 transition-transform"
                      :class="isCategoryOpen(vendor, category) ? '' : '-rotate-90'"
                    />
                  </td>
                  <td
                    :colspan="visibleDataColCount"
                    class="px-2 py-2 align-middle pl-4 sm:pl-6"
                  >
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                      <AppIcon
                        name="all-projects"
                        :size="14"
                        class="shrink-0 text-sky-700/80"
                      />
                      <span class="text-sm font-semibold text-slate-800">
                        {{ displayOrEmpty(category.name, 'Chưa phân nhóm') }}
                      </span>
                      <span class="text-slate-500">{{ category.count }} HĐ · {{ category.setCount }} bộ</span>
                      <span
                        v-if="isColVisible('monthly_cost')"
                        class="tabular-nums text-slate-600"
                      >
                        CP/tháng: {{ moneyShort(category.monthlyCost) }}
                      </span>
                      <span
                        v-if="isColVisible('lifecycle_cost')"
                        class="tabular-nums text-slate-600"
                      >
                        Tổng: {{ moneyShort(category.lifecycleCost) }}
                      </span>
                    </div>
                  </td>
                  <td class="px-1 py-2" />
                </tr>

                <template v-if="isCategoryOpen(vendor, category)">
                  <template
                    v-for="set in category.sets"
                    :key="setCollapseKey(vendor, set)"
                  >
                    <tr
                      class="cursor-pointer border-b border-slate-100 bg-white transition hover:bg-slate-50/80"
                      @click="toggleSet(vendor, set)"
                    >
                      <td class="px-1 py-2 text-center align-middle">
                        <AppIcon
                          name="chevron-down"
                          :size="13"
                          class="inline-block text-slate-400 transition-transform"
                          :class="isSetOpen(vendor, set) ? '' : '-rotate-90'"
                        />
                      </td>
                      <td
                        :colspan="visibleDataColCount"
                        class="px-2 py-2 align-middle pl-8 sm:pl-10"
                      >
                        <div class="flex flex-wrap items-center gap-2 text-xs">
                          <AppIcon
                            name="documents"
                            :size="14"
                            class="shrink-0 text-brand/70"
                          />
                          <span class="font-medium text-slate-700">
                            Bộ HĐ: {{ set.code ?? set.name }}
                            <span class="font-normal text-slate-400">({{ set.signingCount }} lần ký)</span>
                          </span>
                          <span
                            v-if="isColVisible('name')"
                            class="max-w-[12rem] truncate font-medium text-slate-600"
                          >{{ set.name }}</span>
                          <span
                            v-if="isColVisible('monthly_cost')"
                            class="ml-auto tabular-nums text-slate-500"
                          >{{ moneyShort(set.monthlyCost) }}/tháng</span>
                          <span
                            v-if="isColVisible('lifecycle_cost')"
                            class="tabular-nums text-slate-500"
                          >{{ moneyShort(set.lifecycleCost) }}</span>
                        </div>
                      </td>
                      <td class="px-1 py-2" />
                    </tr>

                    <template v-if="isSetOpen(vendor, set)">
                      <tr
                        v-for="c in set.contracts"
                        :key="c.id"
                        class="border-b border-slate-50 bg-white transition hover:bg-brand/[0.03]"
                      >
                        <td class="px-1 py-2 align-middle">
                          <span
                            class="ml-2 block h-5 w-3 border-b border-l border-slate-200"
                            aria-hidden="true"
                          />
                        </td>

                        <td
                          v-if="isColVisible('code')"
                          class="px-2 py-2.5 align-top"
                        >
                          <span class="inline-block rounded-md bg-brand/10 px-1.5 py-0.5 font-mono text-[11px] font-semibold text-brand">
                            {{ c.code }}
                          </span>
                          <p
                            v-if="!isColVisible('role')"
                            class="mt-0.5 text-[10px] font-medium"
                            :class="roleClass(c)"
                          >
                            {{ roleLabel(c) }}
                          </p>
                        </td>
                        <td
                          v-if="isColVisible('vendor')"
                          class="px-2 py-2.5 align-top text-xs text-slate-700"
                        >
                          {{ vendorLabel(vendor, c) }}
                        </td>
                        <td
                          v-if="isColVisible('name')"
                          class="max-w-[12rem] px-2 py-2.5 align-top"
                        >
                          <button
                            type="button"
                            class="block w-full break-words text-left text-sm font-medium text-slate-800 underline-offset-2 hover:text-brand hover:underline"
                            @click.stop="openDetail(c)"
                          >
                            {{ c.name }}
                          </button>
                        </td>
                        <td
                          v-if="isColVisible('role')"
                          class="px-2 py-2.5 align-top text-[10px] font-medium"
                          :class="roleClass(c)"
                        >
                          {{ roleLabel(c) }}
                        </td>
                        <td
                          v-if="isColVisible('using_unit')"
                          class="px-2 py-2.5 align-top text-xs text-slate-600"
                        >
                          {{ displayOrEmpty(c.using_unit, 'Chưa gán đơn vị') }}
                        </td>
                        <td
                          v-if="isColVisible('owner')"
                          class="px-2 py-2.5 align-top text-xs text-slate-600"
                        >
                          {{ ownerName(c) }}
                        </td>
                        <td
                          v-if="isColVisible('signed_date')"
                          class="px-2 py-2.5 align-top text-xs text-slate-600"
                        >
                          {{ dateCell(c.signed_date) }}
                        </td>
                        <td
                          v-if="isColVisible('effective_date')"
                          class="px-2 py-2.5 align-top text-xs text-slate-600"
                        >
                          {{ dateCell(c.effective_date) }}
                        </td>
                        <td
                          v-if="isColVisible('expiry_date')"
                          class="px-2 py-2.5 align-top text-xs text-slate-600"
                        >
                          {{ dateCell(c.expiry_date) }}
                        </td>
                        <td
                          v-if="isColVisible('days_remaining')"
                          class="px-2 py-2.5 align-top text-xs font-medium"
                          :class="expiryTextClass(c.days_until_expiry)"
                        >
                          {{ expiryDisplay(c.days_until_expiry) }}
                        </td>
                        <td
                          v-if="isColVisible('monthly_cost')"
                          class="px-2 py-2.5 align-top text-right text-xs tabular-nums text-slate-600"
                        >
                          {{ moneyShort(c.monthly_cost) }}
                        </td>
                        <td
                          v-if="isColVisible('annual_cost')"
                          class="px-2 py-2.5 align-top text-right text-xs tabular-nums text-slate-600"
                        >
                          {{ moneyShort(c.annual_cost_resolved ?? c.annual_cost) }}
                        </td>
                        <td
                          v-if="isColVisible('lifecycle_cost')"
                          class="px-2 py-2.5 align-top text-right text-xs tabular-nums text-slate-600"
                        >
                          {{ moneyShort(c.lifecycle_cost) }}
                        </td>
                        <td
                          v-if="isColVisible('payment_status')"
                          class="px-2 py-2.5 align-top"
                        >
                          <Badge
                            v-if="c.payment_status?.label"
                            :label="c.payment_status.label"
                            :color="c.payment_status.color"
                          />
                          <span
                            v-else
                            class="text-xs text-slate-400"
                          >{{ displayOrEmpty(null, 'Chưa cập nhật') }}</span>
                        </td>
                        <td
                          v-if="isColVisible('billing_cycle')"
                          class="px-2 py-2.5 align-top text-xs text-slate-600"
                        >
                          {{ displayOrEmpty(c.billing_cycle?.label, 'Chưa cập nhật') }}
                        </td>
                        <td
                          v-if="isColVisible('status')"
                          class="px-2 py-2.5 align-top"
                        >
                          <Badge
                            :label="c.status.label"
                            :color="c.status.color"
                          />
                        </td>
                        <td
                          v-if="isColVisible('attachments_count')"
                          class="px-2 py-2.5 align-top text-xs"
                        >
                          <Link
                            v-if="c.attachments_count"
                            :href="`/contracts/${c.id}`"
                            class="inline-flex items-center gap-0.5 text-slate-600 hover:text-brand"
                            @click.stop
                          >
                            <AppIcon
                              name="file"
                              :size="12"
                            />{{ c.attachments_count }}
                          </Link>
                          <span
                            v-else
                            class="text-slate-400"
                          >0</span>
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
                </template>
              </template>
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
