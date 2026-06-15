<script setup>
import { computed, ref, watch, inject, onMounted, onUnmounted } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';

const props = defineProps({
    permissions: { type: Object, default: () => ({}) },
    canManage: { type: Boolean, default: false },
});

const setGroupDirty = inject('setGroupDirty', null);
const openRoleMenu = ref(null);
const permSearch = ref('');

const GROUP_LABELS = {
    system: 'Hệ thống',
    notifications: 'Thông báo',
    departments: 'Phòng ban',
    projects: 'Dự án',
    daily_reports: 'Báo cáo ngày',
    users: 'Tài khoản',
};

const catalog = computed(() => props.permissions.catalog ?? []);
const roles = computed(() => props.permissions.roles ?? []);
const editableRoles = computed(() => props.permissions.editableRoles ?? []);
const lockedRole = computed(() => props.permissions.lockedRole ?? 'admin');
const navByRole = computed(() => props.permissions.navByRole ?? {});

const groupedCatalog = computed(() => {
    const map = new Map();
    for (const perm of catalog.value) {
        const prefix = perm.key.includes('.') ? perm.key.split('.')[0] : 'other';
        if (!map.has(prefix)) {
            map.set(prefix, {
                prefix,
                label: GROUP_LABELS[prefix] ?? prefix,
                items: [],
            });
        }
        map.get(prefix).items.push(perm);
    }
    return [...map.values()];
});

const filteredGroupedCatalog = computed(() => {
    const q = permSearch.value.trim().toLowerCase();
    if (!q) return groupedCatalog.value;
    return groupedCatalog.value
        .map((group) => ({
            ...group,
            items: group.items.filter(
                (p) =>
                    p.label.toLowerCase().includes(q)
                    || p.key.toLowerCase().includes(q),
            ),
        }))
        .filter((g) => g.items.length > 0);
});

const totalPerms = computed(() => catalog.value.length);

function seed() {
    const grants = {};
    for (const role of editableRoles.value) {
        const current = props.permissions.grants?.[role] ?? [];
        grants[role] = current.includes('*')
            ? catalog.value.map((c) => c.key)
            : [...current];
    }
    return { grants };
}

const form = useForm(seed());

watch(
    () => form.isDirty,
    (dirty) => {
        if (typeof setGroupDirty === 'function') {
            setGroupDirty('permissions', dirty);
        }
    },
    { immediate: true },
);

const isLocked = (role) => role === lockedRole.value;
const isChecked = (role, key) => isLocked(role) || (form.grants[role]?.includes(key) ?? false);

function countFor(role) {
    if (isLocked(role)) return totalPerms.value;
    return form.grants[role]?.length ?? 0;
}

function grantPercent(role) {
    if (totalPerms.value === 0) return 0;
    return Math.round((countFor(role) / totalPerms.value) * 100);
}

function toggle(role, key) {
    if (!props.canManage || isLocked(role)) return;
    const set = new Set(form.grants[role] ?? []);
    if (set.has(key)) set.delete(key);
    else set.add(key);
    form.grants[role] = [...set];
}

function selectAll(role) {
    if (!props.canManage || isLocked(role)) return;
    form.grants[role] = catalog.value.map((c) => c.key);
    openRoleMenu.value = null;
}

function deselectAll(role) {
    if (!props.canManage || isLocked(role)) return;
    form.grants[role] = [];
    openRoleMenu.value = null;
}

function toggleGroupForRole(role, group) {
    if (!props.canManage || isLocked(role)) return;
    const keys = group.items.map((p) => p.key);
    const set = new Set(form.grants[role] ?? []);
    const allOn = keys.every((k) => set.has(k));
    if (allOn) keys.forEach((k) => set.delete(k));
    else keys.forEach((k) => set.add(k));
    form.grants[role] = [...set];
}

function groupAllChecked(role, group) {
    if (isLocked(role)) return true;
    return group.items.every((p) => form.grants[role]?.includes(p.key));
}

function toggleRoleMenu(role) {
    if (isLocked(role) || !props.canManage) return;
    openRoleMenu.value = openRoleMenu.value === role ? null : role;
}

function onDocMousedown(e) {
    if (!e.target.closest?.('[data-role-menu]')) {
        openRoleMenu.value = null;
    }
}

