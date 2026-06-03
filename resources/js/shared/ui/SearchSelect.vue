<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import { matchesSearchQuery } from '@/shared/utils/normalizeSearchKey';

const props = defineProps({
    options: { type: Array, default: () => [] },
    modelValue: { type: [Number, String, null], default: null },
    placeholder: { type: String, default: 'Tìm & chọn…' },
    searchPlaceholder: { type: String, default: 'Tìm kiếm…' },
    id: { type: String, default: null },
    disabled: { type: Boolean, default: false },
    showAvatar: { type: Boolean, default: false },
    valueKey: { type: String, default: 'id' },
    labelKey: { type: String, default: 'name' },
    /** Các field option dùng khi lọc (mặc định: labelKey). */
    searchKeys: { type: Array, default: null },
    clearable: { type: Boolean, default: true },
    /** z-index panel (modal dùng 120+). */
    panelZIndex: { type: Number, default: 70 },
});

const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const search = ref('');
const trigger = ref(null);
const panel = ref(null);
const dropdownStyle = ref({});
const openUp = ref(false);

const optionValue = (o) => o?.[props.valueKey];
const optionLabel = (o) => o?.[props.labelKey] ?? '';

const selected = computed(() =>
    props.options.find((o) => String(optionValue(o)) === String(props.modelValue)) || null,
);

const keysForSearch = computed(() => props.searchKeys?.length ? props.searchKeys : [props.labelKey]);

function optionSearchFields(o) {
    return keysForSearch.value.map((k) => o?.[k]);
}

const filtered = computed(() => {
    const q = search.value.trim();
    if (!q) return props.options;
    return props.options.filter((o) => matchesSearchQuery(optionSearchFields(o), q));
});

const choose = (o) => {
    emit('update:modelValue', o ? optionValue(o) : null);
    open.value = false;
    search.value = '';
};

const toggleOpen = () => {
    if (props.disabled) return;
    open.value = !open.value;
};

const positionPanel = async () => {
    await nextTick();
    const el = trigger.value;
    if (!el) return;

    const rect = el.getBoundingClientRect();
    const panelMaxH = 280;
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
</script>

<template>
  <div class="relative">
    <button
      :id="id"
      ref="trigger"
      type="button"
      class="input flex w-full items-center gap-2 text-left"
      :class="disabled ? 'cursor-not-allowed opacity-60' : ''"
      :disabled="disabled"
      @click="toggleOpen"
    >
      <template v-if="selected">
        <Avatar
          v-if="showAvatar"
          :name="optionLabel(selected)"
          :src="selected.avatar_path"
          :size="22"
        />
        <span class="flex-1 truncate text-slate-700 dark:text-slate-200">{{ optionLabel(selected) }}</span>
        <span
          v-if="clearable && !disabled"
          class="grid h-5 w-5 place-items-center rounded text-slate-300 hover:text-rose-500"
          role="button"
          aria-label="Bỏ chọn"
          @click.stop="choose(null)"
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
            >
          </div>
        </div>
        <ul class="max-h-56 overflow-y-auto py-1">
          <li
            v-for="o in filtered"
            :key="optionValue(o)"
            class="flex cursor-pointer items-center gap-2 px-3 py-1.5 text-sm hover:bg-slate-50 dark:hover:bg-slate-800"
            :class="optionValue(o) === modelValue ? 'bg-brand-50 dark:bg-brand-950/30' : ''"
            @click="choose(o)"
          >
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
                v-if="o.subtitle"
                class="truncate text-xs text-slate-400"
              >
                {{ o.subtitle }}
              </div>
            </div>
            <AppIcon
              v-if="String(optionValue(o)) === String(modelValue)"
              name="check"
              :size="15"
              class="text-brand"
            />
          </li>
          <li
            v-if="filtered.length === 0"
            class="px-3 py-3 text-center text-sm text-slate-400"
          >
            Không tìm thấy.
          </li>
        </ul>
      </div>
    </Teleport>
  </div>
</template>
