<script setup>
import { ref } from 'vue';
import axios from 'axios';
import AppIcon from '@/Components/AppIcon.vue';
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
const showForm = ref(false);
const form = ref({
    account_id: '',
    permissions: ['view'],
});

async function submitGrant() {
    await grant({
        account_id: Number(form.value.account_id),
        permissions: form.value.permissions,
    });
    showForm.value = false;
    router.reload({ preserveScroll: true });
}

async function onRevoke(id) {
    await revoke(id);
    router.reload({ preserveScroll: true });
}

function togglePerm(value) {
    const set = new Set(form.value.permissions);
    if (set.has(value)) set.delete(value);
    else set.add(value);
    form.value.permissions = [...set];
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
          <span>{{ req.requester }} · {{ (req.requested_permissions || []).join(', ') }}</span>
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
        @click="showForm = !showForm"
      >
        <AppIcon
          name="add"
          :size="15"
        />
        Thêm người truy cập
      </button>
    </div>

    <div
      v-if="showForm"
      class="rounded-xl border border-brand/20 bg-brand/5 p-4"
    >
      <div class="grid gap-3 sm:grid-cols-2">
        <select
          v-model="form.account_id"
          class="input h-10 w-full text-sm"
        >
          <option value="">
            Chọn tài khoản hệ thống
          </option>
          <option
            v-for="o in ownerOptions"
            :key="o.id"
            :value="o.id"
          >
            {{ o.display_name }} ({{ o.username }})
          </option>
        </select>
        <div class="flex flex-wrap gap-2">
          <label
            v-for="p in permissionOptions"
            :key="p.value"
            class="inline-flex cursor-pointer items-center gap-1 rounded-lg border px-2 py-1 text-xs"
            :class="form.permissions.includes(p.value) ? 'border-brand bg-white' : 'border-slate-200'"
          >
            <input
              type="checkbox"
              class="sr-only"
              :checked="form.permissions.includes(p.value)"
              @change="togglePerm(p.value)"
            >
            {{ p.label }}
          </label>
        </div>
      </div>
      <div class="mt-3 flex gap-2">
        <button
          type="button"
          class="btn-primary h-9 px-3 text-xs"
          @click="submitGrant"
        >
          Lưu
        </button>
        <button
          type="button"
          class="btn-ghost h-9 px-3 text-xs"
          @click="showForm = false"
        >
          Huỷ
        </button>
      </div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200/80">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
          <tr>
            <th class="px-4 py-3">
              Người dùng
            </th>
            <th class="px-4 py-3">
              Quyền
            </th>
            <th
              v-if="canManageAccess"
              class="px-4 py-3 w-24"
            />
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="g in grants"
            :key="g.id"
            class="border-t border-slate-100"
          >
            <td class="px-4 py-3 font-medium">
              {{ g.account?.display_name || '—' }}
            </td>
            <td class="px-4 py-3 text-xs text-slate-600">
              {{ (g.permissions || []).join(', ') }}
            </td>
            <td
              v-if="canManageAccess"
              class="px-4 py-3"
            >
              <button
                type="button"
                class="text-xs text-rose-600 hover:underline"
                @click="onRevoke(g.id)"
              >
                Thu hồi
              </button>
            </td>
          </tr>
          <tr v-if="!grants.length">
            <td
              colspan="3"
              class="px-4 py-6 text-center text-slate-500"
            >
              Chưa cấp quyền cho ai ngoài phụ trách.
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
