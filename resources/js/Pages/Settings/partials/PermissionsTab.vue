<script setup>
import { computed, ref, watch, inject } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';

const props = defineProps({
    permissions: { type: Object, default: () => ({}) },
    canManage: { type: Boolean, default: false },
});

const setGroupDirty = inject('setGroupDirty', null);
const permSearch = ref('');

const modules = computed(() => props.permissions.modules ?? []);
const roles = computed(() => props.permissions.roles ?? []);
const editableRoles = computed(() => props.permissions.editableRoles ?? []);
const lockedRole = computed(() => props.permissions.lockedRole ?? 'super_admin');
const navByRole = computed(() => props.permissions.navByRole ?? {});
const allKeys = computed(() => modules.value.flatMap((m) => m.abilities.map((a) => a.key)));
const nonReservedKeys = computed(() =>
    modules.value.flatMap((m) => m.abilities.filter((a) => !a.reserved).map((a) => a.key)));

// Module sections grouped by the catalog "group" label.
const sections = computed(() => {
    const map = new Map();
    for (const mod of modules.value) {
        if (!map.has(mod.group)) map.set(mod.group, { group: mod.group, modules: [] });
        map.get(mod.group).modules.push(mod);
    }
    return [...map.values()];
});

const filteredSections = computed(() => {
    const q = permSearch.value.trim().toLowerCase();
    if (!q) return sections.value;
    return sections.value
        .map((section) => ({
            ...section,
            modules: section.modules
                .map((mod) => ({
                    ...mod,
                    abilities: mod.abilities.filter(
                        (a) =>
                            a.label.toLowerCase().includes(q)
                            || a.key.toLowerCase().includes(q)
                            || mod.label.toLowerCase().includes(q),
                    ),
                }))
                .filter((mod) => mod.abilities.length > 0),
        }))
        .filter((section) => section.modules.length > 0);
});

function seed() {
    const grants = {};
    for (const role of editableRoles.value) {
        const current = props.permissions.grants?.[role] ?? [];
        grants[role] = current.includes('*')
            ? [...nonReservedKeys.value]
            : current.filter((k) => nonReservedKeys.value.includes(k));
    }
    return { grants };
}

const form = useForm(seed());

watch(
    () => props.permissions,
    () => {
        const next = seed();
        form.grants = next.grants;
        form.defaults(next);
    },
);

watch(
    () => form.isDirty,
    (dirty) => {
        if (typeof setGroupDirty === 'function') setGroupDirty('permissions', dirty);
    },
    { immediate: true },
);

const isLocked = (role) => role === lockedRole.value;

function isChecked(role, ability) {
    if (isLocked(role)) return true; // super_admin = full access
    if (ability.reserved) return false; // reserved → super only
    return form.grants[role]?.includes(ability.key) ?? false;
}

function canEdit(role, ability) {
    return props.canManage && !isLocked(role) && !ability.reserved;
}

function toggle(role, ability) {
    if (!canEdit(role, ability)) return;
    const set = new Set(form.grants[role] ?? []);
    if (set.has(ability.key)) set.delete(ability.key);
    else set.add(ability.key);
    form.grants[role] = [...set];
}

function moduleKeys(mod) {
    return mod.abilities.filter((a) => !a.reserved).map((a) => a.key);
}

function moduleAllChecked(role, mod) {
    if (isLocked(role)) return true;
    const keys = moduleKeys(mod);
    return keys.length > 0 && keys.every((k) => form.grants[role]?.includes(k));
}

function toggleModule(role, mod) {
    if (!props.canManage || isLocked(role)) return;
    const keys = moduleKeys(mod);
    if (keys.length === 0) return;
    const set = new Set(form.grants[role] ?? []);
    const allOn = keys.every((k) => set.has(k));
    keys.forEach((k) => (allOn ? set.delete(k) : set.add(k)));
    form.grants[role] = [...set];
}

function setAll(role, on) {
    if (!props.canManage || isLocked(role)) return;
    form.grants[role] = on ? [...nonReservedKeys.value] : [];
}

