<script setup>
import { computed, onMounted, ref, toRef, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { useDialog } from '@/composables/useDialog';
import { useAiAccounts } from '@/modules/aiAccount/composables/useAiAccounts';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import DatagridToolbarActionButton from '@/shared/ui/DatagridToolbarActionButton.vue';
import DatagridFilterField from '@/shared/ui/DatagridFilterField.vue';
import FilterVisibilityDropdown from '@/shared/ui/FilterVisibilityDropdown.vue';
import ColumnVisibilityDropdown from '@/shared/ui/ColumnVisibilityDropdown.vue';
import { useAiAccountListUi } from '@/modules/aiAccount/composables/useAiAccountListUi';
import AiAccountAttentionStrip from '@/modules/aiAccount/components/AiAccountAttentionStrip.vue';
import AiAccountGroupList from '@/modules/aiAccount/components/AiAccountGroupList.vue';
import AiAccountFormModal from '@/modules/aiAccount/components/AiAccountFormModal.vue';
import AiAccountRenewModal from '@/modules/aiAccount/components/AiAccountRenewModal.vue';
import AiAccountBanner from '@/modules/aiAccount/components/AiAccountBanner.vue';
import DatagridPaginationFooter from '@/shared/ui/DatagridPaginationFooter.vue';

const FILTER_CONTROL_CLASS = 'input h-10 w-full text-sm';

const props = defineProps({
    can: { type: Object, default: () => ({}) },
    options: { type: Object, required: true },
    formHints: { type: Object, default: () => ({}) },
    reminderSchedule: { type: Array, default: () => ['08:00', '14:00'] },
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
    createAccount,
    updateAccount,
    updateAccountStatus,
    deleteAccount,
    renewAccount,
    triggerReminder,
    toggleGroup,
    expandAllGroups,
    toggleAllGroups,
    allGroupsExpanded,
} = useAiAccounts();

const {
    filters,
    activeFilterCount,
    filterSummaryLabel,
    paginatedDisplayGroups,
    filteredAccountCount,
    perPage,
    paginationMeta,
    setPerPage,
    goToPage,
    PER_PAGE_OPTIONS,
    statusCounts,
    groupFilterOptions,
    clearFilters,
    toggleGroupFilter,
    AI_ACCOUNT_STATUS_FILTER_OPTS,
    AI_ACCOUNT_TABLE_COLUMNS,
    colVisible,
    visibleCols,
    persistVisibleColumns,
    visibleFilters,
    hasFilterRow,
    enabledFilterControlCount,
    persistVisibleFilters,
    FILTER_CONTROLS,
    showFilterPanelDd,
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

const listBadge = computed(() => {
    if (activeFilterCount.value > 0 || search.value.trim()) {
        return filteredAccountCount.value;
    }
    return totalCount.value;
});

let searchTimer = null;
watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => fetchList(), 350);
});

