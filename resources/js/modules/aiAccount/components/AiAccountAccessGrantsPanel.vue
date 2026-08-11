<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import AiAccountAccessGrantModal from '@/modules/aiAccount/components/AiAccountAccessGrantModal.vue';
import {
    fetchAiAccountGrants,
    grantAiAccountAccess,
    revokeAiAccountAccess,
} from '@/modules/aiAccount/composables/useAiAccountAccess';
import { AI_ACCOUNT_ACCESS_PERMISSIONS } from '@/modules/aiAccount/config/accessPermissions';
import { useDialog } from '@/composables/useDialog';
import { useToast } from '@/shared/composables/useToast';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';

const props = defineProps({
    aiAccountId: { type: String, default: null },
    canManageAccess: { type: Boolean, default: false },
    permissionOptions: { type: Array, default: () => AI_ACCOUNT_ACCESS_PERMISSIONS },
    ownerOptions: { type: Array, default: () => [] },
});

const dialog = useDialog();
const toast = useToast();

const modalOpen = ref(false);
const editingGrant = ref(null);
const saving = ref(false);
const searchQ = ref('');
const localGrants = ref([]);
const loading = ref(false);

const permissionLabelMap = computed(() =>
    Object.fromEntries(props.permissionOptions.map((p) => [p.value, p.label])),
);

const filteredGrants = computed(() => {
    const q = searchQ.value.trim().toLowerCase();
    if (!q) return localGrants.value;
    return localGrants.value.filter((g) => {
        const name = (g.account?.display_name || '').toLowerCase();
        const username = (g.account?.username || '').toLowerCase();
        return name.includes(q) || username.includes(q);
    });
});

async function fetchGrants() {
    if (!props.aiAccountId) {
        localGrants.value = [];
        return;
    }
    loading.value = true;
    try {
        const list = await fetchAiAccountGrants(props.aiAccountId);
        localGrants.value = Array.isArray(list) ? list : [];
    } catch {
        toast.error('Không tải được danh sách phân quyền.');
    } finally {
        loading.value = false;
    }
}

watch(() => props.aiAccountId, () => fetchGrants());
onMounted(() => fetchGrants());

function openCreate() {
    editingGrant.value = null;
    modalOpen.value = true;
}

function openEdit(row) {
    editingGrant.value = row;
    modalOpen.value = true;
}

function closeModal() {
    modalOpen.value = false;
    editingGrant.value = null;
}

async function onSave(payload) {
    if (!props.aiAccountId) return;
    saving.value = true;
    try {
        const data = await grantAiAccountAccess(props.aiAccountId, payload);
        closeModal();
        toast.success(data?.message ?? 'Đã cấp quyền truy cập.');
        await fetchGrants();
    } catch (err) {
        toast.error(err?.response?.data?.message ?? 'Không lưu được quyền truy cập.');
    } finally {
        saving.value = false;
    }
}

async function onRevoke(row) {
    if (!props.aiAccountId) return;
    const label = row.account?.display_name || 'người dùng này';
    const ok = await dialog.confirm({
        title: 'Thu hồi quyền',
        message: `Thu hồi quyền truy cập của ${label}?`,
        confirmText: 'Thu hồi',
        tone: 'danger',
    });
    if (!ok) return;
    try {
        const data = await revokeAiAccountAccess(props.aiAccountId, row.id);
        toast.success(data?.message ?? 'Đã thu hồi quyền.');
        await fetchGrants();
    } catch (err) {
        toast.error(err?.response?.data?.message ?? 'Không thu hồi được quyền.');
    }
}

function permLabels(perms) {
    return (perms || []).map((p) => permissionLabelMap.value[p] || p).join(' · ');
}
</script>

<template>
  <div class="space-y-3">
    <div
      v-if="!aiAccountId"
      class="rounded-xl border border-dashed border-slate-300 bg-slate-50/80 px-4 py-8 text-center"
    >
      <AppIcon
        name="lock"
        :size="22"
        class="mx-auto text-slate-400"
      />
      <p class="mt-2 text-sm font-medium text-slate-700">
        Lưu tài khoản trước để cấp quyền
      </p>
      <p class="mt-1 text-[11px] text-slate-500">
        Người tạo luôn thấy tài khoản. Sau khi lưu, thêm người được phép xem tại đây.
      </p>
    </div>

    <template v-else>
      <div class="flex flex-wrap items-center gap-2">
        <div class="min-w-0 flex-1">
          <DatagridToolbarSearch
            v-model="searchQ"
            input-id="ai-access-search"
            placeholder="Tìm theo tên hoặc username…"
            stretch
            hide-label
            inline-actions
            input-height="h-10"
          />
        </div>
        <button
          v-if="canManageAccess"
          type="button"
          class="btn-primary inline-flex h-10 shrink-0 items-center gap-1.5 px-3 text-xs font-semibold"
          @click="openCreate"
        >
          <AppIcon
            name="add"
            :size="15"
          />
          Thêm người
        </button>
      </div>

      <div
        v-if="loading"
        class="py-8 text-center text-sm text-slate-500"
      >
        Đang tải phân quyền…
      </div>
      <div
        v-else-if="filteredGrants.length === 0"
        class="rounded-xl border border-slate-200 bg-white px-4 py-8 text-center text-sm text-slate-500"
      >
        Chưa có người được cấp quyền (ngoài người tạo và admin).
      </div>
      <ul
        v-else
        class="divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200 bg-white"
      >
        <li
          v-for="row in filteredGrants"
          :key="row.id"
          class="flex flex-wrap items-center gap-3 px-3 py-3 sm:px-4"
        >
          <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-medium text-slate-800">
              {{ row.account?.display_name || 'Chưa cập nhật' }}
            </p>
            <p class="truncate text-[11px] text-slate-500">
              {{ row.account?.username }}
            </p>
            <p class="mt-1 text-[11px] text-slate-600">
              {{ permLabels(row.permissions) }}
            </p>
          </div>
          <div
            v-if="canManageAccess"
            class="flex shrink-0 items-center gap-1"
          >
            <button
              type="button"
              class="btn-ghost h-8 px-2 text-xs"
              @click="openEdit(row)"
            >
              Sửa
            </button>
            <button
              type="button"
              class="btn-ghost h-8 px-2 text-xs text-rose-600"
              @click="onRevoke(row)"
            >
              Thu hồi
            </button>
          </div>
        </li>
      </ul>
    </template>

    <AiAccountAccessGrantModal
      :show="modalOpen"
      :grant="editingGrant"
      :owner-options="ownerOptions"
      :permission-options="permissionOptions"
      :saving="saving"
      @close="closeModal"
      @save="onSave"
    />
  </div>
</template>
