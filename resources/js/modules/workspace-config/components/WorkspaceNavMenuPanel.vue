<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import HoverTooltip from '@/shared/ui/HoverTooltip.vue';
import { useToast } from '@/shared/composables/useToast';

const props = defineProps({
    departmentCode: { type: String, required: true },
    // Toggleable groups only: [{ key, heading, icon, section }]
    groups: { type: Array, default: () => [] },
    // null = all enabled; string[] = allow-list
    enabled: { type: Array, default: null },
    canManage: { type: Boolean, default: false },
    profileReady: { type: Boolean, default: false },
});

const toast = useToast();
const savedAt = ref(null);

/** Local draft: null means unrestricted (all on). */
const draft = ref(props.enabled === null || props.enabled === undefined
    ? null
    : [...(props.enabled ?? [])]);

const form = useForm({
    enabled_nav_groups: draft.value,
});

watch(
    () => props.enabled,
    (v) => {
        draft.value = v === null || v === undefined ? null : [...(v ?? [])];
        form.enabled_nav_groups = draft.value;
        form.defaults({ enabled_nav_groups: draft.value });
        form.clearErrors();
    },
    { deep: true },
);

const isUnrestricted = computed(() => draft.value === null);

function isEnabled(group) {
    if (draft.value === null) return true;
    return draft.value.includes(group.key);
}

function toggle(group) {
    if (!props.canManage || !props.profileReady) return;
    if (draft.value === null) {
        // Switching from unrestricted → allow-list of all except this one
        draft.value = props.groups.map((g) => g.key).filter((k) => k !== group.key);
    } else {
        const set = new Set(draft.value);
        if (set.has(group.key)) set.delete(group.key);
        else set.add(group.key);
        draft.value = [...set];
    }
    form.enabled_nav_groups = draft.value;
}

function showAll() {
    if (!props.canManage || !props.profileReady) return;
    draft.value = null;
    form.enabled_nav_groups = null;
}

const visibleCount = computed(() => {
    if (draft.value === null) return props.groups.length;
    return props.groups.filter((g) => draft.value.includes(g.key)).length;
});

const isDirty = computed(() => {
    const a = props.enabled === null || props.enabled === undefined ? null : [...(props.enabled ?? [])].sort().join(',');
    const b = draft.value === null ? null : [...draft.value].sort().join(',');
    return a !== b;
});

function submit() {
    if (!props.canManage || !props.profileReady || !isDirty.value) return;
    form.enabled_nav_groups = draft.value;
    form.patch(`/workspace-config/w/${encodeURIComponent(props.departmentCode)}`, {
        preserveScroll: true,
        onSuccess: () => {
            form.defaults({ enabled_nav_groups: draft.value });
            savedAt.value = new Date();
        },
        onError: () => {
            toast.error('Không lưu được cấu hình menu phòng ban.');
        },
    });
}

