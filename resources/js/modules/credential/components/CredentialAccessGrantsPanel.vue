<script setup>
import { computed, ref } from 'vue';
import axios from 'axios';
import AppIcon from '@/Components/AppIcon.vue';
import CredentialAccessGrantModal from '@/modules/credential/components/CredentialAccessGrantModal.vue';
import CredentialAccessGrantRowActions from '@/modules/credential/components/CredentialAccessGrantRowActions.vue';
import { useCredentialAccess } from '@/modules/credential/composables/useCredentialAccess';
import { useToast } from '@/shared/composables/useToast';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    credentialId: { type: Number, required: true },
    grants: { type: Array, default: () => [] },
    canManageAccess: { type: Boolean, default: false },
    canRequestAccess: { type: Boolean, default: false },
    permissionOptions: { type: Array, default: () => [] },
    ownerOptions: { type: Array, default: () => [] },
    pendingAccessRequests: { type: Array, default: () => [] },
});

const { grant, revoke, requestAccess } = useCredentialAccess(props.credentialId);
const toast = useToast();

const modalOpen = ref(false);
const editingGrant = ref(null);
const saving = ref(false);

const permissionLabelMap = computed(() =>
    Object.fromEntries(props.permissionOptions.map((p) => [p.value, p.label])),
);

function formatPermissions(perms) {
    const list = perms || [];
    if (!list.length) return 'Chưa cấp quyền cụ thể';
    return list.map((p) => permissionLabelMap.value[p] || p).join(' · ');
}

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

async function onSave(payload) {
    saving.value = true;
    try {
        await grant(payload);
        closeModal();
        router.reload({ preserveScroll: true });
    } catch (err) {
        const resp = err?.response?.data;
        const msg = resp?.message
            || (resp?.errors ? Object.values(resp.errors).flat().join(' ') : null)
            || 'Không lưu được quyền truy cập.';
        toast.error(msg);
    } finally {
        saving.value = false;
    }
}

async function onRevoke(grantRow) {
    await revoke(grantRow.id);
    router.reload({ preserveScroll: true });
}

async function submitAccessRequest() {
    await requestAccess({
        requested_permissions: ['view', 'copy_password'],
        reason: 'Yêu cầu truy cập qua module Credential',
    });
}

async function respondRequest(requestId, decision) {
    try {
        const { data } = await axios.put(route('api.credentials.access-requests.respond', {
            credential: props.credentialId,
            accessRequest: requestId,
        }), { decision });
        toast.success(data.message || 'Đã xử lý yêu cầu.');
        router.reload({ preserveScroll: true });
    } catch {
        toast.error('Không xử lý được yêu cầu.');
    }
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
          <span>{{ req.requester }} · {{ formatPermissions(req.requested_permissions) }}</span>
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

    <div
      v-if="canManageAccess"
      class="flex justify-end"
    >
      <button
        type="button"
        class="btn-primary h-9 gap-1.5 px-3 text-xs"
        @click="openCreate"
      >
        <AppIcon
          name="add"
          :size="15"
        />
        Thêm người truy cập
      </button>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200/80 bg-white">
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
              Cấp quyền
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
            v-for="g in grants"
            :key="g.id"
            class="border-t border-slate-100"
          >
            <td class="px-4 py-3 font-medium text-slate-800">
              {{ g.account?.display_name || 'Chưa cập nhật' }}
            </td>
            <td class="px-4 py-3 text-slate-600">
              {{ g.account?.username || 'Chưa cập nhật' }}
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
          <tr v-if="!grants.length">
            <td
              :colspan="canManageAccess ? 4 : 3"
              class="px-4 py-8 text-center text-sm text-slate-500"
            >
              Chưa cấp quyền cho người dùng khác.
            </td>
          </tr>
        </tbody>
      </table>
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
