<script setup>
import { computed, onUnmounted, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import FieldTooltip from '@/shared/ui/FieldTooltip.vue';
import HoverTooltip from '@/shared/ui/HoverTooltip.vue';
import { useToast } from '@/shared/composables/useToast';

const props = defineProps({
    // { groups: [{ key, heading, icon, section, sectionKey, superOnly, protected }], hidden: string[] }
    menu: { type: Object, default: () => ({ groups: [], hidden: [] }) },
    canManage: { type: Boolean, default: false },
    saveHotkeysEnabled: { type: Boolean, default: true },
});

const toast = useToast();
const savedAt = ref(null);
const query = ref('');

const form = useForm({ hidden_groups: [...(props.menu.hidden ?? [])] });

// Re-seed when the Inertia prop changes (e.g. after a save redirect).
watch(
    () => props.menu.hidden,
    (hidden) => {
        form.hidden_groups = [...(hidden ?? [])];
        form.defaults({ hidden_groups: [...(hidden ?? [])] });
    },
    { deep: true },
);

const isHidden = (group) => form.hidden_groups.includes(group.key);

function toggle(group) {
    if (!props.canManage || group.protected) return;
    const set = new Set(form.hidden_groups);
    if (set.has(group.key)) set.delete(group.key);
    else set.add(group.key);
    form.hidden_groups = [...set];
}

function showAll() {
    if (!props.canManage) return;
    form.hidden_groups = [];
}

const toggleable = computed(() => props.menu.groups.filter((g) => !g.protected));
const hiddenCount = computed(() => toggleable.value.filter((g) => isHidden(g)).length);
const visibleCount = computed(() => props.menu.groups.length - hiddenCount.value);

const showSearch = computed(() => props.menu.groups.length > 8);

// Filtered rows + per-row section-start flag (recomputed on the filtered list).
const rows = computed(() => {
    const q = query.value.trim().toLowerCase();
    const list = q
        ? props.menu.groups.filter((g) =>
            (g.heading || '').toLowerCase().includes(q)
            || (g.section || '').toLowerCase().includes(q))
        : props.menu.groups;

    return list.map((group, i) => ({
        group,
        sectionStart: i === 0 || list[i - 1].section !== group.section,
    }));
});

function submit() {
    if (!props.canManage) return;
    form.put('/settings/menu', {
        preserveScroll: true,
        onSuccess: () => {
            form.defaults();
            savedAt.value = new Date();
        },
        onError: () => {
            toast.error('Không lưu được. Phiên có thể đã hết hạn — trang sẽ tải lại.');
        },
    });
}

function formatSavedTime(date) {
    return date.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
}

function onKeydownSave(e) {
    if (!props.saveHotkeysEnabled) return;
    if (!(e.ctrlKey || e.metaKey) || e.key !== 's') return;
    if (!props.canManage || !form.isDirty || form.processing) return;
    e.preventDefault();
    submit();
}

watch(
    () => props.saveHotkeysEnabled,
    (enabled) => {
        document.removeEventListener('keydown', onKeydownSave);
        if (enabled) document.addEventListener('keydown', onKeydownSave);
    },
    { immediate: true },
);

onUnmounted(() => document.removeEventListener('keydown', onKeydownSave));
</script>

<template>
  <form
    class="flex h-full flex-col"
    @submit.prevent="submit"
  >
    <!-- Hướng dẫn sử dụng -->
    <div class="mb-4 flex gap-2.5 rounded-xl border border-brand/15 bg-brand/[0.04] px-3.5 py-3">
      <span class="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-lg bg-brand/10 text-brand">
        <AppIcon
          name="info"
          :size="15"
        />
      </span>
      <div class="min-w-0 text-[12.5px] leading-relaxed text-slate-600">
        <p class="font-semibold text-slate-700">
          Cách hoạt động
        </p>
        <ul class="mt-1 space-y-0.5 text-slate-500">
          <li>• Tắt công tắc để ẩn nhóm menu khỏi thanh điều hướng của <span class="font-medium text-slate-600">mọi người dùng</span> (lớp toàn hệ thống).</li>
          <li>• Module tương ứng trên <span class="font-medium text-slate-600">Cấu hình workspace</span> cũng bị ẩn theo (vd. ẩn «Báo cáo» → ẩn trọng số BC ngày).</li>
          <li>• Menu theo từng phòng ban cấu hình tại shell workspace phòng ban — user thường chỉ thấy nhóm đã bật cho PB của họ; siêu quản trị không bị giới hạn lớp này.</li>
          <li>• Nhấn <span class="font-medium text-slate-600">“Lưu thay đổi”</span> để áp dụng — chưa lưu thì chưa có hiệu lực.</li>
          <li>• Nhóm có dấu <span class="font-bold text-rose-500">*</span> là nhóm bắt buộc, không thể ẩn.</li>
        </ul>
      </div>
    </div>

    <!-- Bộ đếm + reset nhanh -->
    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
      <div class="flex items-center gap-1.5">
        <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-[12px] font-semibold text-slate-600">
          <AppIcon
            name="eye"
            :size="13"
            class="text-slate-400"
          />
          Đang hiển thị {{ visibleCount }}/{{ menu.groups.length }} nhóm
        </span>
        <FieldTooltip
          text="Số nhóm menu đang hiển thị so với tổng số nhóm. Thay đổi áp dụng cho toàn bộ người dùng sau khi lưu."
        />
      </div>
      <button
        type="button"
        class="inline-flex items-center gap-1 text-[12.5px] font-semibold text-brand transition-colors hover:text-brand-700 disabled:cursor-not-allowed disabled:text-slate-300"
        :disabled="!canManage || hiddenCount === 0"
        @click="showAll"
      >
        <AppIcon
          name="refresh"
          :size="13"
        />
        Hiện tất cả
      </button>
    </div>

    <!-- Tìm kiếm (chỉ hiện khi nhiều nhóm) -->
    <div
      v-if="showSearch"
      class="relative mb-3"
    >
      <AppIcon
        name="search"
        :size="15"
        class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"
      />
      <input
        v-model="query"
        type="text"
        class="input pl-9"
        placeholder="Tìm nhóm menu…"
        aria-label="Tìm nhóm menu"
      >
      <button
        v-if="query"
        type="button"
        class="absolute right-2.5 top-1/2 grid h-6 w-6 -translate-y-1/2 place-items-center rounded-full text-slate-400 hover:bg-slate-100 hover:text-slate-600"
        aria-label="Xoá tìm kiếm"
        @click="query = ''"
      >
        <AppIcon
          name="close"
          :size="14"
        />
      </button>
    </div>

    <!-- Danh sách nhóm -->
    <ul
      v-if="rows.length"
      class="space-y-1.5"
    >
      <template
        v-for="row in rows"
        :key="row.group.key"
      >
        <li
          v-if="row.sectionStart && row.group.section"
          class="select-none px-1 pb-1 pt-2 text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400 first:pt-0"
        >
          {{ row.group.section }}
        </li>
        <li>
          <component
            :is="row.group.protected || !canManage ? 'div' : 'button'"
            :type="row.group.protected || !canManage ? undefined : 'button'"
            class="flex w-full items-center gap-3 rounded-xl border px-3.5 py-2.5 text-left transition-colors"
            :class="[
              isHidden(row.group)
                ? 'border-slate-100 bg-slate-50/60'
                : 'border-slate-200 bg-white',
              row.group.protected
                ? 'opacity-90'
                : !canManage
                  ? 'cursor-default'
                  : isHidden(row.group)
                    ? 'hover:bg-slate-100'
                    : 'hover:border-brand/30 hover:bg-brand/[0.03]',
            ]"
            :role="row.group.protected || !canManage ? undefined : 'switch'"
            :aria-checked="row.group.protected ? 'true' : String(!isHidden(row.group))"
            @click="toggle(row.group)"
          >
            <span
              class="grid h-9 w-9 shrink-0 place-items-center rounded-lg transition-colors"
              :class="isHidden(row.group) ? 'bg-slate-200/70 text-slate-400' : 'bg-brand/10 text-brand'"
            >
              <AppIcon
                :name="row.group.icon"
                :size="18"
                :stroke-width="1.7"
              />
            </span>

            <span class="min-w-0 flex-1">
              <span class="flex items-center gap-1">
                <span
                  class="truncate text-[13.5px] font-semibold"
                  :class="isHidden(row.group) ? 'text-slate-400' : 'text-slate-800'"
                >{{ row.group.heading }}</span>
                <HoverTooltip
                  v-if="row.group.protected"
                  label="Nhóm bắt buộc — không thể ẩn"
                  placement="top"
                >
                  <span class="text-[13px] font-bold leading-none text-rose-500">*</span>
                </HoverTooltip>
              </span>
              <span class="mt-0.5 flex items-center gap-1.5 text-[11.5px] text-slate-400">
                <span v-if="row.group.section">{{ row.group.section }}</span>
                <template v-if="row.group.protected">
                  <span aria-hidden="true">·</span>
                  <span class="font-medium text-slate-400">Luôn hiển thị</span>
                </template>
                <template v-else-if="isHidden(row.group)">
                  <span aria-hidden="true">·</span>
                  <span class="font-medium text-amber-600">Đang ẩn</span>
                </template>
              </span>
            </span>

            <HoverTooltip
              v-if="row.group.protected"
              label="Nhóm bắt buộc — luôn hiển thị"
              placement="left"
            >
              <span class="grid h-6 w-6 place-items-center text-slate-300">
                <AppIcon
                  name="shield"
                  :size="16"
                />
              </span>
            </HoverTooltip>
            <HoverTooltip
              v-else
              :label="isHidden(row.group) ? 'Đang ẩn — bật để hiển thị' : 'Đang hiển thị — tắt để ẩn'"
              placement="left"
            >
              <span
                class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors"
                :class="isHidden(row.group) ? 'bg-slate-300' : 'bg-brand'"
              >
                <span
                  class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform duration-200"
                  :class="isHidden(row.group) ? 'translate-x-0.5' : 'translate-x-[22px]'"
                />
              </span>
            </HoverTooltip>
          </component>
        </li>
      </template>
    </ul>

    <!-- Trạng thái rỗng khi tìm kiếm không khớp -->
    <div
      v-else
      class="flex flex-col items-center gap-2 rounded-xl border border-dashed border-slate-200 px-4 py-10 text-center"
    >
      <span class="grid h-10 w-10 place-items-center rounded-full bg-slate-100 text-slate-400">
        <AppIcon
          name="search"
          :size="18"
        />
      </span>
      <p class="text-[13px] font-medium text-slate-500">
        Không tìm thấy nhóm menu phù hợp
      </p>
      <button
        type="button"
        class="text-[12.5px] font-semibold text-brand hover:text-brand-700"
        @click="query = ''"
      >
        Xoá tìm kiếm
      </button>
    </div>

    <!-- Chú thích dấu * -->
    <p class="mt-3 flex items-start gap-1.5 text-[11.5px] leading-relaxed text-slate-400">
      <span class="font-bold text-rose-500">*</span>
      <span>Nhóm bắt buộc (vd. Cấu hình chung, Phân quyền) luôn hiển thị để không khoá quyền quản trị.</span>
    </p>

    <!-- Lưu -->
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
        Lưu thay đổi
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
        />
        Đã lưu<span v-if="savedAt"> lúc {{ formatSavedTime(savedAt) }}</span>
      </span>
      <span
        v-if="canManage"
        class="text-[11px] text-slate-400"
      >Ctrl+S để lưu</span>
    </div>
  </form>
</template>