function formatSavedTime(date) {
    return date.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
  <section
    class="rounded-card border border-slate-200/80 bg-white p-4 shadow-sm sm:p-5"
    aria-label="Menu sidebar phòng ban"
  >
    <div class="flex flex-wrap items-start justify-between gap-2">
      <div class="min-w-0">
        <h2 class="font-display text-sm font-semibold text-slate-800">
          Menu sidebar phòng ban
        </h2>
        <p class="mt-1 text-[12px] leading-snug text-slate-500">
          User thuộc phòng ban này chỉ thấy các nhóm đã bật (sau lớp ẩn toàn hệ thống). Siêu quản trị không bị giới hạn.
        </p>
      </div>
      <span class="inline-flex shrink-0 items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-600">
        <AppIcon
          name="eye"
          :size="12"
          class="text-slate-400"
        />
        {{ visibleCount }}/{{ groups.length }}
      </span>
    </div>

    <p
      v-if="!profileReady"
      class="mt-3 text-[12px] leading-snug text-amber-700"
    >
      Kích hoạt workspace trước để cấu hình menu sidebar.
    </p>

    <template v-else>
      <div class="mt-3 flex items-center justify-between gap-2">
        <span
          v-if="isUnrestricted"
          class="text-[11.5px] font-medium text-emerald-700"
        >Đang mở tất cả nhóm (không giới hạn PB)</span>
        <span
          v-else
          class="text-[11.5px] font-medium text-amber-700"
        >Đang giới hạn theo danh sách đã chọn</span>
        <button
          type="button"
          class="text-[12px] font-semibold text-brand hover:text-brand-700 disabled:cursor-not-allowed disabled:text-slate-300"
          :disabled="!canManage || isUnrestricted"
          @click="showAll"
        >
          Hiện tất cả
        </button>
      </div>

      <ul class="mt-3 max-h-72 space-y-1.5 overflow-y-auto pr-0.5">
        <li
          v-for="group in groups"
          :key="group.key"
        >
          <component
            :is="canManage ? 'button' : 'div'"
            :type="canManage ? 'button' : undefined"
            class="flex w-full items-center gap-2.5 rounded-xl border px-3 py-2 text-left transition-colors"
            :class="[
              isEnabled(group) ? 'border-slate-200 bg-white' : 'border-slate-100 bg-slate-50/60',
              canManage
                ? (isEnabled(group) ? 'hover:border-brand/30 hover:bg-brand/[0.03]' : 'hover:bg-slate-100')
                : 'cursor-default',
            ]"
            :role="canManage ? 'switch' : undefined"
            :aria-checked="canManage ? String(isEnabled(group)) : undefined"
            @click="toggle(group)"
          >
            <span
              class="grid h-8 w-8 shrink-0 place-items-center rounded-lg"
              :class="isEnabled(group) ? 'bg-brand/10 text-brand' : 'bg-slate-200/70 text-slate-400'"
            >
              <AppIcon
                :name="group.icon"
                :size="16"
                :stroke-width="1.7"
              />
            </span>
            <span class="min-w-0 flex-1">
              <span
                class="block truncate text-[13px] font-semibold"
                :class="isEnabled(group) ? 'text-slate-800' : 'text-slate-400'"
              >{{ group.heading }}</span>
              <span
                v-if="group.section"
                class="mt-0.5 block truncate text-[11px] text-slate-400"
              >{{ group.section }}</span>
            </span>
            <HoverTooltip
              :label="isEnabled(group) ? 'Đang hiển thị — tắt để ẩn với PB' : 'Đang ẩn với PB — bật để hiển thị'"
              placement="left"
            >
              <span
                class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors"
                :class="isEnabled(group) ? 'bg-brand' : 'bg-slate-300'"
              >
                <span
                  class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform duration-200"
                  :class="isEnabled(group) ? 'translate-x-[18px]' : 'translate-x-0.5'"
                />
              </span>
            </HoverTooltip>
          </component>
        </li>
      </ul>

      <div
        v-if="canManage"
        class="mt-4 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3"
      >
        <button
          type="button"
          class="btn-primary inline-flex h-9 items-center gap-1.5 px-3 text-xs"
          :disabled="form.processing || !isDirty"
          @click="submit"
        >
          <AppIcon
            name="save"
            :size="15"
          />
          Lưu menu PB
        </button>
        <span
          v-if="isDirty"
          class="text-[11px] text-amber-600"
        >Có thay đổi chưa lưu</span>
        <span
          v-else-if="form.recentlySuccessful && savedAt"
          class="inline-flex items-center gap-1 text-[11px] text-emerald-600"
        >
          <AppIcon
            name="check"
            :size="13"
          />
          Đã lưu lúc {{ formatSavedTime(savedAt) }}
        </span>
      </div>
      <p
        v-if="form.errors.enabled_nav_groups"
        class="mt-2 text-xs text-rose-600"
      >
        {{ form.errors.enabled_nav_groups }}
      </p>
    </template>
  </section>
</template>
