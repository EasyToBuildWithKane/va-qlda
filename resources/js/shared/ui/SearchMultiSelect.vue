<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import { matchesSearchQuery, normalizeSearchKey } from '@/shared/utils/normalizeSearchKey';

/**
 * Searchable multi-select. modelValue is an array of option values.
 * Mirrors SearchSelect.vue's teleported, viewport-aware dropdown.
 */
const props = defineProps({
    options: { type: Array, default: () => [] },
    modelValue: { type: Array, default: () => [] },
    placeholder: { type: String, default: 'Tìm & chọn…' },
    searchPlaceholder: { type: String, default: 'Tìm kiếm…' },
    id: { type: String, default: null },
    disabled: { type: Boolean, default: false },
    showAvatar: { type: Boolean, default: false },
    valueKey: { type: String, default: 'id' },
    labelKey: { type: String, default: 'name' },
    subtitleKey: { type: String, default: 'role_title' },
    searchKeys: { type: Array, default: null },
    panelZIndex: { type: Number, default: 70 },
    /** Max chips rendered inline before collapsing to a "+N" pill. */
    maxChips: { type: Number, default: 3 },
    /** sm = min-h-9 (mặc định); md = h-10 cố định cho filter grid */
    controlSize: { type: String, default: 'sm' },
    /** Cho phép tạo mục mới từ ô tìm khi không khớp danh mục. */
    creatable: { type: Boolean, default: false },
    /** Nhãn nút tạo — dùng `{query}` cho chuỗi đang gõ. */
    createLabel: { type: String, default: 'Thêm «{query}»' },
});

const emit = defineEmits(['update:modelValue', 'create']);

const open = ref(false);
const search = ref('');
const trigger = ref(null);
const panel = ref(null);
const dropdownStyle = ref({});
const openUp = ref(false);

const optionValue = (o) => o?.[props.valueKey];
const optionLabel = (o) => o?.[props.labelKey] ?? '';
const optionSubtitle = (o) => o?.[props.subtitleKey] ?? o?.subtitle ?? '';

const selectedValues = computed(() => (props.modelValue ?? []).map((v) => String(v)));

const selectedOptions = computed(() =>
    props.options.filter((o) => selectedValues.value.includes(String(optionValue(o)))),
);

const isSelected = (o) => selectedValues.value.includes(String(optionValue(o)));

const keysForSearch = computed(() => (props.searchKeys?.length ? props.searchKeys : [props.labelKey, props.subtitleKey]));

const filtered = computed(() => {
    const q = search.value.trim();
    if (!q) return props.options;
    return props.options.filter((o) => matchesSearchQuery(keysForSearch.value.map((k) => o?.[k]), q));
});

const createQuery = computed(() => search.value.trim());

const canCreate = computed(() => {
    if (!props.creatable || props.disabled) return false;
    const q = createQuery.value;
    if (!q) return false;
    const key = normalizeSearchKey(q);
    return !props.options.some((o) => normalizeSearchKey(optionLabel(o)) === key);
});

const createButtonLabel = computed(() =>
    props.createLabel.replaceAll('{query}', createQuery.value),
);

const toggle = (o) => {
    const v = optionValue(o);
    const exists = isSelected(o);
    const next = exists
        ? props.modelValue.filter((x) => String(x) !== String(v))
        : [...props.modelValue, v];
    emit('update:modelValue', next);
};

const createFromSearch = () => {
    if (!canCreate.value) return;
    const name = createQuery.value;
    emit('create', name);
    search.value = '';
};

const removeValue = (v) => {
    emit('update:modelValue', props.modelValue.filter((x) => String(x) !== String(v)));
};

const clearAll = () => emit('update:modelValue', []);

const toggleOpen = () => {
    if (props.disabled) return;
    open.value = !open.value;
};

const positionPanel = async () => {
    await nextTick();
    const el = trigger.value;
    if (!el) return;
    const rect = el.getBoundingClientRect();
    const panelMaxH = 300;
    const gap = 4;
    const spaceBelow = window.innerHeight - rect.bottom - gap;
    const spaceAbove = rect.top - gap;
    openUp.value = spaceBelow < panelMaxH && spaceAbove > spaceBelow;
    dropdownStyle.value = {
        position: 'fixed',
        left: `${rect.left}px`,
        width: `${rect.width}px`,
        zIndex: props.panelZIndex,
        ...(openUp.value
            ? { bottom: `${window.innerHeight - rect.top + gap}px` }
            : { top: `${rect.bottom + gap}px` }),
    };
};

const onPointerDownOutside = (e) => {
    const t = e.target;
    if (trigger.value?.contains(t) || panel.value?.contains(t)) return;
    open.value = false;
};

let scrollListener = null;

watch(open, async (isOpen) => {
    if (isOpen) {
        await positionPanel();
        scrollListener = () => positionPanel();
        window.addEventListener('scroll', scrollListener, true);
        window.addEventListener('resize', scrollListener);
        document.addEventListener('mousedown', onPointerDownOutside);
    } else {
        if (scrollListener) {
            window.removeEventListener('scroll', scrollListener, true);
            window.removeEventListener('resize', scrollListener);
            scrollListener = null;
        }
        document.removeEventListener('mousedown', onPointerDownOutside);
        search.value = '';
    }
});

