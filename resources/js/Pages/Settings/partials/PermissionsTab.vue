<script setup>
import { computed, ref, watch, inject, onMounted, onUnmounted } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    permissions: { type: Object, default: () => ({}) },
    canManage: { type: Boolean, default: false },
});

const setGroupDirty = inject('setGroupDirty', null);
const openRoleMenu = ref(null);

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
    rose: 'bg-rose-100 text-rose-700',
    violet: 'bg-violet-100 text-violet-700',
    sky: 'bg-sky-100 text-sky-700',
    slate: 'bg-slate-100 text-slate-600',
};
</script>

<template>
  <form @submit.prevent="submit">
    <div class="mb-5">
      <h2 class="text-[15px] font-semibold text-slate-800">
        Phân quyền theo vai trò
      </h2>
      <p class="mt-0.5 text-[12.5px] text-slate-400">
        Bật/tắt quyền cho từng vai trò. Cột
        <span class="font-medium text-slate-500">Quản trị viên</span>
        luôn có toàn quyền và không thể chỉnh.
      </p>
    </div>

    <div class="overflow-x-auto rounded-card border border-slate-100">
      <table class="w-full border-collapse text-sm">
        <thead>
          <tr class="bg-slate-50/80">
            <th class="sticky left-0 z-10 bg-slate-50/80 px-3.5 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-400">
              Quyền
            </th>
            <th
              v-for="role in roles"
              :key="role.value"
              class="relative px-3 py-2.5 text-center"
              data-role-menu
            >
              <div class="inline-flex flex-col items-center gap-1">
                <span
                  class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold leading-none"
                  :class="roleColor[role.color] ?? roleColor.slate"
                >
                  <AppIcon
                    v-if="isLocked(role.value)"
                    name="eye"
                    :size="11"
                  />
                  {{ role.label }}
                </span>
                <span class="text-[10px] font-medium text-slate-400">
                  {{ countFor(role.value) }}/{{ totalPerms }}
                </span>
                <button
                  v-if="canManage && !isLocked(role.value)"
                  type="button"
                  class="inline-flex items-center gap-0.5 rounded border border-slate-200 bg-white px-1.5 py-0.5 text-[10px] text-slate-500 hover:bg-slate-50"
                  @click="toggleRoleMenu(role.value)"
                >
                  Chọn
                  <AppIcon
                    name="chevron-down"
                    :size="10"
                  />
                </button>
                <div
                  v-if="openRoleMenu === role.value"
                  class="absolute right-2 top-full z-20 mt-1 min-w-[9rem] rounded-md border border-slate-200 bg-white py-1 text-left shadow-md"
                >
                  <button
                    type="button"
                    class="block w-full px-3 py-1.5 text-left text-xs text-slate-600 hover:bg-slate-50"
                    @click="selectAll(role.value)"
                  >
                    Chọn tất cả
                  </button>
                  <button
                    type="button"
                    class="block w-full px-3 py-1.5 text-left text-xs text-slate-600 hover:bg-slate-50"
                    @click="deselectAll(role.value)"
                  >
                    Bỏ chọn tất cả
                  </button>
                </div>
              </div>
            </th>
          </tr>
        </thead>
        <tbody>
          <template
            v-for="group in groupedCatalog"
            :key="group.prefix"
          >
            <tr class="border-t border-slate-100 bg-slate-50/60">
              <td
                colspan="100"
                class="sticky left-0 z-10 bg-slate-50/90 px-3.5 py-2 text-[11px] font-semibold uppercase tracking-wide text-slate-500"
              >
                {{ group.label }}
              </td>
            </tr>
            <tr
              v-for="perm in group.items"
              :key="perm.key"
              class="border-t border-slate-100 hover:bg-slate-50/60"
            >
              <td class="sticky left-0 z-10 bg-white px-3.5 py-2.5">
                <p class="font-medium text-slate-700">
                  {{ perm.label }}
                </p>
                <p class="font-mono text-[10.5px] text-slate-400">
                  {{ perm.key }}
                </p>
              </td>
              <td
                v-for="role in roles"
                :key="role.value"
                class="px-3 py-2.5 text-center"
              >
                <button
                  type="button"
                  :disabled="!canManage || isLocked(role.value)"
                  class="grid h-7 w-7 place-items-center rounded-md border transition-colors mx-auto"
                  :class="[
                    isChecked(role.value, perm.key)
                      ? 'border-brand bg-brand text-white'
                      : 'border-slate-200 bg-white text-transparent hover:border-slate-300',
                    (!canManage || isLocked(role.value)) ? 'cursor-not-allowed opacity-70' : 'cursor-pointer',
                  ]"
                  :aria-label="`${role.label} — ${perm.label}`"
                  :aria-pressed="isChecked(role.value, perm.key)"
                  @click="toggle(role.value, perm.key)"
                >
                  <AppIcon
                    name="check"
                    :size="15"
                  />
                </button>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <div class="mt-6">
      <h3 class="text-[13px] font-semibold text-slate-700">
        Menu hiển thị theo vai trò
      </h3>
      <p class="mb-3 mt-0.5 text-[12px] text-slate-400">
        Suy ra từ cấu hình điều hướng (chỉ hiển thị, không chỉnh ở đây).
      </p>
      <div class="grid gap-3 sm:grid-cols-2">
        <div
          v-for="role in roles"
          :key="role.value"
          class="rounded-card border border-slate-100 p-3"
        >
          <span
            class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold leading-none"
            :class="roleColor[role.color] ?? roleColor.slate"
          >
            {{ role.label }}
          </span>
          <div class="mt-2 flex flex-wrap gap-1.5">
            <span
              v-for="label in (navByRole[role.value] ?? [])"
              :key="label"
              class="rounded-md bg-slate-100 px-2 py-0.5 text-[11px] text-slate-500"
            >
              {{ label }}
            </span>
            <span
              v-if="!(navByRole[role.value] ?? []).length"
              class="text-[11px] text-slate-300"
            >Không có</span>
          </div>
        </div>
      </div>
    </div>

    <div class="mt-7 flex items-center gap-3 border-t border-slate-100 pt-4">
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
