<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import { matchesSearchQuery, normalizeSearchKey } from '@/shared/utils/normalizeSearchKey';

const props = defineProps({
    options: { type: Array, default: () => [] },
    modelValue: { type: [Number, String, null], default: null },
    placeholder: { type: String, default: 'Gõ để tìm…' },
    id: { type: String, default: null },
    disabled: { type: Boolean, default: false },
    valueKey: { type: String, default: 'id' },
    labelKey: { type: String, default: 'name' },
    subtitleKey: { type: String, default: 'subtitle' },
    searchKeys: { type: Array, default: null },
    clearable: { type: Boolean, default: true },
    creatable: { type: Boolean, default: false },
    createLabel: { type: String, default: 'Tạo «{query}»' },
    /** Nhãn đang tạo mới khi chưa có id (vd. nhóm kiểm thử vừa gõ). */
    createdLabel: { type: String, default: '' },
    emptyText: { type: String, default: 'Không tìm thấy.' },
    panelZIndex: { type: Number, default: 120 },
});

const emit = defineEmits(['update:modelValue', 'create']);

const query = ref('');
const open = ref(false);
const trigger = ref(null);
const panel = ref(null);
const dropdownStyle = ref({});
const openUp = ref(false);

const optionValue = (o) => o?.[props.valueKey];
const optionLabel = (o) => o?.[props.labelKey] ?? '';
const optionSubtitle = (o) => o?.[props.subtitleKey] ?? o?.subtitle ?? o?.code ?? '';

const selected = computed(() =>
    props.options.find((o) => String(optionValue(o)) === String(props.modelValue)) || null,
);

const displayName = computed(() => {
    if (selected.value) return optionLabel(selected.value);
    if (props.createdLabel) return props.createdLabel;
    return '';
});

const keysForSearch = computed(() => (
    props.searchKeys?.length ? props.searchKeys : [props.labelKey, props.subtitleKey]
));

const filtered = computed(() => {
    const q = query.value.trim();
    if (!q || (displayName.value && normalizeSearchKey(q) === normalizeSearchKey(displayName.value))) {
        return props.options.slice(0, 50);
    }
    return props.options
        .filter((o) => matchesSearchQuery(keysForSearch.value.map((k) => o?.[k]), q))
        .slice(0, 50);
});

const createQuery = computed(() => query.value.trim());

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

function syncQueryFromSelection() {
    query.value = displayName.value;
}

watch(displayName, () => {
    if (!open.value) syncQueryFromSelection();
}, { immediate: true });

function choose(o) {
    emit('update:modelValue', o ? optionValue(o) : null);
    query.value = o ? optionLabel(o) : '';
    open.value = false;
}

function createFromQuery() {
    const name = createQuery.value;
    if (!name) return;
    emit('create', name);
    emit('update:modelValue', null);
    query.value = name;
    open.value = false;
}

function clear() {
    emit('update:modelValue', null);
    if (props.creatable) emit('create', '');
    query.value = '';
}

function onEsc() {
    open.value = false;
    syncQueryFromSelection();
}

function onEnter(e) {
    if (!open.value) return;
    e.preventDefault();
    if (canCreate.value) {
        createFromQuery();
        return;
    }
    if (filtered.value[0]) choose(filtered.value[0]);
}

function onFocus() {
    if (props.disabled) return;
    open.value = true;
}

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
    syncQueryFromSelection();
};

let scrollListener = null;

watch(open, async (isOpen) => {
    if (isOpen) {
        await positionPanel();
        scrollListener = () => positionPanel();
        window.addEventListener('scroll', scrollListener, true);
        window.addEventListener('resize', scrollListener);
        document.addEventListener('mousedown', onPointerDownOutside);
    } else if (scrollListener) {
        window.removeEventListener('scroll', scrollListener, true);
        window.removeEventListener('resize', scrollListener);
        scrollListener = null;
        document.removeEventListener('mousedown', onPointerDownOutside);
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
    <div
      ref="trigger"
      class="relative"
    >
      <input
        :id="id"
        v-model="query"
        type="text"
        class="input w-full pr-8"
        :placeholder="placeholder"
        :disabled="disabled"
        autocomplete="off"
        role="combobox"
        :aria-expanded="open"
        aria-autocomplete="list"
        @focus="onFocus"
        @keydown.esc.prevent="onEsc"
        @keydown.enter="onEnter"
      >
      <button
        v-if="clearable && !disabled && (selected || createdLabel || query)"
        type="button"
        class="absolute inset-y-0 right-2 flex items-center text-slate-300 hover:text-slate-500"
        title="Bỏ chọn"
        @click="clear"
      >
        <AppIcon
          name="close"
          :size="14"
        />
      </button>
    </div>

    <Teleport to="body">
      <div
        v-if="open && !disabled"
        ref="panel"
        :style="dropdownStyle"
        class="overflow-hidden rounded-card border border-slate-200 bg-white shadow-elevation-2"
      >
        <ul class="max-h-56 overflow-y-auto py-1">
          <li
            v-for="o in filtered"
            :key="optionValue(o)"
            class="flex cursor-pointer items-center gap-2 px-3 py-1.5 text-sm hover:bg-brand/5"
            :class="String(optionValue(o)) === String(modelValue) ? 'bg-brand/5' : ''"
            @mousedown.prevent="choose(o)"
          >
            <div class="min-w-0 flex-1">
              <div class="truncate text-slate-700">
                {{ optionLabel(o) }}
              </div>
              <div
                v-if="optionSubtitle(o)"
                class="truncate text-[11px] text-slate-400"
              >
                {{ optionSubtitle(o) }}
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
            v-if="canCreate"
            class="cursor-pointer px-3 py-2 text-sm font-medium text-brand hover:bg-brand/5"
            @mousedown.prevent="createFromQuery"
          >
            {{ createButtonLabel }}
          </li>
          <li
            v-else-if="!filtered.length"
            class="px-3 py-3 text-center text-sm text-slate-400"
          >
            {{ emptyText }}
          </li>
        </ul>
      </div>
    </Teleport>
  </div>
</template>
