<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import CredentialAccessGrantModal from '@/modules/credential/components/CredentialAccessGrantModal.vue';
import CredentialAccessGrantRowActions from '@/modules/credential/components/CredentialAccessGrantRowActions.vue';
import { useCredentialAccess } from '@/modules/credential/composables/useCredentialAccess';
import { normalizeResourceList } from '@/modules/credential/utils/normalizeResourceList';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import { useDialog } from '@/composables/useDialog';
import { router } from '@inertiajs/vue3';
import { date, datetime } from '@/composables/useFormat';
import { httpClient } from '@/shared/services/http';
import { displayOrEmpty, EMPTY_LABELS } from '@/shared/utils/emptyDisplay';

const props = defineProps({
    credentialId: { type: Number, required: true },
    grants: { type: Array, default: () => [] },
    canManageAccess: { type: Boolean, default: false },
    canRequestAccess: { type: Boolean, default: false },
    permissionOptions: { type: Array, default: () => [] },
    ownerOptions: { type: Array, default: () => [] },
    pendingAccessRequests: { type: Array, default: () => [] },
});

const { grant, revoke, requestAccess, respondAccessRequest } = useCredentialAccess(props.credentialId);
const dialog = useDialog();

const modalOpen = ref(false);
const editingGrant = ref(null);
const saving = ref(false);
const searchQ = ref('');
const localGrants = ref([]);
const loadingGrants = ref(false);

const permissionLabelMap = computed(() =>
    Object.fromEntries(props.permissionOptions.map((p) => [p.value, p.label])),
);

function syncGrants(raw) {
    localGrants.value = normalizeResourceList(raw);
}

watch(() => props.grants, (value) => syncGrants(value), { immediate: true });

async function fetchGrants() {
    loadingGrants.value = true;
    try {
        const { data: body } = await httpClient.get(
            route('api.credentials.access-grants.index', { credential: props.credentialId }),
        );
        const list = normalizeResourceList(body?.data);
        localGrants.value = list;
    } catch {
        // Giữ dữ liệu từ Inertia khi API không phản hồi
    } finally {
        loadingGrants.value = false;
    }
}

onMounted(() => {
    fetchGrants();
});

const filteredGrants = computed(() => {
    const q = searchQ.value.trim().toLowerCase();
    if (!q) return localGrants.value;
    return localGrants.value.filter((g) => {
        const name = (g.account?.display_name || '').toLowerCase();
        const username = (g.account?.username || '').toLowerCase();
        return name.includes(q) || username.includes(q);
    });
});

function openCreate() {
    editingGrant.value = null;
    modalOpen.value = true;
}

function openEdit(grantRow) {
    editingGrant.value = grantRow;
    modalOpen.value = true;
}

function closeModal() {
    modalOpen.value = false;
    editingGrant.value = null;
}

function apiErrorMessage(err, fallback) {
    const resp = err?.response?.data;
    return resp?.message
        || (resp?.errors ? Object.values(resp.errors).flat().join(' ') : null)
        || fallback;
}

async function onSave(payload) {
    saving.value = true;
    try {
        const data = await grant(payload);
        closeModal();
        await dialog.alert({
            title: 'Cấp quyền thành công',
            message: data?.message || 'Đã cấp quyền truy cập.',
        });
        await router.reload({ preserveScroll: true });
        await fetchGrants();
    } catch (err) {
        await dialog.alert({
            title: 'Không lưu được quyền',
            message: apiErrorMessage(err, 'Không lưu được quyền truy cập.'),
            tone: 'danger',
        });
    } finally {
        saving.value = false;
    }
}

async function onRevoke(grantRow) {
    const label = grantRow.account?.display_name || 'người dùng này';
    const confirmed = await dialog.confirm({
        title: 'Thu hồi quyền truy cập?',
        message: `Quyền của ${label} sẽ bị gỡ khỏi tài khoản credential.`,
        confirmText: 'Thu hồi',
        tone: 'danger',
    });
    if (!confirmed) return;

    try {
        const data = await revoke(grantRow.id);
        await dialog.alert({
            title: 'Đã thu hồi',
            message: data?.message || 'Đã thu hồi quyền truy cập.',
        });
        await router.reload({ preserveScroll: true });
        await fetchGrants();
    } catch (err) {
        await dialog.alert({
            title: 'Không thu hồi được',
            message: apiErrorMessage(err, 'Không thu hồi được quyền truy cập.'),
            tone: 'danger',
        });
    }
}

async function submitAccessRequest() {
    try {
        const data = await requestAccess({
            requested_permissions: ['view', 'copy_password'],
            reason: 'Yêu cầu truy cập qua module Credential',
        });
        await dialog.alert({
            title: 'Đã gửi yêu cầu',
            message: data?.message || 'Đã gửi yêu cầu truy cập. Chờ người phụ trách duyệt.',
        });
        router.reload({ preserveScroll: true });
    } catch (err) {
        await dialog.alert({
            title: 'Không gửi được yêu cầu',
            message: apiErrorMessage(err, 'Không gửi được yêu cầu truy cập.'),
            tone: 'danger',
        });
    }
}

async function respondRequest(requestId, decision) {
    const isApprove = decision === 'approved';
    try {
        const data = await respondAccessRequest(requestId, decision);
        await dialog.alert({
            title: isApprove ? 'Đã duyệt yêu cầu' : 'Đã từ chối yêu cầu',
            message: data?.message || (isApprove ? 'Đã duyệt yêu cầu truy cập.' : 'Đã từ chối yêu cầu truy cập.'),
        });
        await router.reload({ preserveScroll: true });
        await fetchGrants();
    } catch (err) {
        await dialog.alert({
            title: 'Không xử lý được yêu cầu',
            message: apiErrorMessage(err, 'Không xử lý được yêu cầu truy cập.'),
            tone: 'danger',
        });
    }
}