onBeforeUnmount(() => {
    if (scrollListener) {
        window.removeEventListener('scroll', scrollListener, true);
        window.removeEventListener('resize', scrollListener);
    }
    document.removeEventListener('mousedown', onPointerDownOutside);
});

const overflowCount = computed(() => Math.max(0, selectedOptions.value.length - props.maxChips));
const visibleChips = computed(() => selectedOptions.value.slice(0, props.maxChips));
</script>

<template>
  <div class="relative">
    <button
      :id="id"
      ref="trigger"
      type="button"
      :class="[
        'input flex w-full flex-wrap items-center gap-1 text-left',
        controlSize === 'md' ? 'h-10 min-h-10 max-h-10 overflow-hidden py-0 px-2.5' : 'min-h-9 py-1',
        disabled ? 'cursor-not-allowed opacity-60' : '',
      ]"
      :disabled="disabled"
      @click="toggleOpen"
    >
      <template v-if="visibleChips.length">
        <span
          v-for="o in visibleChips"
          :key="optionValue(o)"
          class="inline-flex max-w-[10rem] items-center gap-1 rounded-full bg-brand-50 py-0.5 pl-1 pr-1.5 text-xs font-medium text-brand-700 dark:bg-brand-950/40 dark:text-brand-100"
        >
          <Avatar
            v-if="showAvatar"
            :name="optionLabel(o)"
            :src="o.avatar_path"
            :size="16"
          />
          <span class="truncate">{{ optionLabel(o) }}</span>
          <span
            class="grid h-4 w-4 shrink-0 place-items-center rounded-full text-brand-400 hover:text-rose-500"
            role="button"
            aria-label="Bỏ chọn"
            @click.stop="removeValue(optionValue(o))"
          >
            <AppIcon
              name="close"
              :size="11"
            />
          </span>
        </span>
        <span
          v-if="overflowCount"
          class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-500 dark:bg-slate-700 dark:text-slate-300"
        >+{{ overflowCount }}</span>
        <span
          class="ml-auto grid h-5 w-5 place-items-center rounded text-slate-300 hover:text-rose-500"
          role="button"
          aria-label="Xoá tất cả"
          @click.stop="clearAll"
        >
          <AppIcon
            name="close"
            :size="14"
          />
        </span>
      </template>
      <template v-else>
        <span class="flex-1 truncate text-slate-400">{{ placeholder }}</span>
        <AppIcon
          name="chevron-down"
          :size="16"
          class="text-slate-400"
        />
      </template>
    </button>

    <Teleport to="body">
      <div
        v-if="open"
        ref="panel"
        :style="dropdownStyle"
        class="overflow-hidden rounded-card border border-slate-200 bg-white shadow-elevation-2 dark:border-slate-700 dark:bg-slate-900"
      >
        <div class="border-b border-slate-100 p-2 dark:border-slate-700">
          <div class="relative">
            <AppIcon
              name="search"
              :size="15"
              class="pointer-events-none absolute left-2.5 top-2.5 text-slate-400"
            />
            <input
              v-model="search"
              type="text"
              class="input py-1.5 pl-8 text-sm"
              :placeholder="searchPlaceholder"
              autofocus
              @keydown.enter.prevent="canCreate ? createFromSearch() : undefined"
            >
          </div>
        </div>
        <ul class="max-h-56 overflow-y-auto py-1">
          <li
            v-if="canCreate"
            class="flex cursor-pointer items-center gap-2 border-b border-slate-100 px-3 py-2 text-sm text-brand hover:bg-brand-50 dark:border-slate-700 dark:hover:bg-brand-950/30"
            @click="createFromSearch"
          >
            <span class="grid h-4 w-4 shrink-0 place-items-center rounded border border-brand/40 bg-brand/10 text-brand">
              <AppIcon
                name="plus"
                :size="12"
              />
            </span>
            <span class="min-w-0 flex-1 font-medium">{{ createButtonLabel }}</span>
          </li>
          <li
            v-for="o in filtered"
            :key="optionValue(o)"
            class="flex cursor-pointer items-center gap-2 px-3 py-1.5 text-sm hover:bg-slate-50 dark:hover:bg-slate-800"
            :class="isSelected(o) ? 'bg-brand-50 dark:bg-brand-950/30' : ''"
            @click="toggle(o)"
          >
            <span
              class="grid h-4 w-4 shrink-0 place-items-center rounded border"
              :class="isSelected(o) ? 'border-brand bg-brand text-white' : 'border-slate-300 dark:border-slate-600'"
            >
              <AppIcon
                v-if="isSelected(o)"
                name="check"
                :size="12"
              />
            </span>
            <Avatar
              v-if="showAvatar"
              :name="optionLabel(o)"
              :src="o.avatar_path"
              :size="24"
            />
            <div class="min-w-0 flex-1">
              <div class="truncate text-slate-700 dark:text-slate-200">
                {{ optionLabel(o) }}
              </div>
              <div
                v-if="optionSubtitle(o)"
                class="truncate text-xs text-slate-400"
              >
                {{ optionSubtitle(o) }}
              </div>
            </div>
          </li>
          <li
            v-if="filtered.length === 0 && !canCreate"
            class="px-3 py-3 text-center text-sm text-slate-400"
          >
            Không tìm thấy.
          </li>
        </ul>
      </div>
    </Teleport>
  </div>
</template>
