<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import { matchesSearchQuery, normalizeSearchKey } from '@/shared/utils/normalizeSearchKey';

const props = defineProps({
    employees: { type: Array, default: () => [] },
    modelValue: { type: [Number, null], default: null },
    initialLabel: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'pick']);

const inputRef = ref(null);
const panelRef = ref(null);
const open = ref(false);
const query = ref('');
const panelStyle = ref({});

const employeeOptions = computed(() =>
    (props.employees ?? []).map((e) => ({
        ...e,
        subtitle: [e.role_title, e.department, e.email, e.code].filter(Boolean).join(' · '),
    })),
);

const selected = computed(() =>
    employeeOptions.value.find((e) => String(e.id) === String(props.modelValue)) ?? null,
);

function filterEmployees(list, q) {
    const trimmed = q.trim();
    if (!trimmed) return list.slice(0, 50);
    return list.filter((e) => matchesSearchQuery(
        [e.name, e.email, e.code, e.role_title, e.department],
        trimmed,
    ));
}

const filtered = computed(() => filterEmployees(employeeOptions.value, query.value));

function syncQueryFromSelection() {
    if (selected.value) {
        query.value = selected.value.name ?? '';
        return;
    }
    if (!props.modelValue && props.initialLabel) {
        query.value = props.initialLabel;
    }
}

watch(() => [props.modelValue, props.initialLabel, props.employees], syncQueryFromSelection, { immediate: true });

function pick(emp) {
    emit('update:modelValue', emp.id);
    emit('pick', emp);
    query.value = emp.name ?? '';
    open.value = false;
}

function findExactByName(q) {
    const key = normalizeSearchKey(q);
    if (!key) return null;
    return employeeOptions.value.find((e) => normalizeSearchKey(e.name) === key) ?? null;
}

function onInput() {
    open.value = true;
    const q = query.value.trim();
    if (!q) {
        emit('update:modelValue', null);
        return;
    }
    const exact = findExactByName(q);
    if (exact) {
        pick(exact);
        return;
    }
    const matches = filterEmployees(employeeOptions.value, q);
    if (matches.length === 1) {
        pick(matches[0]);
        return;
    }
    emit('update:modelValue', null);
}

function onEnter() {
    if (filtered.value.length > 0) {
        pick(filtered.value[0]);
    }
}

async function positionPanel() {
    await nextTick();
    const el = inputRef.value;
    if (!el) return;
    const rect = el.getBoundingClientRect();
    panelStyle.value = {
        position: 'fixed',
        left: `${rect.left}px`,
        top: `${rect.bottom + 4}px`,
        width: `${rect.width}px`,
        zIndex: 250,
    };
}

const onOutside = (e) => {
    const t = e.target;
    if (inputRef.value?.contains(t) || panelRef.value?.contains(t)) return;
    open.value = false;
    syncQueryFromSelection();
};

watch(open, async (isOpen) => {
    if (isOpen) {
        await positionPanel();
        window.addEventListener('scroll', positionPanel, true);
        window.addEventListener('resize', positionPanel);
        document.addEventListener('mousedown', onOutside);
    } else {
        window.removeEventListener('scroll', positionPanel, true);
        window.removeEventListener('resize', positionPanel);
        document.removeEventListener('mousedown', onOutside);
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', positionPanel, true);
    window.removeEventListener('resize', positionPanel);
    document.removeEventListener('mousedown', onOutside);
});
</script>

<template>
  <div class="relative">
    <div class="relative">
      <AppIcon
        name="search"
        :size="15"
        class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"
      />
      <input
        ref="inputRef"
        v-model="query"
        type="text"
        class="input w-full py-2 pl-9 pr-9"
        :disabled="disabled"
        placeholder="Gõ họ tên, email hoặc mã NV…"
        autocomplete="off"
        @focus="open = true; positionPanel()"
        @input="onInput"
        @keydown.down.prevent="open = true"
        @keydown.enter.prevent="onEnter"
      >
      <button
        v-if="query && !disabled"
        type="button"
        class="absolute right-2 top-1/2 grid h-6 w-6 -translate-y-1/2 place-items-center rounded text-slate-400 hover:bg-slate-100 hover:text-slate-600"
        aria-label="Xóa"
        @click="query = ''; emit('update:modelValue', null); open = true"
      >
        <AppIcon
          name="close"
          :size="14"
        />
      </button>
    </div>

    <p
      v-if="query.trim() && open && filtered.length === 0 && employeeOptions.length"
      class="mt-1 text-xs text-slate-500"
    >
      Không khớp ai — thử gõ ít từ hơn (VD: «Toàn» hoặc email), hoặc nhập tay họ tên bên dưới.
    </p>

    <Teleport to="body">
      <ul
        v-if="open && filtered.length && !disabled"
        ref="panelRef"
        :style="panelStyle"
        class="max-h-60 overflow-y-auto rounded-card border border-slate-200 bg-white py-1 shadow-elevation-2"
        role="listbox"
      >
        <li
          v-for="e in filtered"
          :key="e.id"
          role="option"
          class="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm hover:bg-slate-50"
          :class="String(e.id) === String(modelValue) ? 'bg-brand-50' : ''"
          @mousedown.prevent="pick(e)"
        >
          <Avatar
            :name="e.name"
            :src="e.avatar_path"
            :size="28"
          />
          <div class="min-w-0 flex-1">
            <div class="truncate font-medium text-slate-800">
              {{ e.name }}
            </div>
            <div
              v-if="e.subtitle"
              class="truncate text-xs text-slate-500"
            >
              {{ e.subtitle }}
            </div>
          </div>
        </li>
      </ul>
    </Teleport>
  </div>
</template>