function countFor(role) {
    if (isLocked(role)) return allKeys.value.length;
    return form.grants[role]?.length ?? 0;
}

function grantPercent(role) {
    const total = isLocked(role) ? allKeys.value.length : nonReservedKeys.value.length;
    if (total === 0) return 0;
    return Math.round((countFor(role) / total) * 100);
}

function submit() {
    if (!props.canManage) return;
    form
        .transform((data) => ({ grants: data.grants }))
        .put('/settings/permissions', {
            preserveScroll: true,
            onSuccess: () => form.defaults(),
        });
}

const roleColor = {
    fuchsia: 'bg-fuchsia-100 text-fuchsia-800 ring-fuchsia-200/80',
    rose: 'bg-rose-100 text-rose-800 ring-rose-200/80',
    violet: 'bg-violet-100 text-violet-800 ring-violet-200/80',
    sky: 'bg-sky-100 text-sky-800 ring-sky-200/80',
    slate: 'bg-slate-100 text-slate-700 ring-slate-200/80',
};
</script>

<template>
  <form @submit.prevent="submit">
    <!-- Header + role summary -->
    <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
      <div>
        <h2 class="text-[15px] font-semibold text-slate-800">
          Phân quyền theo vai trò
        </h2>
        <p class="mt-1 text-xs text-slate-500">
          <span class="inline-flex items-center gap-1 rounded-md bg-fuchsia-50 px-1.5 py-0.5 font-medium text-fuchsia-700">
            <AppIcon
              name="account"
              :size="11"
            />
            Super Admin
          </span>
          luôn toàn quyền &amp; độc quyền cấu hình — không chỉnh được.
        </p>
      </div>
      <div class="w-full lg:max-w-sm">
        <DatagridToolbarSearch
          v-model="permSearch"
          input-id="perm-search"
          placeholder="Tìm quyền hoặc module…"
          compact
          hide-label
          input-height="h-10"
        />
      </div>
    </div>

    <!-- Role summary chips -->
    <div class="mb-5 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5">
      <div
        v-for="role in roles"
        :key="`sum-${role.value}`"
        class="rounded-xl border border-slate-200 bg-white p-3"
      >
        <div class="flex items-center justify-between">
          <span
            class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset"
            :class="roleColor[role.color] ?? roleColor.slate"
          >
            <AppIcon
              v-if="isLocked(role.value)"
              name="account"
              :size="10"
            />
            {{ role.label }}
          </span>
          <span class="text-[11px] font-medium text-slate-400">{{ grantPercent(role.value) }}%</span>
        </div>
        <div class="mt-2 mb-1 flex items-center justify-between text-[10px] text-slate-500">
          <span>{{ countFor(role.value) }} quyền</span>
          <div
            v-if="canManage && !isLocked(role.value)"
            class="flex gap-1.5"
          >
            <button
              type="button"
              class="text-brand hover:underline"
              @click="setAll(role.value, true)"
            >
              Tất cả
            </button>
            <span class="text-slate-300">·</span>
            <button
              type="button"
              class="text-slate-500 hover:underline"
              @click="setAll(role.value, false)"
            >
              Bỏ
            </button>
          </div>
        </div>
        <div class="h-1 overflow-hidden rounded-full bg-slate-200/80">
          <div
            class="h-full rounded-full transition-all"
            :class="isLocked(role.value) ? 'bg-fuchsia-500' : 'bg-brand'"
            :style="{ width: `${grantPercent(role.value)}%` }"
          />
        </div>
      </div>
    </div>

    <!-- Module sections -->
    <div class="space-y-5">
      <section
        v-for="section in filteredSections"
        :key="section.group"
      >
        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
          {{ section.group }}
        </h3>
        <div class="grid gap-3 xl:grid-cols-2">
          <div
            v-for="mod in section.modules"
            :key="mod.key"
            class="overflow-hidden rounded-xl border border-slate-200 shadow-sm"
          >
            <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-3 py-2">
              <AppIcon
                :name="mod.icon"
                :size="15"
                class="text-slate-500"
              />
              <span class="text-sm font-semibold text-slate-800">{{ mod.label }}</span>
              <span
                v-if="mod.reserved"
                class="ml-auto inline-flex items-center gap-1 rounded-full bg-fuchsia-50 px-1.5 py-0.5 text-[10px] font-medium text-fuchsia-700"
              >
                <AppIcon
                  name="account"
                  :size="9"
                /> Super
              </span>
            </div>
            <table class="w-full border-collapse text-sm">
              <thead>
                <tr class="border-b border-slate-100 bg-white">
                  <th class="px-3 py-1.5 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-400">
                    Quyền
                  </th>
                  <th
                    v-for="role in roles"
                    :key="`${mod.key}-h-${role.value}`"
                    class="px-1 py-1.5 text-center"
                  >
                    <span
                      class="inline-block rounded px-1 text-[10px] font-semibold"
                      :class="isLocked(role.value) ? 'text-fuchsia-600' : 'text-slate-500'"
                      :title="role.label"
                    >{{ role.label.split(' ')[0] }}</span>
                  </th>
                </tr>
                <tr
                  v-if="!mod.reserved"
                  class="border-b border-slate-100 bg-slate-50/60"
                >
                  <td class="px-3 py-1 text-[10px] font-medium text-slate-400">
                    Toàn module
                  </td>
                  <td
                    v-for="role in roles"
                    :key="`${mod.key}-all-${role.value}`"
                    class="px-1 py-1 text-center"
                  >
                    <button
                      v-if="canManage && !isLocked(role.value)"
                      type="button"
                      class="text-[10px] font-medium text-brand hover:underline"
                      @click="toggleModule(role.value, mod)"
                    >
                      {{ moduleAllChecked(role.value, mod) ? 'Tắt' : 'Bật' }}
                    </button>
                    <span
                      v-else
                      class="text-[10px] text-slate-300"
                    >—</span>
                  </td>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="ability in mod.abilities"
                  :key="ability.key"
                  class="border-t border-slate-50 hover:bg-slate-50/50"
                >
                  <td class="px-3 py-1.5">
                    <span class="text-[13px] text-slate-700">{{ ability.label }}</span>
                    <span class="ml-1 font-mono text-[9px] text-slate-300">{{ ability.action }}</span>
                  </td>
                  <td
                    v-for="role in roles"
                    :key="`${ability.key}-${role.value}`"
                    class="px-1 py-1.5 text-center"
                  >
                    <input
                      type="checkbox"
                      class="h-4 w-4 rounded border-slate-300 text-brand focus:ring-brand/30 disabled:opacity-50"
                      :class="isLocked(role.value) ? 'text-fuchsia-500' : ''"
                      :checked="isChecked(role.value, ability)"
                      :disabled="!canEdit(role.value, ability)"
                      :aria-label="`${role.label} — ${mod.label} ${ability.label}`"
                      @change="toggle(role.value, ability)"
                    >
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <p
        v-if="!filteredSections.length"
        class="rounded-xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-500"
      >
        Không có quyền khớp «{{ permSearch }}».
      </p>
    </div>

    <!-- Nav per role (read-only) -->
    <details class="mt-6 rounded-xl border border-slate-200 bg-slate-50/40">
      <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-slate-700">
        Menu hiển thị theo vai trò
        <span class="ml-2 text-xs font-normal text-slate-400">(chỉ xem)</span>
      </summary>
      <div class="grid gap-3 border-t border-slate-200 p-4 sm:grid-cols-2 lg:grid-cols-5">
        <div
          v-for="role in roles"
          :key="`nav-${role.value}`"
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

    <!-- Sticky save bar -->
    <div class="sticky bottom-0 z-10 mt-6 flex flex-wrap items-center gap-3 border-t border-slate-100 bg-white/95 py-4 backdrop-blur">
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
      <span
        v-if="!canManage"
        class="text-xs text-slate-400"
      >Chỉ Super Admin được chỉnh phân quyền.</span>
    </div>
  </form>
</template>
