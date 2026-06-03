<script setup>
import { computed, onMounted, ref, toRef, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { useDialog } from '@/composables/useDialog';
import { useAiAccounts } from '@/modules/aiAccount/composables/useAiAccounts';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import { useAiAccountListUi } from '@/modules/aiAccount/composables/useAiAccountListUi';
import AiAccountBanner from '@/modules/aiAccount/components/AiAccountBanner.vue';
import AiAccountSummaryCards from '@/modules/aiAccount/components/AiAccountSummaryCards.vue';
import AiAccountGroupList from '@/modules/aiAccount/components/AiAccountGroupList.vue';
import AiAccountFormModal from '@/modules/aiAccount/components/AiAccountFormModal.vue';
import AiAccountRenewModal from '@/modules/aiAccount/components/AiAccountRenewModal.vue';
import AiAccountSectionNav from '@/modules/aiAccount/components/AiAccountSectionNav.vue';
import AiAccountCrossLink from '@/modules/aiAccount/components/AiAccountCrossLink.vue';

const props = defineProps({
    can: { type: Object, default: () => ({}) },
    options: { type: Object, required: true },
});

const dialog = useDialog();
const {
    loading,
    groups,
    banner,
    summaryCards,
    search,
    expanded,
    fetchList,
    fetchSummary,
    createAccount,
    updateAccount,
    deleteAccount,
    renewAccount,
    triggerReminder,
    toggleGroup,
} = useAiAccounts();

const {
    filters,
    activeFilterCount,
    displayGroups,
    filteredAccountCount,
    statusCounts,
    groupFilterOptions,
    clearFilters,
    toggleGroupFilter,
    AI_ACCOUNT_COLUMNS,
    AI_ACCOUNT_STATUS_FILTER_OPTS,
    colVisible,
    toggleColumn,
    showFilterDd,
    showColDd,
    filterDdRef,
    colDdRef,
    openFilter,
    openCol,
} = useAiAccountListUi(groups, toRef(props, 'options'));

const formOpen = ref(false);
const renewOpen = ref(false);
const editing = ref(null);
const renewing = ref(null);

const totalCount = computed(() => summaryCards.value?.total_accounts ?? 0);
const proposalPendingCount = ref(0);
const listBadge = computed(() => {
    if (activeFilterCount.value > 0 || search.value.trim()) {
        return filteredAccountCount.value;
    }
    return totalCount.value;
});

let searchTimer = null;
watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => fetchList(), 300);
});

onMounted(async () => {
    await fetchList();
    try {
        const summary = await fetchSummary();
        proposalPendingCount.value = summary.proposal_counts?.pending ?? 0;
    } catch {
        /* optional — cross-link badge */
    }
});

function openCreate() {
    editing.value = null;
    formOpen.value = true;
}

function openEdit(row) {
    editing.value = row;
    formOpen.value = true;
}

async function onFormSubmit(payload) {
    if (editing.value?.id) {
        await updateAccount(editing.value.id, payload);
    } else {
        await createAccount(payload);
    }
    formOpen.value = false;
    editing.value = null;
}

function openRenew(row) {
    renewing.value = row;
    renewOpen.value = true;
}

async function onRenewSubmit(payload) {
    if (!renewing.value?.id) return;
    await renewAccount(renewing.value.id, payload);
    renewOpen.value = false;
    renewing.value = null;
}

async function onDelete(row) {
    const ok = await dialog.confirm({
        title: 'Xoá tài khoản',
        message: `Bạn có chắc muốn xoá ${row.tool_name}?`,
        confirmText: 'Xoá',
        tone: 'danger',
    });
    if (!ok) return;
    await deleteAccount(row.id, row.tool_name);
}

async function clearAllFilters() {
    if (activeFilterCount.value === 0) return;
    const ok = await dialog.confirm({
        title: 'Xoá bộ lọc',
        message: 'Xoá tất cả bộ lọc đang áp dụng?',
        confirmText: 'Xoá lọc',
    });
    if (!ok) return;
    clearFilters();
}