function formatExpires(expiresAt) {
    if (!expiresAt) return 'Không hết hạn';
    return date(expiresAt);
}
</script>

<template>
  <div class="space-y-4">
    <div
      v-if="canRequestAccess && !canManageAccess"
      class="flex justify-end"
    >
      <button
        type="button"
        class="btn-ghost h-9 gap-1.5 px-3 text-xs"
        @click="submitAccessRequest"
      >
        <AppIcon
          name="send"
          :size="15"
        />
        Yêu cầu truy cập
      </button>
    </div>

    <div
      v-if="canManageAccess && pendingAccessRequests.length"
      class="rounded-xl border border-amber-200 bg-amber-50/80 p-4"
    >
      <p class="text-sm font-medium text-amber-900">
        Yêu cầu chờ duyệt
      </p>
      <ul class="mt-2 space-y-2 text-sm">
        <li
          v-for="req in pendingAccessRequests"
          :key="req.id"
          class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-white px-3 py-2"
        >
          <span>{{ req.requester }} · {{ (req.requested_permissions || []).map((p) => permissionLabelMap[p] || p).join(' · ') }}</span>
          <span class="flex gap-2">
            <button
              type="button"
              class="text-xs text-emerald-700 hover:underline"
              @click="respondRequest(req.id, 'approved')"
            >
              Duyệt
            </button>
            <button
              type="button"
              class="text-xs text-rose-600 hover:underline"
              @click="respondRequest(req.id, 'rejected')"
            >
              Từ chối
            </button>
          </span>
        </li>
      </ul>
    </div>

    <div class="card overflow-hidden">
      <div class="border-b border-slate-100 px-5 py-3">
        <div class="flex w-full min-w-0 flex-wrap items-center gap-2 lg:flex-nowrap">
          <div class="min-w-0 w-full basis-full lg:flex-1 lg:basis-auto">
            <DatagridToolbarSearch
              v-model="searchQ"
              hide-label
              stretch
              inline-actions
              input-height="h-10"
              placeholder="Tìm theo tên hoặc username…"
              aria-label="Tìm người được cấp quyền"
            />
          </div>
          <div
            v-if="canManageAccess"
            class="flex shrink-0 items-center gap-2"
          >
            <button
              type="button"
              class="btn-primary h-10 gap-1.5 px-3 text-xs"
              @click="openCreate"
            >
              <AppIcon
                name="add"
                :size="15"
              />
              Thêm người truy cập
            </button>
          </div>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
            <tr>
              <th class="px-4 py-3">
                Người dùng
              </th>
              <th class="px-4 py-3">
                Username
              </th>
              <th class="px-4 py-3">
                Quyền
              </th>
              <th class="px-4 py-3">
                Ngày cấp
              </th>
              <th class="px-4 py-3">
                Hết hạn
              </th>
              <th class="px-4 py-3">
                Người cấp
              </th>
              <th
                v-if="canManageAccess"
                class="w-16 px-4 py-3 text-right"
              >
                Thao tác
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-if="loadingGrants && !filteredGrants.length"
              class="border-t border-slate-100"
            >
              <td
                :colspan="canManageAccess ? 7 : 6"
                class="px-4 py-8 text-center text-sm text-slate-500"
              >
                Đang tải danh sách quyền…
              </td>
            </tr>
            <tr
              v-for="g in filteredGrants"
              :key="g.id"
              class="border-t border-slate-100"
            >
              <td class="px-4 py-3 font-medium text-slate-800">
                {{ displayOrEmpty(g.account?.display_name, EMPTY_LABELS.notUpdated) }}
              </td>
              <td class="px-4 py-3 text-slate-600">
                {{ displayOrEmpty(g.account?.username, EMPTY_LABELS.notUpdated) }}
              </td>
              <td class="px-4 py-3">
                <div class="flex flex-wrap gap-1">
                  <span
                    v-for="perm in (g.permissions || [])"
                    :key="perm"
                    class="inline-flex rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-700"
                  >
                    {{ permissionLabelMap[perm] || perm }}
                  </span>
                  <span
                    v-if="!(g.permissions || []).length"
                    class="text-xs text-slate-500"
                  >
                    Chưa cập nhật
                  </span>
                </div>
              </td>
              <td class="px-4 py-3 text-slate-600 tabular-nums">
                {{ g.created_at ? datetime(g.created_at) : displayOrEmpty(null, EMPTY_LABELS.notUpdated) }}
              </td>
              <td class="px-4 py-3 text-slate-600 tabular-nums">
                {{ formatExpires(g.expires_at) }}
              </td>
              <td class="px-4 py-3 text-slate-600">
                {{ displayOrEmpty(g.granted_by?.display_name, EMPTY_LABELS.notUpdated) }}
              </td>
              <td
                v-if="canManageAccess"
                class="px-4 py-3 text-right"
              >
                <CredentialAccessGrantRowActions
                  :grant="g"
                  @edit="openEdit"
                  @revoke="onRevoke"
                />
              </td>
            </tr>
            <tr v-if="!loadingGrants && !filteredGrants.length">
              <td
                :colspan="canManageAccess ? 7 : 6"
                class="px-4 py-8 text-center text-sm text-slate-500"
              >
                {{ searchQ.trim() ? 'Không có người dùng khớp tìm kiếm.' : 'Chưa cấp quyền cho người dùng khác.' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <CredentialAccessGrantModal
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
