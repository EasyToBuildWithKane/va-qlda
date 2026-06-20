<script setup>
import { computed, ref } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import Modal from '@/Components/Ui/Modal.vue';
import FieldTooltip from '@/shared/ui/FieldTooltip.vue';
import HoverTooltip from '@/shared/ui/HoverTooltip.vue';

const props = defineProps({
    // Full nav (pre-visibility-filter) so every group can be toggled.
    nav: { type: Array, default: () => [] },
    groupKey: { type: Function, required: true },
    isGroupHidden: { type: Function, required: true },
    toggleGroupVisibility: { type: Function, required: true },
    showAllGroups: { type: Function, required: true },
    hiddenCount: { type: Number, default: 0 },
    isUpcomingGroup: { type: Function, default: () => false },
    groupContainsActive: { type: Function, default: () => false },
});

const open = ref(false);
const query = ref('');

const visibleCount = computed(() => props.nav.filter((g) => !props.isGroupHidden(g)).length);

// Search is only useful past a handful of groups — keep the panel uncluttered otherwise.
const showSearch = computed(() => props.nav.length > 6);

// Whether any group maps to the page the user is currently on (drives the "*" legend).
const hasActiveMark = computed(() => props.nav.some((g) => props.groupContainsActive(g)));

// Filtered rows + per-row "this starts a new super-section" flag (recomputed on the
// filtered list so section labels stay correct while searching).
const rows = computed(() => {
    const q = query.value.trim().toLowerCase();
    const list = q
        ? props.nav.filter((g) =>
            (g.heading || '').toLowerCase().includes(q)
            || (g.section || '').toLowerCase().includes(q)
            || g.items.some((it) => (it.label || '').toLowerCase().includes(q)))
        : props.nav;

    return list.map((group, i) => ({
        group,
        key: props.groupKey(group),
        sectionStart: i === 0 || list[i - 1].section !== group.section,
    }));
});

function reset() {
    query.value = '';
}

function close() {
    open.value = false;
    reset();
}
</script>