function expandAllGroups() {
    for (const g of displayGroups.value) {
        expanded.value[g.group] = true;
    }
}

function collapseAllGroups() {
    for (const g of displayGroups.value) {
        expanded.value[g.group] = false;
    }
}
</script>

<template>
  <Head title="Quản lý AI · Tài khoản" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Quản lý AI"
        subtitle="Tài khoản đang dùng · liên kết với phiếu đề xuất mua sắm"
        icon="account"
        icon-color="brand"
        :badge="totalCount || null"
      >
        <AiAccountSectionNav
          active="accounts"
          :accounts-badge="totalCount || null"
          :proposals-badge="proposalPendingCount > 0 ? proposalPendingCount : null"
        />
      </PageHeader>
    </template>

    <AiAccountCrossLink
      direction="to-proposals"
      :pending-count="proposalPendingCount"
    />

    <AiAccountSummaryCards :cards="summaryCards" />

    <div class="card overflow-visible">
      <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
        <div class="flex min-w-0 flex-col gap-0.5 sm:flex-row sm:items-center sm:gap-2">
          <div class="flex items-center gap-2">
            <h2 class="font-semibold text-slate-700">
              Danh sách tài khoản
            </h2>
            <span
              class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-brand/10 px-1.5 text-[11px] font-bold text-brand"
            >
              {{ listBadge }}
            </span>
          </div>
          <p
            v-if="activeFilterCount > 0 || search.trim()"
            class="text-xs text-slate-500"
          >
            Đang lọc
            <template v-if="search.trim()">
              · tìm kiếm
            </template>
          </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <button
            type="button"
            class="btn-ghost border border-slate-200 px-2.5 py-1.5 text-xs text-slate-600"
            @click="expandAllGroups"
          >
            Mở tất cả nhóm
          </button>
          <button
            type="button"
            class="btn-ghost border border-slate-200 px-2.5 py-1.5 text-xs text-slate-600"
            @click="collapseAllGroups"
          >
            Đóng tất cả
          </button>
          <button
            v-if="can.create"
            type="button"
            class="btn-primary gap-1.5 text-sm"
            @click="openCreate"
          >
            <AppIcon
              name="add"
              :size="15"
            />
            Thêm tài khoản
          </button>
        </div>
      </div>

      <div class="flex flex-col gap-2.5 border-b border-slate-100 bg-slate-50/40 px-5 py-3.5 sm:flex-row sm:items-center">
        <DatagridToolbarSearch
          v-model="search"
          input-id="ai-accounts-search"
          placeholder="Tên công cụ, email, license, ghi chú…"
        />

        <div class="flex shrink-0 flex-wrap items-center gap-2">
          <div
            ref="filterDdRef"
            class="relative"
          >
            <button
              type="button"
              class="inline-flex h-9 shrink-0 items-center gap-1 rounded-btn border px-2.5 text-xs font-medium transition select-none"
              :class="showFilterDd || activeFilterCount > 0
                ? 'border-brand/40 bg-brand/5 text-brand'
                : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-slate-800'"
              title="Bộ lọc tài khoản"
              @click="openFilter"
            >
              <AppIcon
                name="filter"
                :size="15"
              />
              <span>Lọc</span>
              <span
                v-if="activeFilterCount > 0"
                class="flex h-4 w-4 items-center justify-center rounded-full bg-brand text-[10px] font-bold text-white"
              >{{ activeFilterCount }}</span>
            </button>

            <Transition
              enter-active-class="transition duration-150 ease-out"
              enter-from-class="opacity-0 scale-95 -translate-y-1"
              leave-active-class="transition duration-100 ease-in"
              leave-to-class="opacity-0 scale-95 -translate-y-1"
            >
              <div
                v-if="showFilterDd"
                class="absolute right-0 top-full z-30 mt-1.5 max-h-[min(70vh,28rem)] w-72 origin-top-right overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-elevation-2"
              >
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-2.5">
                  <span class="text-xs font-bold uppercase tracking-wide text-slate-500">Bộ lọc</span>
                  <button
                    v-if="activeFilterCount > 0"
                    type="button"
                    class="text-xs text-brand hover:underline"
                    @click="clearFilters"
                  >
                    Xoá
                  </button>
                </div>

                <div class="border-b border-slate-100 px-4 py-3">
                  <p class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                    Trạng thái
                  </p>
                  <div class="flex flex-col gap-1">
                    <label
                      v-for="opt in AI_ACCOUNT_STATUS_FILTER_OPTS"
                      :key="opt.key"
                      class="flex cursor-pointer items-center justify-between rounded-lg px-2.5 py-1.5 transition hover:bg-slate-50"
                      :class="filters.status === opt.key ? 'bg-brand/5' : ''"
                    >
                      <div class="flex items-center gap-2.5">
                        <span
                          class="flex h-4 w-4 items-center justify-center rounded-full border-2 transition"
                          :class="filters.status === opt.key
                            ? 'border-brand bg-brand'
                            : 'border-slate-300'"
                        >
                          <span
                            v-if="filters.status === opt.key"
                            class="h-1.5 w-1.5 rounded-full bg-white"
                          />
                        </span>
                        <span
                          class="text-sm"
                          :class="filters.status === opt.key ? 'font-semibold text-slate-800' : 'text-slate-600'"
                        >
                          {{ opt.label }}
                        </span>
                      </div>
                      <span class="text-[11px] font-medium text-slate-400">
                        {{ statusCounts[opt.key] ?? 0 }}
                      </span>
                      <input
                        v-model="filters.status"
                        type="radio"
                        :value="opt.key"
                        class="sr-only"
                      >
                    </label>
                  </div>
                </div>

                <div class="border-b border-slate-100 px-4 py-3">
                  <p class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                    Nhóm chức năng
                  </p>
                  <div class="flex flex-col gap-1">
                    <label
                      v-for="g in groupFilterOptions"
                      :key="g.value"
                      class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-1.5 transition hover:bg-slate-50"
                    >
                      <input
                        type="checkbox"
                        class="rounded border-slate-300 text-brand focus:ring-brand/30"
                        :checked="filters.groups.includes(g.value)"
                        @change="toggleGroupFilter(g.value)"
                      >
                      <span
                        class="h-2 w-2 shrink-0 rounded-full"
                        :style="{ backgroundColor: g.dot_color }"
                      />
                      <span class="text-sm text-slate-700">{{ g.label }}</span>
                    </label>
                  </div>
                </div>

                <div class="px-4 py-3">
                  <label class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-1.5 hover:bg-slate-50">
                    <input
                      v-model="filters.attentionOnly"
                      type="checkbox"
                      class="rounded border-slate-300 text-brand focus:ring-brand/30"
                    >
                    <span class="text-sm text-slate-700">Chỉ sắp hết hạn / đã hết hạn</span>
                  </label>
                </div>

                <div
                  v-if="activeFilterCount > 0"
                  class="border-t border-slate-100 px-4 py-2.5"
                >
                  <button
                    type="button"
                    class="w-full rounded-lg py-1.5 text-center text-xs font-medium text-slate-600 hover:bg-slate-50"
                    @click="clearAllFilters"
                  >
                    Xoá tất cả bộ lọc
                  </button>
                </div>
              </div>
            </Transition>
          </div>

          <div
            ref="colDdRef"
            class="relative"
          >
            <button
              type="button"
              class="inline-flex h-9 shrink-0 items-center gap-1 rounded-btn border px-2.5 text-xs font-medium transition select-none"
              :class="showColDd
                ? 'border-brand/40 bg-brand/5 text-brand'
                : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-slate-800'"
              title="Cột hiển thị"
              @click="openCol"
            >
              <AppIcon
                name="columns"
                :size="15"
              />
              <span>Cột</span>
            </button>

            <Transition
              enter-active-class="transition duration-150 ease-out"
              enter-from-class="opacity-0 scale-95 -translate-y-1"
              leave-active-class="transition duration-100 ease-in"
              leave-to-class="opacity-0 scale-95 -translate-y-1"
            >
              <div
                v-if="showColDd"
                class="absolute right-0 top-full z-30 mt-1.5 w-52 origin-top-right rounded-xl border border-slate-200 bg-white shadow-elevation-2"
              >
                <div class="border-b border-slate-100 px-4 py-2.5">
                  <span class="text-xs font-bold uppercase tracking-wide text-slate-500">Cột hiển thị</span>
                </div>
                <div class="px-4 py-2">
                  <div class="flex items-center justify-between rounded-lg px-2 py-1.5 opacity-50">
                    <span class="text-sm text-slate-600">Công cụ</span>
                    <AppIcon
                      name="check"
                      :size="14"
                      class="text-emerald-500"
                    />
                  </div>
                  <div class="flex items-center justify-between rounded-lg px-2 py-1.5 opacity-50">
                    <span class="text-sm text-slate-600">Thao tác</span>
                    <AppIcon
                      name="check"
                      :size="14"
                      class="text-emerald-500"
                    />
                  </div>
                </div>
                <div class="border-t border-slate-100 px-4 py-2">
                  <button
                    v-for="col in AI_ACCOUNT_COLUMNS"
                    :key="col.key"
                    type="button"
                    class="flex w-full cursor-pointer items-center justify-between rounded-lg px-2 py-1.5 transition hover:bg-slate-50"
                    @click="toggleColumn(col.key)"
                  >
                    <span
                      class="text-sm text-left"
                      :class="colVisible[col.key] ? 'font-medium text-slate-800' : 'text-slate-500'"
                    >
                      {{ col.label }}
                    </span>
                    <span
                      class="relative inline-flex h-4 w-7 shrink-0 rounded-full transition-colors duration-200"
                      :class="colVisible[col.key] ? 'bg-brand' : 'bg-slate-200'"
                    >
                      <span
                        class="absolute top-0.5 h-3 w-3 rounded-full bg-white shadow transition-transform duration-200"
                        :class="colVisible[col.key] ? 'translate-x-3.5' : 'translate-x-0.5'"
                      />
                    </span>
                  </button>
                </div>
              </div>
            </Transition>
          </div>

          <Link
            :href="route('ai-accounts.cost-report')"
            class="btn-ghost hidden h-9 gap-1.5 border border-slate-200 text-sm sm:inline-flex"
          >
            <AppIcon
              name="performance"
              :size="16"
            />
            Báo cáo
          </Link>
          <button
            v-if="can.trigger_reminder"
            type="button"
            class="btn-ghost hidden h-9 gap-1.5 border border-slate-200 text-sm md:inline-flex"
            @click="triggerReminder"
          >
            <AppIcon
              name="notifications"
              :size="16"
            />
            Nhắc nhở
          </button>
        </div>
      </div>

      <AiAccountBanner :banner="banner" />

      <AiAccountGroupList
        :groups="displayGroups"
        :expanded="expanded"
        :loading="loading"
        :col-visible="colVisible"
        @toggle="toggleGroup"
        @edit="openEdit"
        @delete="onDelete"
        @renew="openRenew"
      />
    </div>

    <AiAccountFormModal
      :show="formOpen"
      :account="editing"
      :options="options"
      @close="formOpen = false"
      @submit="onFormSubmit"
    />

    <AiAccountRenewModal
      :show="renewOpen"
      :account="renewing"
      @close="renewOpen = false"
      @submit="onRenewSubmit"
    />
  </AppLayout>
</template>
