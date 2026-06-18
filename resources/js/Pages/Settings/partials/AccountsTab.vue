<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';

const props = defineProps({
    accounts: { type: Object, default: () => ({ accounts: [], roles: [] }) },
    canManage: { type: Boolean, default: false },
});

const search = ref('');
const savingId = ref(null);

const roles = computed(() => props.accounts.roles ?? []);
const roleLabel = (value) => roles.value.find((r) => r.value === value)?.label ?? value;
const roleColorName = (value) => roles.value.find((r) => r.value === value)?.color ?? 'slate';

const list = computed(() => {
    const q = search.value.trim().toLowerCase();
    const rows = props.accounts.accounts ?? [];
    if (!q) return rows;
    return rows.filter(
        (a) =>
            (a.display_name ?? '').toLowerCase().includes(q)
            || (a.email ?? '').toLowerCase().includes(q)
            || (a.username ?? '').toLowerCase().includes(q),
    );
});

const roleChip = {
    fuchsia: 'bg-fuchsia-100 text-fuchsia-800 ring-fuchsia-200/80',
    rose: 'bg-rose-100 text-rose-800 ring-rose-200/80',
    violet: 'bg-violet-100 text-violet-800 ring-violet-200/80',
    sky: 'bg-sky-100 text-sky-800 ring-sky-200/80',
    slate: 'bg-slate-100 text-slate-700 ring-slate-200/80',
};

function changeRole(account, event) {
    const role = event.target.value;
    if (!props.canManage || role === account.role) return;
    savingId.value = account.id;
    router.put(
        `/settings/accounts/${account.id}/role`,
        { role },
        {
            preserveScroll: true,
            onFinish: () => (savingId.value = null),
            onError: () => {
                event.target.value = account.role; // revert select on failure
            },
        },
    );
}
</script>

<template>
  <div>
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <h2 class="text-[15px] font-semibold text-slate-800">
          Tài khoản &amp; Vai trò
        </h2>
        <p class="mt-1 text-xs text-slate-500">
          Gán vai trò cho tài khoản đăng nhập. Quyền của mỗi vai trò cấu hình ở tab
          <span class="font-medium text-slate-600">Phân quyền</span>.
        </p>
      </div>
      <div class="w-full sm:max-w-xs">
        <DatagridToolbarSearch
          v-model="search"
          input-id="account-search"
          placeholder="Tìm tên hoặc email…"
          compact
          hide-label
          input-height="h-10"
        />
      </div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 shadow-sm">
      <table class="w-full min-w-[640px] border-collapse text-sm">
        <thead>
          <tr class="border-b border-slate-200 bg-slate-50 text-left text-xs font-semibold text-slate-600">
            <th class="px-4 py-3">
              Tài khoản
            </th>
            <th class="px-4 py-3">
              Vai trò hiện tại
            </th>
            <th class="px-4 py-3">
              Đổi vai trò
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="account in list"
            :key="account.id"
            class="border-t border-slate-100 hover:bg-slate-50/50"
            :class="account.is_active ? '' : 'opacity-60'"
          >
            <td class="px-4 py-2.5">
              <div class="flex items-center gap-1.5">
                <p class="text-sm font-medium text-slate-800">
                  {{ account.display_name }}
                </p>
                <span
                  v-if="account.is_self"
                  class="rounded bg-slate-100 px-1 text-[10px] text-slate-500"
                >Bạn</span>
                <span
                  v-if="!account.is_active"
                  class="rounded bg-amber-50 px-1 text-[10px] text-amber-600"
                >Ngừng</span>
              </div>
              <p class="text-[11px] text-slate-400">
                {{ account.email ?? account.username }}
              </p>
            </td>
            <td class="px-4 py-2.5">
              <span
                class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset"
                :class="roleChip[roleColorName(account.role)] ?? roleChip.slate"
              >
                {{ roleLabel(account.role) }}
              </span>
            </td>
            <td class="px-4 py-2.5">
              <div class="flex items-center gap-2">
                <select
                  class="h-9 rounded-lg border-slate-300 text-sm focus:border-brand focus:ring-brand/30 disabled:opacity-50"
                  :value="account.role"
                  :disabled="!canManage || savingId === account.id"
                  @change="changeRole(account, $event)"
                >
                  <option
                    v-for="role in roles"
                    :key="role.value"
                    :value="role.value"
                  >
                    {{ role.label }}
                  </option>
                </select>
                <AppIcon
                  v-if="savingId === account.id"
                  name="refresh"
                  :size="15"
                  class="animate-spin text-slate-400"
                />
              </div>
            </td>
          </tr>
          <tr v-if="!list.length">
            <td
              colspan="3"
              class="px-4 py-8 text-center text-sm text-slate-500"
            >
              Không có tài khoản khớp.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <p
      v-if="!canManage"
      class="mt-3 text-xs text-slate-400"
    >
      Chỉ Super Admin được gán vai trò.
    </p>
  </div>
</template>
