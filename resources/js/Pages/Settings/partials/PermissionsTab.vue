<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';

// Editable role × permission matrix. The admin column is locked to full access
// (server enforces this too) so an admin can never be locked out.
const props = defineProps({
    permissions: { type: Object, default: () => ({}) },
    canManage: { type: Boolean, default: false },
});

const catalog = computed(() => props.permissions.catalog ?? []);
const roles = computed(() => props.permissions.roles ?? []);
const editableRoles = computed(() => props.permissions.editableRoles ?? []);
const lockedRole = computed(() => props.permissions.lockedRole ?? 'admin');
const navByRole = computed(() => props.permissions.navByRole ?? {});

const roleColor = {
    rose: 'bg-rose-100 text-rose-700',
    violet: 'bg-violet-100 text-violet-700',
    sky: 'bg-sky-100 text-sky-700',
    slate: 'bg-slate-100 text-slate-600',
};

// Seed the form from current grants; a role holding '*' means every permission.
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

const isLocked = (role) => role === lockedRole.value;
const isChecked = (role, key) => isLocked(role) || (form.grants[role]?.includes(key) ?? false);

function toggle(role, key) {
    if (!props.canManage || isLocked(role)) return;
    const set = new Set(form.grants[role] ?? []);
    set.has(key) ? set.delete(key) : set.add(key);
    form.grants[role] = [...set];
}

function submit() {
    if (!props.canManage) return;
    form.put('/settings/permissions', {
        preserveScroll: true,
        onSuccess: () => form.defaults(),
    });
}
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

    <!-- Matrix -->
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
              class="px-3 py-2.5 text-center"
            >
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
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="perm in catalog"
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
        </tbody>
      </table>
    </div>

    <!-- Nav visibility per role (read-only) -->
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

    <!-- Save bar -->
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