onMounted(() => document.addEventListener('mousedown', onDocMousedown));
onUnmounted(() => document.removeEventListener('mousedown', onDocMousedown));

function submit() {
    if (!props.canManage) return;
    form.put('/settings/permissions', {
        preserveScroll: true,
        onSuccess: () => form.defaults(),
    });
}

const roleColor = {
    rose: 'bg-rose-100 text-rose-800 ring-rose-200/80',
    violet: 'bg-violet-100 text-violet-800 ring-violet-200/80',
    sky: 'bg-sky-100 text-sky-800 ring-sky-200/80',
    slate: 'bg-slate-100 text-slate-700 ring-slate-200/80',
};

const roleHeaderBg = {
    rose: 'bg-rose-50/90',
    violet: 'bg-violet-50/90',
    sky: 'bg-sky-50/90',
    slate: 'bg-slate-50/90',
};
</script>

<template>
  <form @submit.prevent="submit">
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <h2 class="text-[15px] font-semibold text-slate-800">
          Phân quyền theo vai trò
        </h2>
        <p class="mt-1 text-xs text-slate-500">
          <span class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-1.5 py-0.5 font-medium text-slate-600">
            <AppIcon
              name="eye"
              :size="11"
            />
            Quản trị viên
          </span>
          luôn toàn quyền — không chỉnh được.
        </p>
      </div>
      <div class="w-full sm:max-w-md">
        <DatagridToolbarSearch
          v-model="permSearch"
          input-id="perm-search"
          placeholder="Tên quyền…"
          compact
          hide-label
          input-height="h-10"
        />
      </div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 shadow-sm">
      <table class="w-full min-w-[640px] border-collapse text-sm">
        <thead>
          <tr class="border-b border-slate-200 bg-slate-50">
            <th class="sticky left-0 z-20 min-w-[12rem] bg-slate-50 px-4 py-3 text-left text-xs font-semibold text-slate-600">
              Quyền
            </th>
            <th
              v-for="role in roles"
              :key="role.value"
              class="relative min-w-[7.5rem] px-2 py-3 text-center"
              :class="roleHeaderBg[role.color] ?? roleHeaderBg.slate"
              data-role-menu
            >
              <div class="flex flex-col items-center gap-1.5">
                <span
                  class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[11px] font-semibold ring-1 ring-inset"
                  :class="roleColor[role.color] ?? roleColor.slate"
                >
                  <AppIcon
                    v-if="isLocked(role.value)"
                    name="eye"
                    :size="11"
                  />
                  {{ role.label }}
                </span>
                <div class="w-full max-w-[6.5rem]">
                  <div class="mb-0.5 flex justify-between text-[10px] font-medium text-slate-500">
                    <span>{{ countFor(role.value) }}/{{ totalPerms }}</span>
                    <span>{{ grantPercent(role.value) }}%</span>
                  </div>
                  <div class="h-1 overflow-hidden rounded-full bg-slate-200/80">
                    <div
                      class="h-full rounded-full bg-brand transition-all"
                      :style="{ width: `${grantPercent(role.value)}%` }"
                    />
                  </div>
                </div>
                <button
                  v-if="canManage && !isLocked(role.value)"
                  type="button"
                  class="inline-flex items-center gap-0.5 rounded-md border border-slate-200 bg-white px-2 py-0.5 text-[10px] font-medium text-slate-600 hover:bg-slate-50"
                  @click="toggleRoleMenu(role.value)"
                >
                  Hàng loạt
                  <AppIcon
                    name="chevron-down"
                    :size="10"
                  />
                </button>
                <div
                  v-if="openRoleMenu === role.value"
                  class="absolute right-1 top-full z-30 mt-1 min-w-[10rem] rounded-lg border border-slate-200 bg-white py-1 text-left shadow-lg"
                >
                  <button
                    type="button"
                    class="block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50"
                    @click="selectAll(role.value)"
                  >
                    Bật tất cả
                  </button>
                  <button
                    type="button"
                    class="block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50"
                    @click="deselectAll(role.value)"
                  >
                    Tắt tất cả
                  </button>
                </div>
              </div>
            </th>
          </tr>
        </thead>
        <tbody>
          <template
            v-for="group in filteredGroupedCatalog"
            :key="group.prefix"
          >
            <tr class="border-t border-slate-200 bg-slate-50/70">
              <td
                colspan="1"
                class="sticky left-0 z-10 bg-slate-50/95 px-4 py-2"
              >
                <span class="text-xs font-semibold uppercase tracking-wide text-slate-600">
                  {{ group.label }}
                </span>
              </td>
              <td
                v-for="role in roles"
                :key="`${group.prefix}-${role.value}`"
                class="px-2 py-2 text-center"
              >
                <button
                  v-if="!isLocked(role.value) && canManage"
                  type="button"
                  class="text-[10px] font-medium text-brand hover:underline"
                  @click="toggleGroupForRole(role.value, group)"
                >
                  {{ groupAllChecked(role.value, group) ? 'Tắt nhóm' : 'Bật nhóm' }}
                </button>
                <span
                  v-else-if="isLocked(role.value)"
                  class="text-[10px] text-slate-400"
                >—</span>
              </td>
            </tr>
            <tr
              v-for="perm in group.items"
              :key="perm.key"
              class="border-t border-slate-100 transition-colors hover:bg-slate-50/50"
            >
              <td class="sticky left-0 z-10 border-r border-slate-100 bg-white px-4 py-2.5">
                <p class="text-sm font-medium text-slate-800">
                  {{ perm.label }}
                </p>
                <p class="mt-0.5 font-mono text-[10px] text-slate-400">
                  {{ perm.key }}
                </p>
              </td>
              <td
                v-for="role in roles"
                :key="role.value"
                class="px-2 py-2.5 text-center"
              >
                <label
                  class="inline-flex cursor-pointer items-center justify-center"
                  :class="(!canManage || isLocked(role.value)) ? 'cursor-not-allowed opacity-60' : ''"
                >
                  <input
                    type="checkbox"
                    class="h-4 w-4 rounded border-slate-300 text-brand focus:ring-brand/30 disabled:opacity-70"
                    :checked="isChecked(role.value, perm.key)"
                    :disabled="!canManage || isLocked(role.value)"
                    :aria-label="`${role.label} — ${perm.label}`"
                    @change="toggle(role.value, perm.key)"
                  >
                </label>
              </td>
            </tr>
          </template>
          <tr v-if="!filteredGroupedCatalog.length">
            <td
              :colspan="roles.length + 1"
              class="px-4 py-8 text-center text-sm text-slate-500"
            >
              Không có quyền khớp «{{ permSearch }}».
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <details class="mt-6 rounded-xl border border-slate-200 bg-slate-50/40">
      <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-slate-700">
        Menu hiển thị theo vai trò
        <span class="ml-2 text-xs font-normal text-slate-400">(chỉ xem)</span>
      </summary>
      <div class="grid gap-3 border-t border-slate-200 p-4 sm:grid-cols-2 lg:grid-cols-4">
        <div
          v-for="role in roles"
          :key="role.value"
          class="rounded-lg border border-slate-200 bg-white p-3"
        >
          <span
            class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset"
            :class="roleColor[role.color] ?? roleColor.slate"
          >
            {{ role.label }}
          </span>
          <ul class="mt-2 space-y-1">
            <li
              v-for="label in (navByRole[role.value] ?? [])"
              :key="label"
              class="flex items-start gap-1.5 text-xs text-slate-600"
            >
              <span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-brand/60" />
              {{ label }}
            </li>
            <li
              v-if="!(navByRole[role.value] ?? []).length"
              class="text-xs text-slate-400"
            >
              Không có mục menu
            </li>
          </ul>
        </div>
      </div>
    </details>

    <div class="mt-6 flex flex-wrap items-center gap-3 border-t border-slate-100 pt-4">
      <button
        type="submit"
        class="btn-primary"
        :disabled="!canManage || form.processing || !form.isDirty"
      >
        <AppIcon
          name="save"
          :size="16"
        />
        Lưu phân quyền
      </button>
      <span
        v-if="form.isDirty && canManage"
        class="text-xs text-amber-600"
      >Có thay đổi chưa lưu</span>
      <span
        v-else-if="form.recentlySuccessful"
        class="inline-flex items-center gap-1 text-xs text-emerald-600"
      >
        <AppIcon
          name="check"
          :size="14"
        /> Đã lưu
      </span>
    </div>
  </form>
</template>
