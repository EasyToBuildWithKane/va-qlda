<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import { httpGet } from '@/shared/services/http';
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
const remoteEmployees = ref([]);
const searching = ref(false);
const searchError = ref(false);

let debounceTimer;
let fetchGen = 0;

function mapRow(e) {
    return {
        ...e,
        subtitle: [e.role_title, e.department, e.email, e.code].filter(Boolean).join(' · '),
    };
}

const employeeOptions = computed(() => {
    const q = query.value.trim();
    const remote = remoteEmployees.value ?? [];
    if (q && remote.length) {
        return remote.map(mapRow);
    }
    if (remote.length) {
        return remote.map(mapRow);
    }
    return (props.employees ?? []).map(mapRow);
});

const selected = computed(() =>
    employeeOptions.value.find((e) => String(e.id) === String(props.modelValue)) ?? null,
);

const filtered = computed(() => employeeOptions.value);

function syncQueryFromSelection() {
    if (selected.value) {
        query.value = selected.value.name ?? '';
        return;
    }
    if (!props.modelValue && props.initialLabel) {
        query.value = props.initialLabel;
    }
}

watch(() => [props.modelValue, props.initialLabel], syncQueryFromSelection, { immediate: true });

async function fetchEmployees({ q = '', id = null } = {}) {
    const gen = ++fetchGen;
    searching.value = true;
    searchError.value = false;
    try {
        const params = {};
        if (id != null) {
            params.id = id;
        } else {
            params.q = q;
        }
        const res = await httpGet(route('api.ai-accounts.employees.search'), { params });
        if (gen !== fetchGen) {
            return;
        }
        remoteEmployees.value = res.data?.employees ?? [];
        if (id != null && remoteEmployees.value[0]) {
            emit('pick', remoteEmployees.value[0]);
        } else if (q && remoteEmployees.value.length) {
            await nextTick();
            tryResolveQuery();
        }
    } catch {
        if (gen === fetchGen) {
            searchError.value = true;
            remoteEmployees.value = [];
        }
    } finally {
        if (gen === fetchGen) {
            searching.value = false;
        }
    }
}

function scheduleSearch() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        fetchEmployees({ q: query.value.trim() });
    }, 280);
}

watch(
    () => props.modelValue,
    (id) => {
        if (id != null && id !== '') {
            fetchEmployees({ id });
        }
    },
    { immediate: true },
);

watch(query, (q) => {
    open.value = true;
    if (!q.trim()) {
        scheduleSearch();
        return;
    }
    scheduleSearch();
});

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
        scheduleSearch();
        return;
    }
    scheduleSearch();
}

function tryResolveQuery() {
    const q = query.value.trim();
    if (!q) return;
    const exact = findExactByName(q);
    if (exact) {
        pick(exact);
        return;
    }
    const local = employeeOptions.value.filter((e) =>
        matchesSearchQuery([e.name, e.email, e.code, e.role_title, e.department], q),
    );
    if (local.length === 1) {
        pick(local[0]);
    }
}

function onEnter() {
    if (filtered.value.length > 0) {
        pick(filtered.value[0]);
    } else {
        tryResolveQuery();
    }
}

function onBlur() {
    window.setTimeout(() => {
        tryResolveQuery();
    }, 200);
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
        width: `${Math.max(rect.width, 280)}px`,
        zIndex: 250,
    };
}

const onOutside = (e) => {
    const t = e.target;
    if (inputRef.value?.contains(t) || panelRef.value?.contains(t)) return;
    open.value = false;
    tryResolveQuery();
    syncQueryFromSelection();
};

watch(open, async (isOpen) => {
    if (isOpen) {
        await positionPanel();
        if (!remoteEmployees.value.length && !searching.value) {
            fetchEmployees({ q: query.value.trim() });
        }
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
    clearTimeout(debounceTimer);
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
        @blur="onBlur"
        @keydown.down.prevent="open = true"
        @keydown.enter.prevent="onEnter"
      >
      <button
        v-if="query && !disabled"
        type="button"
        class="absolute right-2 top-1/2 grid h-6 w-6 -translate-y-1/2 place-items-center rounded text-slate-400 hover:bg-slate-100 hover:text-slate-600"
        aria-label="Xóa"
        @click="query = ''; emit('update:modelValue', null); scheduleSearch(); open = true"
      >
        <AppIcon
          name="close"
          :size="14"
        />
      </button>
    </div>

    <p
      v-if="searching"
      class="mt-1 text-xs text-slate-400"
    >
      Đang tìm…
    </p>
    <p
      v-else-if="searchError"
      class="mt-1 text-xs text-rose-600"
    >
      Không tải được danh sách nhân sự. Thử lại hoặc nhập tay các ô bên dưới.
    </p>
    <p
      v-else-if="query.trim() && open && filtered.length === 0"
      class="mt-1 text-xs text-slate-500"
    >
      Không khớp ai — thử «Toàn», email hoặc mã NV, hoặc nhập tay họ tên bên dưới.
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