onMounted(() => {
    fetchList();
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

async function onStatusChange(row, status) {
    const ok = status === 'expired'
        ? await dialog.confirm({
            title: 'Đánh dấu hết hạn',
            message: `${row.tool_name}: cập nhật ngày hết hạn về hôm nay?`,
            confirmText: 'Hết hạn hôm nay',
            cancelText: 'Chỉ đổi trạng thái',
        })
        : true;
    await updateAccountStatus(row.id, {
        status,
        sync_expiry_on_expire: status === 'expired' && ok,
        expiry_date: status === 'expired' && ok
            ? new Date().toISOString().slice(0, 10)
            : null,
    });
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

function showAttentionOnly() {
    filters.status = 'all';
    filters.attentionOnly = true;
    expandAllGroups();
}
</script>

<template>
  <Head title="Tài khoản AI" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Tài khoản AI"
        subtitle="Email, mật khẩu, chi phí và phiếu đề xuất / đề nghị thanh toán"
        icon="account"
        icon-color="brand"
        :badge="listBadge || null"
      >
        <button
          v-if="can.create"
          type="button"
          class="btn-primary inline-flex h-9 shrink-0 items-center gap-1.5 px-3 text-xs font-semibold"
          @click="openCreate"
        >
          <AppIcon
            name="add"
            :size="15"
          />
          Thêm tài khoản
        </button>
      </PageHeader>
    </template>

    <AiAccountBanner :banner="banner" />

    <div class="card overflow-visible shadow-sm">
      <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-700">
        <div class="flex w-full min-w-0 flex-wrap items-center gap-2 lg:flex-nowrap">
          <div class="min-w-0 w-full basis-full lg:min-w-[10rem] lg:flex-1 lg:basis-auto">
            <DatagridToolbarSearch
              v-model="search"
              input-id="ai-accounts-search"
              placeholder="Tên công cụ, email, ghi chú…"
              stretch
              inline-actions
              hide-label
              input-height="h-10"
            />
          </div>

          <div class="flex shrink-0 flex-wrap items-center gap-2">
            <div
              ref="filterDdRef"
              class="relative shrink-0"
            >
              <DatagridToolbarActionButton
                icon="filter"
                :active="showFilterPanelDd"
                :title="`Hiển thị bộ lọc (${enabledFilterControlCount}/${FILTER_CONTROLS.length})`"
                @click="openFilter"
              >
                Lọc
              </DatagridToolbarActionButton>
              <FilterVisibilityDropdown
                v-model="visibleFilters"
                :show="showFilterPanelDd"
                :anchor-ref="filterDdRef"
                :controls="FILTER_CONTROLS"
                @persist="persistVisibleFilters"
              />
            </div>

            <div
              ref="colDdRef"
              class="relative shrink-0"
            >
              <DatagridToolbarActionButton
                icon="columns"
                :active="showColDd"
                title="Cột hiển thị"
                @click="openCol"
              >
                Cột
              </DatagridToolbarActionButton>
              <ColumnVisibilityDropdown
                v-model="visibleCols"
                :show="showColDd"
                :anchor-ref="colDdRef"
                :columns="AI_ACCOUNT_TABLE_COLUMNS"
                :fixed-labels="['Công cụ', 'Thao tác']"
                @persist="persistVisibleColumns"
              />
            </div>

            <Link
              :href="route('ai-accounts.cost-report')"
              class="btn-ghost hidden h-10 items-center gap-1.5 border border-slate-200 px-3 text-xs font-medium sm:inline-flex"
            >
              <AppIcon
                name="budget"
                :size="15"
              />
              Chi phí AI
            </Link>
            <button
              v-if="can.trigger_reminder"
              type="button"
              class="btn-ghost hidden h-10 items-center gap-1.5 border border-slate-200 px-3 text-xs font-medium md:inline-flex"
              @click="triggerReminder"
            >
              <AppIcon
                name="notifications"
                :size="15"
              />
              Nhắc nhở
            </button>
          </div>

          <div class="ml-auto flex shrink-0 items-center gap-2">
            <DatagridToolbarActionButton
              icon="chevron-down"
              :title="allGroupsExpanded ? 'Thu gọn mọi nhóm' : 'Mở rộng mọi nhóm'"
              @click="toggleAllGroups"
            >
              <span class="hidden sm:inline">{{ allGroupsExpanded ? 'Thu nhóm' : 'Mở nhóm' }}</span>
              <span class="sm:hidden">{{ allGroupsExpanded ? 'Thu' : 'Mở' }}</span>
            </DatagridToolbarActionButton>
          </div>
        </div>

        <p
          v-if="!hasFilterRow && (activeFilterCount > 0 || search.trim())"
          class="mt-2 text-xs text-slate-500"
        >
          <template v-if="filterSummaryLabel">
            {{ filterSummaryLabel }}
          </template>
          <template v-if="search.trim()">
            <span v-if="filterSummaryLabel"> · </span>«{{ search.trim() }}»
          </template>
        </p>

        <Transition name="fade-slide">
          <div
            v-if="hasFilterRow"
            class="grid grid-cols-1 gap-3 border-t border-slate-100 px-0 pt-4 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6 dark:border-slate-700"
          >
            <DatagridFilterField v-if="visibleFilters.status">
              <select
                v-model="filters.status"
                :class="FILTER_CONTROL_CLASS"
                aria-label="Trạng thái"
              >
                <option
                  v-for="opt in AI_ACCOUNT_STATUS_FILTER_OPTS"
                  :key="opt.key"
                  :value="opt.key"
                >
                  {{ opt.label }} ({{ statusCounts[opt.key] ?? 0 }})
                </option>
              </select>
            </DatagridFilterField>

            <DatagridFilterField
              v-if="visibleFilters.group"
              class="col-span-full xl:col-span-6"
            >
              <div class="flex flex-wrap gap-2">
                <button
                  v-for="g in groupFilterOptions"
                  :key="g.value"
                  type="button"
                  class="inline-flex h-10 shrink-0 items-center gap-1.5 rounded-btn border px-3 text-xs font-medium transition"
                  :class="filters.groups.includes(g.value)
                    ? 'border-brand/40 bg-brand/5 text-brand'
                    : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'"
                  @click="toggleGroupFilter(g.value)"
                >
                  <span
                    class="h-2 w-2 shrink-0 rounded-full"
                    :style="{ backgroundColor: g.dot_color }"
                  />
                  {{ g.label }}
                </button>
              </div>
            </DatagridFilterField>

            <DatagridFilterField
              v-if="visibleFilters.attention"
              class="flex items-center"
            >
              <label class="flex h-10 w-full cursor-pointer items-center gap-2 rounded-btn border border-slate-200 px-3 text-sm text-slate-700 dark:border-slate-700">
                <input
                  v-model="filters.attentionOnly"
                  type="checkbox"
                  class="rounded border-slate-300 text-brand focus:ring-brand/30"
                >
                Chỉ sắp hết hạn / đã hết hạn
              </label>
            </DatagridFilterField>

            <div
              v-if="activeFilterCount > 0"
              class="col-span-full flex justify-end"
            >
              <button
                type="button"
                class="text-xs font-medium text-brand"
                @click="clearAllFilters"
              >
                Đặt lại bộ lọc
              </button>
            </div>
          </div>
        </Transition>
      </div>

      <AiAccountAttentionStrip
        :cards="summaryCards"
        :schedule-times="reminderSchedule"
        class="mt-3"
        @show-attention="showAttentionOnly"
      />

      <AiAccountGroupList
        class="pb-1"
        :groups="paginatedDisplayGroups"
        :expanded="expanded"
        :loading="loading"
        :col-visible="colVisible"
        :status-options="options.status ?? []"
        @toggle="toggleGroup"
        @edit="openEdit"
        @delete="onDelete"
        @renew="openRenew"
        @status-change="onStatusChange"
      />

      <DatagridPaginationFooter
        v-if="!loading && filteredAccountCount > 0"
        variant="bar"
        client
        :meta="paginationMeta"
        :per-page="perPage"
        :per-page-options="PER_PAGE_OPTIONS"
        @update:per-page="setPerPage"
        @page-change="goToPage"
      />
    </div>

    <AiAccountFormModal
      :show="formOpen"
      :account="editing"
      :can="can"
      :form-hints="formHints"
      :reminder-schedule="reminderSchedule"
      :status-options="options.status ?? []"
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
