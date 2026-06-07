<script setup>
import { computed, onMounted, ref, toRef, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { useDialog } from '@/composables/useDialog';
import { useAiAccounts } from '@/modules/aiAccount/composables/useAiAccounts';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import FilterVisibilityDropdown from '@/shared/ui/FilterVisibilityDropdown.vue';
import ColumnVisibilityDropdown from '@/shared/ui/ColumnVisibilityDropdown.vue';
import { useAiAccountListUi } from '@/modules/aiAccount/composables/useAiAccountListUi';
import AiAccountAttentionStrip from '@/modules/aiAccount/components/AiAccountAttentionStrip.vue';
import AiAccountGroupList from '@/modules/aiAccount/components/AiAccountGroupList.vue';
import AiAccountFormModal from '@/modules/aiAccount/components/AiAccountFormModal.vue';
import AiAccountRenewModal from '@/modules/aiAccount/components/AiAccountRenewModal.vue';
import AiAccountPasswordViewersModal from '@/modules/aiAccount/components/AiAccountPasswordViewersModal.vue';
import AiAccountBanner from '@/modules/aiAccount/components/AiAccountBanner.vue';
import DatagridPaginationFooter from '@/shared/ui/DatagridPaginationFooter.vue';

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
    updateRenewalPayment,
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
    paymentCounts,
    groupFilterOptions,
    clearFilters,
    toggleGroupFilter,
    AI_ACCOUNT_STATUS_FILTER_OPTS,
    AI_ACCOUNT_RENEWAL_PAYMENT_FILTER_OPTS,
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
const passwordViewersOpen = ref(false);
const passwordViewerAccountId = ref(null);
const editing = ref(null);
const renewing = ref(null);

const totalCount = computed(() => summaryCards.value?.total_accounts ?? 0);
const allAccountsForPicker = computed(() =>
    (groups.value ?? []).flatMap((g) => g.accounts ?? []),
);

function openPasswordViewers(row = null) {
    passwordViewerAccountId.value = row?.id ?? null;
    passwordViewersOpen.value = true;
}

function closePasswordViewers() {
    passwordViewersOpen.value = false;
    passwordViewerAccountId.value = null;
}

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

async function onRenewalPaymentChange(row, status) {
    await updateRenewalPayment(row.id, status);
}

async function onStatusChange(row, status) {
    const ok = status === 'expired'
        ? await dialog.confirm({
            title: 'Đánh dấu hết hạn',
            message: `${row.tool_name}: gói có thể đã hết trước ngày trên phiếu. Cập nhật ngày hết hạn về hôm nay?`,
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
  <Head title="Quản lý AI · Tài khoản" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Quản lý AI"
        subtitle="Tài khoản đang dùng · liên kết với phiếu đề xuất mua sắm"
        icon="account"
        icon-color="brand"
        :badge="totalCount || null"
      />
    </template>

    <AiAccountBanner :banner="banner" />

    <div class="card overflow-visible shadow-sm">
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
            <template v-if="filterSummaryLabel">
              {{ filterSummaryLabel }}
            </template>
            <template v-else-if="search.trim()">
              Tìm kiếm
            </template>
            <template v-if="search.trim()">
              <span v-if="filterSummaryLabel"> · </span>«{{ search.trim() }}»
            </template>
          </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <button
            type="button"
            class="inline-flex h-9 items-center gap-1 rounded-btn border border-slate-200 bg-white px-2.5 text-xs font-medium text-slate-600 hover:border-slate-300"
            :title="allGroupsExpanded ? 'Thu gọn mọi nhóm' : 'Mở rộng mọi nhóm'"
            @click="toggleAllGroups"
          >
            <AppIcon
              name="chevron-down"
              :size="15"
              class="transition-transform"
              :class="allGroupsExpanded ? 'rotate-180' : ''"
            />
            {{ allGroupsExpanded ? 'Đóng tất cả nhóm' : 'Mở tất cả nhóm' }}
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

      <div class="border-b border-slate-100 bg-slate-50/40 px-5 py-3.5">
        <div class="flex flex-col gap-2.5 sm:flex-row sm:items-center">
          <DatagridToolbarSearch
            v-model="search"
            input-id="ai-accounts-search"
            placeholder="Tên công cụ, email, license, ghi chú…"
          />

          <div class="flex shrink-0 flex-wrap items-center gap-2">
            <div
              ref="filterDdRef"
              class="relative shrink-0"
            >
              <button
                type="button"
                class="inline-flex h-9 shrink-0 items-center gap-1 rounded-btn border px-2.5 text-xs font-medium transition select-none"
                :class="showFilterPanelDd
                  ? 'border-brand/40 bg-brand/5 text-brand'
                  : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-slate-800'"
                :title="`Hiển thị bộ lọc (${enabledFilterControlCount}/${FILTER_CONTROLS.length})`"
                aria-label="Hiển thị bộ lọc trên thanh công cụ"
                @click="openFilter"
              >
                <AppIcon
                  name="filter"
                  :size="15"
                />
                <span>Lọc</span>
              </button>
              <FilterVisibilityDropdown
                v-model="visibleFilters"
                :anchor="filterDdRef"
                :show="showFilterPanelDd"
                :controls="FILTER_CONTROLS"
                @persist="persistVisibleFilters"
              />
            </div>

            <div
              ref="colDdRef"
              class="relative shrink-0"
            >
              <button
                type="button"
                class="inline-flex h-9 shrink-0 items-center gap-1 rounded-btn border px-2.5 text-xs font-medium transition select-none"
                :class="showColDd
                  ? 'border-brand/40 bg-brand/5 text-brand'
                  : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-slate-800'"
                title="Cột hiển thị"
                aria-label="Cột hiển thị"
                @click="openCol"
              >
                <AppIcon
                  name="columns"
                  :size="15"
                />
                <span>Cột</span>
              </button>
              <ColumnVisibilityDropdown
                v-model="visibleCols"
                :anchor="colDdRef"
                :show="showColDd"
                :columns="AI_ACCOUNT_TABLE_COLUMNS"
                :fixed-labels="['Công cụ', 'Thao tác']"
                @persist="persistVisibleColumns"
              />
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
              v-if="can.manage_password_viewers"
              type="button"
              class="btn-ghost hidden h-9 gap-1.5 border border-slate-200 text-sm md:inline-flex"
              title="Cấp quyền xem mật khẩu theo từng công cụ AI"
              @click="openPasswordViewers()"
            >
              <AppIcon
                name="eye"
                :size="16"
              />
              Quyền xem MK
            </button>
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

        <div
          v-if="hasFilterRow"
          class="mt-2.5 flex flex-wrap items-center gap-2 border-t border-slate-50 pt-2.5"
        >
          <select
            v-if="visibleFilters.status"
            v-model="filters.status"
            class="input h-9 w-[min(100%,11rem)] shrink-0 text-sm sm:w-52"
            aria-label="Lọc theo trạng thái"
          >
            <option
              v-for="opt in AI_ACCOUNT_STATUS_FILTER_OPTS"
              :key="opt.key"
              :value="opt.key"
            >
              {{ opt.label }} ({{ statusCounts[opt.key] ?? 0 }})
            </option>
          </select>

          <select
            v-if="visibleFilters.renewal_payment"
            v-model="filters.renewalPayment"
            class="input h-9 w-[min(100%,14rem)] shrink-0 text-sm sm:w-60"
            aria-label="Lọc theo thanh toán gia hạn"
          >
            <option
              v-for="opt in AI_ACCOUNT_RENEWAL_PAYMENT_FILTER_OPTS"
              :key="opt.key"
              :value="opt.key"
            >
              {{ opt.label }} ({{ paymentCounts[opt.key] ?? 0 }})
            </option>
          </select>

          <template v-if="visibleFilters.group">
            <button
              v-for="g in groupFilterOptions"
              :key="g.value"
              type="button"
              class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-btn border px-2.5 text-xs font-medium transition"
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
          </template>

          <label
            v-if="visibleFilters.attention"
            class="inline-flex h-9 shrink-0 cursor-pointer items-center gap-2 rounded-btn border border-slate-200 bg-white px-2.5 text-xs font-medium text-slate-700 hover:border-slate-300"
          >
            <input
              v-model="filters.attentionOnly"
              type="checkbox"
              class="rounded border-slate-300 text-brand focus:ring-brand/30"
            >
            Chỉ sắp hết hạn / đã hết hạn
          </label>

          <button
            v-if="activeFilterCount > 0"
            type="button"
            class="text-xs font-medium text-brand hover:underline"
            @click="clearAllFilters"
          >
            Xoá bộ lọc
          </button>
        </div>
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
        :can-manage-password-viewers="can.manage_password_viewers"
        :status-options="options.status ?? []"
        @toggle="toggleGroup"
        @edit="openEdit"
        @delete="onDelete"
        @renew="openRenew"
        @status-change="onStatusChange"
        @renewal-payment="onRenewalPaymentChange"
        @password-viewers="openPasswordViewers"
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
      @close="formOpen = false"
      @submit="onFormSubmit"
    />

    <AiAccountRenewModal
      :show="renewOpen"
      :account="renewing"
      @close="renewOpen = false"
      @submit="onRenewSubmit"
    />

    <AiAccountPasswordViewersModal
      :show="passwordViewersOpen"
      :accounts="allAccountsForPicker"
      :initial-account-id="passwordViewerAccountId"
      @close="closePasswordViewers"
    />
  </AppLayout>
</template>