<template>
  <div class="shrink-0 border-t border-white/[0.09] px-2 py-2">
    <button
      type="button"
      class="group flex w-full items-center gap-2 rounded-lg px-2 py-2 text-[12.5px] font-medium text-white/55 transition-colors hover:bg-white/[0.06] hover:text-white/90"
      title="Tùy chỉnh nhóm menu hiển thị trên thanh điều hướng"
      aria-haspopup="dialog"
      @click="open = true"
    >
      <span class="grid h-7 w-7 shrink-0 place-items-center rounded-lg text-white/55 group-hover:text-white/90">
        <AppIcon
          name="settings"
          :size="17"
          :stroke-width="1.65"
        />
      </span>
      <span class="flex-1 truncate text-left">Tùy chỉnh menu</span>
      <span
        v-if="hiddenCount > 0"
        class="ml-auto inline-flex min-w-[1.25rem] shrink-0 items-center justify-center rounded-full bg-white/15 px-1.5 py-0.5 text-[10px] font-bold leading-none tabular-nums text-white/90"
        :aria-label="`${hiddenCount} nhóm đang ẩn`"
      >{{ hiddenCount }}</span>
    </button>

    <Modal
      :show="open"
      title="Tùy chỉnh menu sidebar"
      max-width="max-w-lg"
      @close="close"
    >
      <div class="space-y-4">
        <!-- Hướng dẫn sử dụng -->
        <div class="flex gap-2.5 rounded-xl border border-brand/15 bg-brand/[0.04] px-3.5 py-3">
          <span class="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-lg bg-brand/10 text-brand">
            <AppIcon
              name="info"
              :size="15"
            />
          </span>
          <div class="min-w-0 text-[12.5px] leading-relaxed text-slate-600">
            <p class="font-semibold text-slate-700">
              Cá nhân hoá thanh điều hướng
            </p>
            <ul class="mt-1 space-y-0.5 text-slate-500">
              <li>• Bật/tắt công tắc để hiện hoặc ẩn từng nhóm menu.</li>
              <li>• Thay đổi áp dụng ngay và chỉ lưu trên trình duyệt này.</li>
              <li>• Nhấn <span class="font-medium text-slate-600">“Hiện tất cả”</span> bất cứ lúc nào để khôi phục.</li>
            </ul>
          </div>
        </div>

        <!-- Bộ đếm + reset nhanh -->
        <div class="flex items-center justify-between gap-3">
          <div class="flex items-center gap-1.5">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-[12px] font-semibold text-slate-600">
              <AppIcon
                name="eye"
                :size="13"
                class="text-slate-400"
              />
              Đang hiển thị {{ visibleCount }}/{{ nav.length }} nhóm
            </span>
            <FieldTooltip
              text="Số nhóm menu đang hiển thị trên thanh điều hướng so với tổng số nhóm khả dụng theo quyền của bạn."
            />
          </div>
          <button
            type="button"
            class="inline-flex items-center gap-1 text-[12.5px] font-semibold text-brand transition-colors hover:text-brand-700 disabled:cursor-not-allowed disabled:text-slate-300"
            :disabled="hiddenCount === 0"
            @click="showAllGroups"
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
          class="relative"
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
          class="-mr-1 max-h-[20rem] space-y-1 overflow-y-auto pr-1"
        >
          <template
            v-for="row in rows"
            :key="row.key"
          >
            <li
              v-if="row.sectionStart && row.group.section"
              class="select-none px-1 pb-1 pt-2 text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400 first:pt-0"
            >
              {{ row.group.section }}
            </li>
            <li>
              <button
                type="button"
                class="flex w-full items-center gap-3 rounded-xl border px-3 py-2.5 text-left transition-colors"
                :class="isGroupHidden(row.group)
                  ? 'border-slate-100 bg-slate-50/60 hover:bg-slate-100'
                  : 'border-slate-200 bg-white hover:border-brand/30 hover:bg-brand/[0.03]'"
                role="switch"
                :aria-checked="!isGroupHidden(row.group)"
                :aria-label="`${isGroupHidden(row.group) ? 'Hiện' : 'Ẩn'} nhóm ${row.group.heading}`"
                @click="toggleGroupVisibility(row.group)"
              >
                <span
                  class="grid h-9 w-9 shrink-0 place-items-center rounded-lg transition-colors"
                  :class="isGroupHidden(row.group) ? 'bg-slate-200/70 text-slate-400' : 'bg-brand/10 text-brand'"
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
                      :class="isGroupHidden(row.group) ? 'text-slate-400' : 'text-slate-800'"
                    >{{ row.group.heading }}</span>
                    <HoverTooltip
                      v-if="groupContainsActive(row.group)"
                      label="Nhóm chứa trang bạn đang xem"
                      placement="top"
                    >
                      <span class="text-[13px] font-bold leading-none text-rose-500">*</span>
                    </HoverTooltip>
                  </span>
                  <span class="mt-0.5 flex items-center gap-1.5 text-[11.5px] text-slate-400">
                    <span>{{ row.group.items.length }} mục</span>
                    <template v-if="isUpcomingGroup(row.group)">
                      <span aria-hidden="true">·</span>
                      <span class="text-amber-500">Sắp ra mắt</span>
                    </template>
                    <template v-if="isGroupHidden(row.group)">
                      <span aria-hidden="true">·</span>
                      <span class="font-medium text-slate-400">Đã ẩn</span>
                    </template>
                  </span>
                </span>

                <HoverTooltip
                  :label="isGroupHidden(row.group) ? 'Đang ẩn — nhấn để hiển thị' : 'Đang hiển thị — nhấn để ẩn'"
                  placement="left"
                >
                  <span
                    class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors"
                    :class="isGroupHidden(row.group) ? 'bg-slate-300' : 'bg-brand'"
                  >
                    <span
                      class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform duration-150"
                      :class="isGroupHidden(row.group) ? 'translate-x-0.5' : 'translate-x-[1.125rem]'"
                    />
                  </span>
                </HoverTooltip>
              </button>
            </li>
          </template>
        </ul>

        <!-- Trạng thái rỗng khi tìm kiếm không khớp -->
        <div
          v-else
          class="flex flex-col items-center gap-2 rounded-xl border border-dashed border-slate-200 px-4 py-8 text-center"
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
        <p
          v-if="hasActiveMark"
          class="flex items-start gap-1.5 text-[11.5px] leading-relaxed text-slate-400"
        >
          <span class="font-bold text-rose-500">*</span>
          <span>Nhóm chứa trang bạn đang xem — nên giữ hiển thị để dễ quay lại.</span>
        </p>
      </div>

      <!-- Hành động -->
      <div class="mt-5 flex items-center justify-between gap-3 border-t border-slate-100 pt-4">
        <button
          type="button"
          class="btn-ghost text-[13px]"
          :disabled="hiddenCount === 0"
          @click="showAllGroups"
        >
          <AppIcon
            name="refresh"
            :size="15"
          />
          Hiện tất cả
        </button>
        <button
          type="button"
          class="btn-primary text-[13px]"
          @click="close"
        >
          <AppIcon
            name="check"
            :size="15"
          />
          Xong
        </button>
      </div>
    </Modal>
  </div>
</template>
